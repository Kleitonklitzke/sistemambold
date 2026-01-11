
<?php
error_reporting(0);

require_once __DIR__ . '/../core/LojaConfig.php';
require_once __DIR__ . '/../core/PdoLoader.php';

// Funções de busca adaptadas (anteriormente em funcoes.php)
// Nota: Elas agora recebem a conexão PDO ($pdo) como um argumento.

function buscavendas(PDO $pdo, $codloja, $datainicio, $datafim) {
    $status = "F";
    $sdev = $pdo->prepare("select vendaprodutos.CODPROD, (SUM(vendaprodutos.VALORBRUTO)), (SUM(vendaprodutos.VALORPRODUTO)), (SUM(vendaprodutos.VALORSUBSIDIO)), (SUM(vendaprodutos.CUSTOMEDIO)), (SUM(vendaprodutos.ULTCUSTO)), (SUM(vendaprodutos.QUANTPROD)), (SUM(vendaprodutos.ARREDOND)), (produtos.CODPROD), (produtos.CODCLASSE), (SUM(venda.VALORSUBSIDIO)), (MIN(venda.VALORTOTAL)), (MAX(venda.VALORTOTAL)), venda.STATUS, (COUNT(distinct venda.IDVENDA)), classes.CODCLASS, classes.NOMECLASS from vendaprodutos inner join produtos on vendaprodutos.CODPROD = produtos.CODPROD inner join venda on vendaprodutos.IDVENDA = venda.IDVENDA inner join classes on produtos.CODCLASSE = classes.CODCLASS WHERE (vendaprodutos.DATAHORAVENDA between ? and ?) AND (venda.CODLOJA = ?) AND (venda.STATUS = ?) GROUP BY (produtos.CODCLASSE)");
    $sdev->bindValue(1, $datainicio);
    $sdev->bindValue(2, $datafim);
    $sdev->bindValue(3, $codloja);
    $sdev->bindValue(4, $status);
    $sdev->execute();
    $vendas = [];
    while ($rowsdev = $sdev->fetch(PDO::FETCH_ASSOC)) {
        $vendas[] = [
            'codigo' => $rowsdev['CODCLASSE'],
            'nome' => $rowsdev['NOMECLASS'],
            'liquido' => $rowsdev['(SUM(vendaprodutos.VALORPRODUTO))'],
            'arredond' => $rowsdev['(SUM(vendaprodutos.ARREDOND))'],
            'bruto' => $rowsdev['(SUM(vendaprodutos.VALORBRUTO))'],
            'custo' => $rowsdev['(SUM(vendaprodutos.CUSTOMEDIO))'],
            'subsidio' => $rowsdev['(SUM(vendaprodutos.VALORSUBSIDIO))'],
            'maiorvenda' => $rowsdev['(MAX(venda.VALORTOTAL))'],
            'menorvenda' => $rowsdev['(MIN(venda.VALORTOTAL))']
        ];
    }
    return $vendas;
}

function busca_atendimentos(PDO $pdo, $codloja, $datainicio, $datafim) {
    $status = "F";
    $sdev = $pdo->prepare("select (COUNT(distinct venda.IDVENDA)) from venda WHERE (venda.DATAHORAVENDA between ? and ?) AND (venda.CODLOJA = ?) AND (venda.STATUS = ?) GROUP BY venda.CODIGOVENDEDORES");
    $sdev->bindValue(1, $datainicio);
    $sdev->bindValue(2, $datafim);
    $sdev->bindValue(3, $codloja);
    $sdev->bindValue(4, $status);
    $sdev->execute();
    $totaldeatendimentos = 0;
    while ($rowsdev = $sdev->fetch(PDO::FETCH_ASSOC)) {
        $totaldeatendimentos += $rowsdev['(COUNT(distinct venda.IDVENDA))'];
    }
    return $totaldeatendimentos;
}

function busca_devolucoes(PDO $pdo, $codloja, $datainicio, $datafim) {
    $sdev = $pdo->prepare("select (SUM(devolucao.VALORBRUTO)), (SUM(devolucao.VALORPRODUTO)), (SUM(devolucao.CUSTOMEDIO)), devolucao.CODLOJA, devolucao.CODPROD, produtos.CODPROD, produtos.CODCLASSE FROM devolucao INNER JOIN produtos on devolucao.CODPROD = produtos.CODPROD WHERE (devolucao.DATAHORADEVOLUC between ? and ?) AND (devolucao.CODLOJA = ?) group by produtos.CODCLASSE");
    $sdev->bindValue(1, $datainicio);
    $sdev->bindValue(2, $datafim);
    $sdev->bindValue(3, $codloja);
    $sdev->execute();
    $devolucoes = [];
    while ($rowsdev = $sdev->fetch(PDO::FETCH_ASSOC)) {
        $devolucoes[] = [
            'codigo' => $rowsdev['CODCLASSE'],
            'devolucbruto' => $rowsdev['(SUM(devolucao.VALORBRUTO))'],
            'devolucliquido' => $rowsdev['(SUM(devolucao.VALORPRODUTO))'],
            'custodevoluc' => $rowsdev['(SUM(devolucao.CUSTOMEDIO))']
        ];
    }
    return $devolucoes;
}

