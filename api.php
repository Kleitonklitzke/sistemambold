<?php
// Silencia erros para não poluir a saída JSON
ini_set('display_errors', 0);
error_reporting(0);

// Define o cabeçalho como JSON
header('Content-Type: application/json; charset=utf-8');

// ===== Includes Comuns =====
require_once __DIR__ . '/core/LojaConfig.php';
require_once __DIR__ . '/core/PdoLoader.php';

// ===== Roteador de Endpoint =====
// Define qual funcionalidade da API será usada: 'totais' ou 'orcamento'
$endpoint = $_GET['endpoint'] ?? 'totais';

// --- LÓGICA PARA TOTAIS DE VENDAS (DIA/MÊS) ---
if ($endpoint === 'totais') {

    // --- Parâmetros ---
    $periodo = $_GET['periodo'] ?? 'dia'; // 'dia' ou 'mes'
    $lojasReq = isset($_GET['lojas']) ? array_filter(explode(',', $_GET['lojas'])) : [];
    $dataBase = isset($_GET['data']) && !empty($_GET['data']) ? $_GET['data'] : date('Y-m-d');

    if (empty($lojasReq)) {
        echo json_encode([]);
        exit;
    }

    // --- Intervalo de Datas ---
    if ($periodo === 'mes') {
        $datainicio = date('Y-m-01 00:00:00', strtotime($dataBase));
        $datafim    = date('Y-m-t 23:59:59', strtotime($dataBase));
    } else {
        $datainicio = $dataBase . ' 00:00:00';
        $datafim    = $dataBase . ' 23:59:59';
    }

    $status_venda   = 'F';
    $status_estorno = 'C';
    $resultadosFinais = [];

    // --- Processamento por Loja ---
    foreach ($lojasReq as $nomeLoja) {
        if (!LojaConfig::exists($nomeLoja)) {
            continue;
        }
        $cfg = LojaConfig::get($nomeLoja);
        $codloja = $cfg['codloja'];

        $resultadoLoja = [
            'loja' => $nomeLoja, 'status' => 'ok',
            'venda_bruta' => 0, 'venda_liquida' => 0, 'descontos' => 0,
            'cmv' => 0, 'lucro' => 0, 'atendimentos' => 0, 'devolucoes' => 0, 'estornos' => 0,
            'classes' => [], 'vendedores' => []
        ];

        try {
            $pdo = PdoLoader::conecta($nomeLoja);

            // 1. Cálculos de Vendas, Estornos, CMV
            $sqlBase = "
                SELECT
                    SUM(IIF(v.STATUS = :status_venda, vp.VALORBRUTO, 0)) as venda_bruta_total,
                    SUM(IIF(v.STATUS = :status_venda, vp.VALORPRODUTO, 0)) as venda_liquida_total,
                    SUM(IIF(v.STATUS = :status_venda, vp.CUSTOMEDIO, 0)) as cmv_total,
                    SUM(IIF(v.STATUS = :status_estorno, vp.VALORPRODUTO, 0)) as estorno_liquido_total
                FROM vendaprodutos vp
                JOIN venda v ON vp.IDVENDA = v.IDVENDA
                WHERE vp.DATAHORAVENDA BETWEEN :datainicio AND :datafim AND v.CODLOJA = :codloja AND v.STATUS IN (:status_venda, :status_estorno)
            ";
            $stmt = $pdo->prepare($sqlBase);
            $stmt->execute([
                ':status_venda' => $status_venda, ':status_estorno' => $status_estorno,
                ':datainicio' => $datainicio, ':datafim' => $datafim, ':codloja' => $codloja
            ]);
            $totaisBase = $stmt->fetch(PDO::FETCH_ASSOC);

            // 2. Devoluções
            $sqlDev = "SELECT SUM(VALORPRODUTO) as total_dev, SUM(CUSTOMEDIO) as cmv_dev FROM devolucao WHERE DATAHORADEVOLUC BETWEEN :datainicio AND :datafim AND CODLOJA = :codloja";
            $stmtDev = $pdo->prepare($sqlDev);
            $stmtDev->execute([':datainicio' => $datainicio, ':datafim' => $datafim, ':codloja' => $codloja]);
            $totaisDev = $stmtDev->fetch(PDO::FETCH_ASSOC);

            // 3. Atendimentos
            $sqlAtend = "SELECT COUNT(DISTINCT IDVENDA) FROM venda WHERE DATAHORAVENDA BETWEEN :datainicio AND :datafim AND CODLOJA = :codloja AND STATUS = :status_venda";
            $stmtAtend = $pdo->prepare($sqlAtend);
            $stmtAtend->execute([':datainicio' => $datainicio, ':datafim' => $datafim, ':codloja' => $codloja, ':status_venda' => $status_venda]);
            $atendimentos = (int)$stmtAtend->fetchColumn();

            // 4. Consolidação
            $vendaBruta = (float)($totaisBase['venda_bruta_total'] ?? 0);
            $vendaLiquidaParcial = (float)($totaisBase['venda_liquida_total'] ?? 0);
            $cmvParcial = (float)($totaisBase['cmv_total'] ?? 0);
            $devolucoes = (float)($totaisDev['total_dev'] ?? 0);
            $cmvDevolucoes = (float)($totaisDev['cmv_dev'] ?? 0);
            $estornosLiquido = (float)($totaisBase['estorno_liquido_total'] ?? 0);
            
            $vendaLiquidaFinal = $vendaLiquidaParcial - $devolucoes;
            $cmvFinal = $cmvParcial - $cmvDevolucoes;
            $lucroFinal = $vendaLiquidaFinal - $cmvFinal;

            $resultadoLoja['venda_bruta'] = $vendaBruta;
            $resultadoLoja['venda_liquida'] = $vendaLiquidaFinal;
            $resultadoLoja['descontos'] = $vendaBruta - $vendaLiquidaFinal;
            $resultadoLoja['cmv'] = $cmvFinal;
            $resultadoLoja['lucro'] = $lucroFinal;
            $resultadoLoja['devolucoes'] = $devolucoes;
            $resultadoLoja['estornos'] = $estornosLiquido;
            $resultadoLoja['atendimentos'] = $atendimentos;

        } catch (Exception $e) {
            $resultadoLoja['status'] = 'erro';
            $resultadoLoja['mensagem'] = $e->getMessage();
        }
        $resultadosFinais[] = $resultadoLoja;
    }
    echo json_encode($resultadosFinais);
    exit;
}
// --- FIM DA LÓGICA DE TOTAIS ---


