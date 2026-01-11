<?php
declare(strict_types=1);

// Garante que absolutamente nada (BOM, warnings/notices, espaços) vaze antes do JSON.
// Isso evita o front ficar com "—" por falha de JSON.parse().
ob_start();
header('Content-Type: application/json; charset=utf-8');

/**
 * Normaliza strings para UTF-8 para evitar json_encode() retornar false
 * quando existirem bytes inválidos vindos do banco (latin1 etc.).
 */
function _utf8ize(mixed $data): mixed {
    if (is_array($data)) {
        foreach ($data as $k => $v) {
            $data[$k] = _utf8ize($v);
        }
        return $data;
    }
    if (is_object($data)) {
        foreach (get_object_vars($data) as $k => $v) {
            $data->$k = _utf8ize($v);
        }
        return $data;
    }
    if (is_string($data)) {
        // Se já for UTF-8 válido, mantém.
        if (preg_match('//u', $data)) {
            return $data;
        }
        // Tenta converter de ISO-8859-1 / Windows-1252 para UTF-8.
        $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $data);
        if ($converted !== false && $converted !== '') {
            return $converted;
        }
        $converted = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $data);
        if ($converted !== false && $converted !== '') {
            return $converted;
        }
        // Fallback: remove bytes inválidos.
        return @iconv('UTF-8', 'UTF-8//IGNORE', $data) ?: '';
    }
    return $data;
}

function _jsonOut(array $payload, int $httpCode = 200): void {
    http_response_code($httpCode);
    // garante UTF-8 em toda a estrutura
    $payload = _utf8ize($payload);
    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
    );
    if ($json === false) {
        // fallback mínimo
        http_response_code(500);
        echo '{"error":"Falha ao gerar JSON","detail":"' . addslashes(json_last_error_msg()) . '"}';
        return;
    }
    echo $json;
}

// Captura erros fatais (ex.: require com caminho errado, parse error, etc.)
// para não deixar o endpoint em branco e quebrar o JSON.parse() no front.
register_shutdown_function(function (): void {
    $err = error_get_last();
    if (!$err) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array($err['type'] ?? 0, $fatalTypes, true)) {
        return;
    }

    if (ob_get_length() !== false) {
        @ob_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    _jsonOut([
        'error'  => 'Erro fatal no endpoint orcamento.php',
        'detail' => ($err['message'] ?? 'Erro fatal'),
        'file'   => ($err['file'] ?? null),
        'line'   => ($err['line'] ?? null),
    ], 500);
});

require_once __DIR__ . '/../core/LojaConfig.php';
require_once __DIR__ . '/../core/PdoLoader.php';
require_once __DIR__ . '/../core/DateHelper.php';
require_once __DIR__ . '/../repositories/FinanceiroRepository.php';
require_once __DIR__ . '/../repositories/OrcamentoConfigRepository.php';
require_once __DIR__ . '/../services/OrcamentoService.php';

