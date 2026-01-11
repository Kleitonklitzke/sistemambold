<?php
ini_set('display_errors', 0); // Desativa a exibição de erros na resposta final
error_reporting(0);
header('Content-Type: application/json');

require_once __DIR__ . '/core/LojaConfig.php';
require_once __DIR__ . '/core/PdoLoader.php';

// Parâmetros da requisição
$periodo = $_GET['periodo'] ?? 'dia'; // 'dia' ou 'mes'
$lojasReq = isset($_GET['lojas']) ? explode(',', $_GET['lojas']) : [];
$dataBase = isset($_GET['data']) && !empty($_GET['data']) ? $_GET['data'] : date('Y-m-d');

// Determina o intervalo de datas com base no período
if ($periodo === 'mes') {
    $datainicio = date('Y-m-01 00:00:00', strtotime($dataBase));
    $datafim    = date('Y-m-t 23:59:59', strtotime($dataBase));
} else {
    $datainicio = $dataBase . ' 00:00:00';
    $datafim    = $dataBase . ' 23:59:59';
}

$status_venda   = 'F';
$status_estorno = 'C';

$todasAsLojas = LojaConfig::all();
$resultadosFinais = [];

// Processa apenas as lojas solicitadas
foreach ($lojasReq as $nomeLoja) {
    if (!isset($todasAsLojas[$nomeLoja])) {
        continue;
    }
    $cfg = $todasAsLojas[$nomeLoja];
    $resultadoLoja = [
        'loja' => $nomeLoja,
        'status' => 'ok',
        'venda_bruta' => 0, 'venda_liquida' => 0, 'descontos' => 0,
        'cmv' => 0, 'lucro' => 0, 'atendimentos' => 0, 'devolucoes' => 0, 'estornos' => 0,
        'classes' => [], 'vendedores' => []
    ];

    try {
        $pdo = PdoLoader::conecta($nomeLoja);

        // 1. CÁLCULO PRINCIPAL BASEADO EM VENDAPRODUTOS
        $sqlBase = "
            SELECT
                SUM(IIF(v.STATUS = ?, vp.VALORBRUTO, 0)) as venda_bruta_total,
                SUM(IIF(v.STATUS = ?, vp.VALORPRODUTO, 0)) as venda_liquida_total,
                SUM(IIF(v.STATUS = ?, vp.CUSTOMEDIO, 0)) as cmv_total,
                SUM(IIF(v.STATUS = ?, vp.VALORBRUTO, 0)) as estorno_bruto_total,
                SUM(IIF(v.STATUS = ?, vp.VALORPRODUTO, 0)) as estorno_liquido_total,
                SUM(IIF(v.STATUS = ?, vp.CUSTOMEDIO, 0)) as estorno_cmv_total
            FROM vendaprodutos vp
            JOIN venda v ON vp.IDVENDA = v.IDVENDA
            WHERE vp.DATAHORAVENDA BETWEEN ? AND ? AND v.CODLOJA = ? AND v.STATUS IN (?, ?)
        ";
        $stmt = $pdo->prepare($sqlBase);
        $stmt->execute([
            $status_venda, $status_venda, $status_venda, // Vendas
            $status_estorno, $status_estorno, $status_estorno, // Estornos
            $datainicio, $datafim, $cfg['codloja'], $status_venda, $status_estorno
        ]);
        $totaisBase = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2. DEVOLUÇÕES
        $sqlDev = "SELECT SUM(VALORPRODUTO) as total_dev, SUM(CUSTOMEDIO) as cmv_dev FROM devolucao WHERE DATAHORADEVOLUC BETWEEN ? AND ? AND CODLOJA = ?";
        $stmtDev = $pdo->prepare($sqlDev);
        $stmtDev->execute([$datainicio, $datafim, $cfg['codloja']]);
        $totaisDev = $stmtDev->fetch(PDO::FETCH_ASSOC);

        // 3. ATENDIMENTOS
        $sqlAtend = "SELECT COUNT(DISTINCT IDVENDA) FROM venda WHERE DATAHORAVENDA BETWEEN ? AND ? AND CODLOJA = ? AND STATUS = ?";
        $stmtAtend = $pdo->prepare($sqlAtend);
        $stmtAtend->execute([$datainicio, $datafim, $cfg['codloja'], $status_venda]);
        $atendimentos = (int)$stmtAtend->fetchColumn();

        // 4. Consolidar totais
        $vendaBruta = (float)($totaisBase['venda_bruta_total'] ?? 0);
        $vendaLiquida = (float)($totaisBase['venda_liquida_total'] ?? 0);
        $cmv = (float)($totaisBase['cmv_total'] ?? 0);
        $devolucoes = (float)($totaisDev['total_dev'] ?? 0);
        $cmvDevolucoes = (float)($totaisDev['cmv_dev'] ?? 0);
        $estornosLiquido = (float)($totaisBase['estorno_liquido_total'] ?? 0);

        $resultadoLoja['venda_bruta'] = $vendaBruta;
        $resultadoLoja['venda_liquida'] = $vendaLiquida - $devolucoes;
        $resultadoLoja['descontos'] = $resultadoLoja['venda_bruta'] - $resultadoLoja['venda_liquida'];
        $resultadoLoja['cmv'] = $cmv - $cmvDevolucoes;
        $resultadoLoja['lucro'] = $resultadoLoja['venda_liquida'] - $resultadoLoja['cmv'];
        $resultadoLoja['devolucoes'] = $devolucoes;
        $resultadoLoja['estornos'] = $estornosLiquido; 
        $resultadoLoja['atendimentos'] = $atendimentos;

        // APENAS PARA O PERÍODO 'DIA', buscar detalhes de classes e vendedores
        if ($periodo === 'dia') {
            // Lógica para buscar classes e vendedores (simplificada para performance)
            // ... (código das queries de classe e vendedor que já fizemos)
        }

    } catch (Exception $e) {
        $resultadoLoja['status'] = 'erro';
        $resultadoLoja['mensagem'] = $e->getMessage();
    }
    $resultadosFinais[] = $resultadoLoja;
}

echo json_encode($resultadosFinais);

?>