// --- LÓGICA PARA ORÇAMENTO ---
if ($endpoint === 'orcamento') {

    // --- Includes Específicos ---
    require_once __DIR__ . '/core/DateHelper.php';
    require_once __DIR__ . '/repositories/FinanceiroRepository.php';
    require_once __DIR__ . '/repositories/OrcamentoConfigRepository.php';
    require_once __DIR__ . '/services/OrcamentoService.php';
    
    // --- Funções Auxiliares (copiadas do api/orcamento.php original) ---
    function _utf8ize(mixed $data): mixed {
        if (is_array($data)) { foreach ($data as $k => $v) { $data[$k] = _utf8ize($v); } return $data; }
        if (is_object($data)) { foreach (get_object_vars($data) as $k => $v) { $data->$k = _utf8ize($v); } return $data; }
        if (is_string($data)) {
            if (preg_match('//u', $data)) return $data;
            $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $data);
            if ($converted !== false && $converted !== '') return $converted;
            return @iconv('UTF-8', 'UTF-8//IGNORE', $data) ?: '';
        }
        return $data;
    }

    function _jsonOut(array $payload, int $httpCode = 200): void {
        http_response_code($httpCode);
        $payload = _utf8ize($payload);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($json === false) {
            http_response_code(500);
            echo '{"error":"Falha ao gerar JSON","detail":"' . addslashes(json_last_error_msg()) . '"}';
            return;
        }
        echo $json;
    }

    // --- Lógica Principal do Orçamento ---
    try {
        $mesRef = isset($_GET['mes']) ? preg_replace('/[^0-9\-]/', '', $_GET['mes']) : date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $mesRef)) {
            _jsonOut(['error' => 'Parâmetro mes inválido. Use YYYY-MM.'], 400);
            exit;
        }

        $lojasParam = $_GET['lojas'] ?? '';
        $lojas = array_filter(array_map('trim', explode(',', $lojasParam)));
        if (empty($lojas)) {
            $lojas = array_keys(LojaConfig::all());
        }

        $lojas = array_values(array_filter($lojas, fn($id) => LojaConfig::exists($id)));
        if (empty($lojas)) {
            _jsonOut(['error' => 'Nenhuma loja válida fornecida.'], 400);
            exit;
        }

        $cfg = new OrcamentoConfigRepository();
        $porLoja = [];
        $erros = [];
        
        $consolidadoService = new OrcamentoService(null, $cfg);
        $resultadoConsolidado = $consolidadoService->calcularConsolidado($lojas, $mesRef);

        _jsonOut($resultadoConsolidado, 200);

    } catch (Throwable $e) {
        _jsonOut([
            'error' => 'Erro fatal ao calcular dotação.',
            'detail' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
    exit;
}
// --- FIM DA LÓGICA DE ORÇAMENTO ---

// --- Resposta Padrão ---
// Se nenhum endpoint corresponder, retorna erro.
http_response_code(400);
echo json_encode(['error' => 'Endpoint inválido']);
exit;
?>