function buscaestornos(PDO $pdo, $codloja, $datainicio, $datafim) {
    $status = "C";
    $sdev = $pdo->prepare("select (SUM(vendaprodutos.VALORBRUTO)) from vendaprodutos inner join produtos on vendaprodutos.CODPROD = produtos.CODPROD inner join venda on vendaprodutos.IDVENDA = venda.IDVENDA inner join classes on produtos.CODCLASSE = classes.CODCLASS WHERE (vendaprodutos.DATAHORAVENDA between ? and ?) AND (venda.CODLOJA = ?) AND (venda.STATUS = ?) GROUP BY produtos.CODCLASSE");
    $sdev->bindValue(1, $datainicio);
    $sdev->bindValue(2, $datafim);
    $sdev->bindValue(3, $codloja);
    $sdev->bindValue(4, $status);
    $sdev->execute();
    $total = 0;
    while($row = $sdev->fetch(PDO::FETCH_ASSOC)) {
        $total += $row['(SUM(vendaprodutos.VALORBRUTO))'];
    }
    return $total;
}

// --- Lógica Principal ---

$lojaId = filter_input(INPUT_GET, 'loja', FILTER_SANITIZE_STRING);
if (!$lojaId || !LojaConfig::exists($lojaId)) {
    http_response_code(404);
    echo json_encode(["error" => "Loja não encontrada."]);
    exit;
}

$codloja = LojaConfig::codloja($lojaId);
$metas = LojaConfig::metas($lojaId);

// Define o período (data)
$datainicio = date('Y-m-01 00:00:00');
$datafim = date('Y-m-t 23:59:59');
if (isset($_GET['data'])) {
    $dataUrl = filter_input(INPUT_GET, 'data', FILTER_SANITIZE_STRING);
    $datainicio = date('Y-m-01 00:00:00', strtotime($dataUrl));
    $datafim = date('Y-m-t 23:59:59', strtotime($dataUrl));
}

try {
    $pdo = PdoLoader::fromLojaId($lojaId);

    $vendas = buscavendas($pdo, $codloja, $datainicio, $datafim);
    $atendimentos = busca_atendimentos($pdo, $codloja, $datainicio, $datafim);
    $devolucoes = busca_devolucoes($pdo, $codloja, $datainicio, $datafim);
    $estornos = buscaestornos($pdo, $codloja, $datainicio, $datafim);

    // Cálculos de totais
    $vendabrutatotal = 0;
    $vendaliquidatotal = 0;
    $descontototal = 0;
    $customediovendas = 0;
    $devolucaoliquida = 0;
    $devolucaobruta = 0;
    $customediodevolucoes = 0;

    foreach ($vendas as $venda) {
        $vendabrutatotal += $venda['bruto'];
        $vendaliquidatotal += $venda['liquido'];
        $customediovendas += $venda['custo'];
    }
    $descontototal = $vendabrutatotal - $vendaliquidatotal;
    
    if(!empty($devolucoes)){
        foreach ($devolucoes as $devolucao) {
            $devolucaoliquida += $devolucao['devolucliquido'];
            $customediodevolucoes += $devolucao['custodevoluc'];
            $devolucaobruta += $devolucao['devolucbruto'];
        }
    }

    $cmv = $customediovendas - $customediodevolucoes;
    $vendareal = $vendaliquidatotal - $devolucaoliquida;
    $lucro = $vendareal - $cmv;
    $percentualdesconto = ($descontototal / $vendabrutatotal) * 100;
    $percentuallucro = ($lucro / $vendareal) * 100;
    $ticketmedio = $vendareal / $atendimentos;

    // Monta o JSON de resposta
    $json_data = [
        "loja" => $lojaId,
        "venda_bruta" => round($vendabrutatotal, 2),
        "venda_liquida" => round($vendaliquidatotal, 2),
        "descontos" => round($descontototal, 2),
        "devolucao_bruta" => round($devolucaobruta, 2),
        "devolucao_liquida" => round($devolucaoliquida, 2),
        "venda_real" => round($vendareal, 2),
        "atendimentos" => $atendimentos,
        "estornos" => round($estornos, 2),
        "cmv" => round($cmv, 2),
        "lucro" => round($lucro, 2),
        "percentual_desconto" => round($percentualdesconto, 2),
        "percentual_lucro" => round($percentuallucro, 2),
        "ticket_medio" => round($ticketmedio, 2),
        "meta_desconto" => $metas['desconto'],
        "meta_lucro" => $metas['lucro'],
        "meta_ticket_medio" => $metas['ticketMedio'],
    ];

} catch (Exception $e) {
    http_response_code(500);
    $json_data = ["error" => "Erro interno do servidor.", "message" => $e->getMessage()];
}

header("Content-type: application/json; charset=utf-8");
echo json_encode($json_data);