try {
    $mesRef = isset($_GET['mes']) ? preg_replace('/[^0-9\-]/', '', $_GET['mes']) : date('Y-m');
    if (!preg_match('/^\d{4}-\d{2}$/', $mesRef)) {
        if (ob_get_length() !== false) { @ob_clean(); }
        _jsonOut(['error' => 'Parâmetro mes inválido. Use YYYY-MM.'], 400);
        exit;
    }

    $lojasParam = $_GET['lojas'] ?? '';
    $lojas = array_filter(array_map('trim', explode(',', $lojasParam)));
    if (empty($lojas)) {
        // padrão: todas
        $lojas = array_keys(LojaConfig::all());
    }

    // valida lojas
    $lojas = array_values(array_filter($lojas, fn($id) => LojaConfig::exists($id)));
    if (empty($lojas)) {
        if (ob_get_length() !== false) { @ob_clean(); }
        _jsonOut(['error' => 'Nenhuma loja válida em lojas=.'], 400);
        exit;
    }

    $cfg = new OrcamentoConfigRepository();

    $porLoja = [];
    $sumDot = 0.0;
    $sumCompras = 0.0;
    $sumBase = 0.0;
    $sumF1Weighted = 0.0;
    $sumF2Weighted = 0.0;
    $sumF3 = 0.0;

    // categorias consolidadas: [nome => ['consumido'=>, 'dotacao'=>, 'pf_w'=>]]
    $catsAgg = [];

    $erros = [];

    foreach ($lojas as $lojaId) {
        try {
            $pdo = PdoLoader::fromLojaId($lojaId);
            $repo = new FinanceiroRepository($pdo);
            $svc  = new OrcamentoService($repo, $cfg);

            $codloja = LojaConfig::codloja($lojaId);
            $calc = $svc->calcular($lojaId, $codloja, $mesRef);

            $porLoja[$lojaId] = $calc;
        } catch (Throwable $e) {
            // Não derruba o consolidado se uma loja tiver divergência de schema.
            $erros[$lojaId] = $e->getMessage();
            $porLoja[$lojaId] = [
                'loja_id' => $lojaId,
                'loja_label' => LojaConfig::label($lojaId),
                'mes' => $mesRef,
                'error' => true,
                'message' => $e->getMessage(),
                'cmv_base' => 0,
                'f1_vendas' => 1,
                'f2_score' => 1,
                'f3_ajuste' => 0,
                'dotacao' => 0,
                'compras_mes' => 0,
                'percent_consumido' => 0,
                'percent_fator' => 100,
                'categorias' => [],
            ];
            continue;
        }

        $sumDot  += (float)$calc['dotacao'];
        $sumBase += (float)$calc['cmv_base'];
        $sumF3   += (float)$calc['f3_ajuste'];
        $sumCompras += (float)($calc['compras_mes'] ?? 0);

        // agrega categorias
        if (!empty($calc['categorias']) && is_array($calc['categorias'])) {
            foreach ($calc['categorias'] as $row) {
                $nome = $row['nome'] ?? 'Sem classe';
                if (!isset($catsAgg[$nome])) {
                    $catsAgg[$nome] = ['consumido'=>0.0,'dotacao'=>0.0,'pf_w'=>0.0];
                }
                $cons = (float)($row['consumido'] ?? 0);
                $dot  = (float)($row['dotacao'] ?? 0);
                $pf   = (float)($row['percent_fator'] ?? 0);
                $catsAgg[$nome]['consumido'] += $cons;
                $catsAgg[$nome]['dotacao']   += $dot;
                $catsAgg[$nome]['pf_w']      += $pf * ($dot > 0 ? $dot : 1.0);
            }
        }

        // pesos: cmv_base (se 0, peso 0)
        $w = (float)$calc['cmv_base'];
        $sumF1Weighted += $w * (float)$calc['f1_vendas'];
        $sumF2Weighted += $w * (float)$calc['f2_score'];
    }

    $f1Med = ($sumBase > 0) ? ($sumF1Weighted / $sumBase) : 1.0;
    $f2Med = ($sumBase > 0) ? ($sumF2Weighted / $sumBase) : 1.0;

    $percentConsumidoCons = ($sumDot > 0) ? (($sumCompras / $sumDot) * 100.0) : 0.0;

    // finaliza categorias consolidadas
    $categoriasCons = [];
    foreach ($catsAgg as $nome => $agg) {
        $dot = (float)$agg['dotacao'];
        $cons = (float)$agg['consumido'];
        $pf = ($dot > 0) ? ((float)$agg['pf_w'] / $dot) : 0.0;
        $pu = ($dot > 0) ? (($cons / $dot) * 100.0) : 0.0;
        $categoriasCons[] = [
            'nome' => $nome,
            'consumido' => round($cons, 2),
            'dotacao' => round($dot, 2),
            'percent_fator' => round($pf, 1),
            'percent_util' => round($pu, 1),
        ];
    }
    usort($categoriasCons, fn($a,$b) => ($b['consumido'] <=> $a['consumido']));


    $consolidado = [
        'loja_id'       => 'consolidado',
        'loja_label'    => 'Consolidado',
        'mes'           => $mesRef,
        'cmv_base'      => round($sumBase, 2),
        'f1_vendas'     => round($f1Med, 4),
        'f2_score'      => round($f2Med, 4),
        'f3_ajuste'     => round($sumF3, 2),
        'dotacao'       => round($sumDot, 2),
        'compras_mes'   => round($sumCompras, 2),
        'percent_consumido' => round($percentConsumidoCons, 1),
        'percent_fator' => round($f1Med * 100.0, 2),
        'categorias'    => $categoriasCons,
    ];

    if (ob_get_length() !== false) { @ob_clean(); }
    _jsonOut([
        'mes' => $mesRef,
        'lojas' => $lojas,
        'erros' => $erros,
        'por_loja' => $porLoja,
        'consolidado' => $consolidado,
    ], 200);
    exit;
} catch (Throwable $e) {
    if (ob_get_length() !== false) { @ob_clean(); }
    _jsonOut([
        'error' => 'Erro ao calcular dotação.',
        'detail' => $e->getMessage(),
    ], 500);
    exit;
}
