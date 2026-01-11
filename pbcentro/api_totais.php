<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require __DIR__ . '/conexaopdo.php';

/*
  AJUSTE AQUI PARA CADA LOJA
*/
$codloja  = 10;             // <--- altere conforme a loja
$nomeLoja = 'pimenta';     // <--- altere conforme a loja

$statusFinalizada = 'F';
$statusEstorno    = 'C';

/*
  Aceita:
  - ?data=2025-11-07
  - ?mes=2025-11
  - nada -> usa o dia de hoje
*/
$hoje      = date('Y-m-d');
$usaMes    = false;
$diaInicio = $hoje;
$diaFim    = $hoje;

if (isset($_GET['data']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data'])) {
    $diaInicio = $_GET['data'];
    $diaFim    = $_GET['data'];
} elseif (isset($_GET['mes']) && preg_match('/^\d{4}-\d{2}$/', $_GET['mes'])) {
    $usaMes    = true;
    $anoMes    = $_GET['mes'];
    $diaInicio = $anoMes . '-01';
    $diaFim    = date('Y-m-t', strtotime($diaInicio));
}

/* =======================================================
   FUNÇÃO PARA CONVERTER TEXTO COM SEGURANÇA
   ======================================================= */
function safe_text($val, $fallback = 'Sem nome') {
    if ($val === null || $val === '') {
        return $fallback;
    }
    $converted = @mb_convert_encoding($val, 'UTF-8', 'UTF-8, ISO-8859-1, ISO-8859-15, Windows-1252');
    return $converted !== false ? $converted : $fallback;
}

/* =======================================================
   1) VENDAS (itens) - SOMENTE STATUS = 'F'
   ======================================================= */
$sqlVendas = "
    SELECT
        SUM(vp.VALORBRUTO)   AS soma_valor_bruto,
        SUM(vp.VALORPRODUTO) AS soma_valor_produto,
        SUM(vp.CUSTOMEDIO)   AS soma_custo_medio
    FROM vendaprodutos vp
    INNER JOIN venda v ON vp.IDVENDA = v.IDVENDA
    WHERE vp.DATAHORAVENDA BETWEEN ? AND ?
      AND v.CODLOJA = ?
      AND v.STATUS  = ?
";
$stmtV = $con->prepare($sqlVendas);
$stmtV->execute([$diaInicio, $diaFim, $codloja, $statusFinalizada]);
$v = $stmtV->fetch(PDO::FETCH_ASSOC) ?: [];

$vendaBruta   = (float)($v['soma_valor_bruto']   ?? 0);
$vendaLiquida = (float)($v['soma_valor_produto'] ?? 0);
$cmv          = (float)($v['soma_custo_medio']   ?? 0);

/* =======================================================
   2) DEVOLUÇÕES - ABATE DOS TOTAIS
   ======================================================= */
$sqlDev = "
    SELECT
        SUM(d.VALORBRUTO)   AS devoluc_bruto,
        SUM(d.VALORPRODUTO) AS devoluc_liquido,
        SUM(d.CUSTOMEDIO)   AS custo_devoluc
    FROM devolucao d
    WHERE d.DATAHORADEVOLUC BETWEEN ? AND ?
      AND d.CODLOJA = ?
";
$stmtD = $con->prepare($sqlDev);
$stmtD->execute([$diaInicio, $diaFim, $codloja]);
$dev = $stmtD->fetch(PDO::FETCH_ASSOC) ?: [];

$devolucaoBruta   = (float)($dev['devoluc_bruto']   ?? 0);
$devolucaoLiquida = (float)($dev['devoluc_liquido'] ?? 0);
$custoDevolucao   = (float)($dev['custo_devoluc']   ?? 0);

/* devolução realmente reduz venda e cmv */
$vendaBruta   -= $devolucaoBruta;
$vendaLiquida -= $devolucaoLiquida;
$cmv          -= $custoDevolucao;

/* =======================================================
   3) ESTORNOS (STATUS = 'C') - APENAS INFORMATIVO
   ======================================================= */
$sqlEst = "
    SELECT
        SUM(vp.VALORBRUTO)   AS est_bruto,
        SUM(vp.VALORPRODUTO) AS est_liquido,
        SUM(vp.CUSTOMEDIO)   AS est_custo
    FROM vendaprodutos vp
    INNER JOIN venda v ON vp.IDVENDA = v.IDVENDA
    WHERE vp.DATAHORAVENDA BETWEEN ? AND ?
      AND v.CODLOJA = ?
      AND v.STATUS  = ?
";
$stmtE = $con->prepare($sqlEst);
$stmtE->execute([$diaInicio, $diaFim, $codloja, $statusEstorno]);
$est = $stmtE->fetch(PDO::FETCH_ASSOC) ?: [];

$estornoBruto   = (float)($est['est_bruto']   ?? 0);
$estornoLiquido = (float)($est['est_liquido'] ?? 0);
$estornoCusto   = (float)($est['est_custo']   ?? 0);

/* NÃO subtrair de novo dos totais, pois STATUS = 'C' não entrou nas vendas */

/* =======================================================
   4) ATENDIMENTOS
   ======================================================= */
$sqlAt = "
    SELECT COUNT(DISTINCT v.IDVENDA) AS atendimentos
    FROM venda v
    WHERE v.DATAHORAVENDA BETWEEN ? AND ?
      AND v.CODLOJA = ?
      AND v.STATUS  = ?
";
$stmtA = $con->prepare($sqlAt);
$stmtA->execute([$diaInicio, $diaFim, $codloja, $statusFinalizada]);
$atendimentos = (int)$stmtA->fetchColumn();

$descontos = $vendaBruta - $vendaLiquida;
$lucro     = $vendaLiquida - $cmv;

/* =======================================================
   5) CLASSES (com devolução abatida por classe)
   ======================================================= */

/* 5.1) Vendas por classe (STATUS = 'F') */
$classesBase = [];
$sqlClasse = "
    SELECT
        c.CODCLASS                  AS cod_classe,
        c.NOMECLASS                 AS nome_classe,
        SUM(vp.VALORBRUTO)          AS venda_bruta,
        SUM(vp.VALORPRODUTO)        AS venda_liq,
        SUM(vp.CUSTOMEDIO)          AS custo,
        SUM(vp.QUANTPROD)           AS quant
    FROM vendaprodutos vp
    INNER JOIN produtos p ON vp.CODPROD = p.CODPROD
    INNER JOIN classes  c ON p.CODCLASSE = c.CODCLASS
    INNER JOIN venda    v ON vp.IDVENDA  = v.IDVENDA
    WHERE vp.DATAHORAVENDA BETWEEN ? AND ?
      AND v.CODLOJA = ?
      AND v.STATUS  = ?
    GROUP BY c.CODCLASS, c.NOMECLASS
    ORDER BY venda_liq DESC
";
$stmtC = $con->prepare($sqlClasse);
$stmtC->execute([$diaInicio, $diaFim, $codloja, $statusFinalizada]);
while ($row = $stmtC->fetch(PDO::FETCH_ASSOC)) {
    $codClasse   = $row['cod_classe'];
    $nomeClasse  = safe_text($row['nome_classe'] ?? '', 'Sem classe');
    $vendaBrutaC = (float)($row['venda_bruta'] ?? 0);
    $vendaLiqC   = (float)($row['venda_liq']   ?? 0);
    $custoC      = (float)($row['custo']       ?? 0);
    $quantC      = (float)($row['quant']       ?? 0);

    $classesBase[$codClasse] = [
        'cod_classe'  => $codClasse,
        'nome_classe' => $nomeClasse,
        'venda_bruta' => $vendaBrutaC,
        'venda_liq'   => $vendaLiqC,
        'custo'       => $custoC,
        'quant'       => $quantC,
    ];
}

/* 5.2) Devoluções por classe (abatendo) */
$devClasses = [];
$sqlClasseDev = "
    SELECT
        c.CODCLASS                  AS cod_classe,
        SUM(d.VALORBRUTO)           AS dev_bruto,
        SUM(d.VALORPRODUTO)         AS dev_liq,
        SUM(d.CUSTOMEDIO)           AS dev_custo
        -- Se tiver quantidade na devolução, pode somar aqui também (ex.: SUM(d.QUANTPROD) AS dev_quant)
    FROM devolucao d
    INNER JOIN produtos p ON d.CODPROD = p.CODPROD
    INNER JOIN classes  c ON p.CODCLASSE = c.CODCLASS
    WHERE d.DATAHORADEVOLUC BETWEEN ? AND ?
      AND d.CODLOJA = ?
    GROUP BY c.CODCLASS
";
$stmtCD = $con->prepare($sqlClasseDev);
$stmtCD->execute([$diaInicio, $diaFim, $codloja]);
while ($row = $stmtCD->fetch(PDO::FETCH_ASSOC)) {
    $codClasse = $row['cod_classe'];
    $devClasses[$codClasse] = [
        'dev_bruto' => (float)($row['dev_bruto'] ?? 0),
        'dev_liq'   => (float)($row['dev_liq']   ?? 0),
        'dev_custo' => (float)($row['dev_custo'] ?? 0),
        // 'dev_quant' => (float)($row['dev_quant'] ?? 0), // se existir
    ];
}

/* 5.3) Monta array final de classes com devolução abatida */
$classes = [];
foreach ($classesBase as $codClasse => $cls) {
    $dev = $devClasses[$codClasse] ?? [
        'dev_bruto' => 0,
        'dev_liq'   => 0,
        'dev_custo' => 0,
        // 'dev_quant' => 0,
    ];

    $vendaBrutaC = $cls['venda_bruta'] - $dev['dev_bruto'];
    $vendaLiqC   = $cls['venda_liq']   - $dev['dev_liq'];
    $custoC      = $cls['custo']       - $dev['dev_custo'];
    $quantC      = $cls['quant']; // se quiser abater quantidade de devolução, subtrair aqui

    if ($vendaBrutaC < 0) $vendaBrutaC = 0;
    if ($vendaLiqC   < 0) $vendaLiqC   = 0;
    if ($custoC      < 0) $custoC      = 0;
    if ($quantC      < 0) $quantC      = 0;

    $lucroC     = $vendaLiqC - $custoC;
    $descontosC = $vendaBrutaC - $vendaLiqC;

    $classes[] = [
        'cod_classe'  => $codClasse,
        'nome_classe' => $cls['nome_classe'],
        'venda_bruta' => $vendaBrutaC,
        'venda_liq'   => $vendaLiqC,
        'descontos'   => $descontosC,
        'custo'       => $custoC,
        'lucro'       => $lucroC,
        'quant'       => $quantC,
    ];
}

/* =======================================================
   6) VENDEDORES (com abatimento de devolução)
   ======================================================= */
$vendedoresBase = [];
$sqlVend = "
    SELECT
        vp.CODVEND,
        vend.APELIDO,
        SUM(vp.VALORPRODUTO)      AS venda_liq,
        SUM(vp.CUSTOMEDIO)        AS custo,
        COUNT(DISTINCT v.IDVENDA) AS atend
    FROM vendaprodutos vp
    INNER JOIN venda v         ON vp.IDVENDA = v.IDVENDA
    INNER JOIN vendedores vend ON vp.CODVEND = vend.CODVEND
    WHERE vp.DATAHORAVENDA BETWEEN ? AND ?
      AND v.CODLOJA = ?
      AND v.STATUS  = ?
    GROUP BY vp.CODVEND, vend.APELIDO
    ORDER BY venda_liq DESC
";
$stmtVend = $con->prepare($sqlVend);
$stmtVend->execute([$diaInicio, $diaFim, $codloja, $statusFinalizada]);
while ($row = $stmtVend->fetch(PDO::FETCH_ASSOC)) {
    $codVend = $row['CODVEND'];
    $vendedoresBase[$codVend] = [
        'cod_vend'  => $codVend,
        'apelido'   => safe_text($row['APELIDO'] ?? '', 'Sem nome'),
        'venda_liq' => (float)$row['venda_liq'],
        'custo'     => (float)$row['custo'],
        'atend'     => (int)$row['atend'],
    ];
}

/* devoluções por vendedor */
$sqlDevVend = "
    SELECT
        d.CODVEND,
        SUM(d.VALORPRODUTO) AS devoluc_liq,
        SUM(d.CUSTOMEDIO)   AS devoluc_custo
    FROM devolucao d
    WHERE d.DATAHORADEVOLUC BETWEEN ? AND ?
      AND d.CODLOJA = ?
    GROUP BY d.CODVEND
";
$stmtDV = $con->prepare($sqlDevVend);
$stmtDV->execute([$diaInicio, $diaFim, $codloja]);
$devVend = [];
while ($row = $stmtDV->fetch(PDO::FETCH_ASSOC)) {
    $devVend[$row['CODVEND']] = [
        'devoluc_liq'   => (float)$row['devoluc_liq'],
        'devoluc_custo' => (float)$row['devoluc_custo'],
    ];
}

/* aplica devoluções e monta top 5 */
$vendedores = [];
foreach ($vendedoresBase as $codVend => $vend) {
    $devLiq   = $devVend[$codVend]['devoluc_liq']   ?? 0;
    $devCusto = $devVend[$codVend]['devoluc_custo'] ?? 0;

    $vendaFinal = $vend['venda_liq'] - $devLiq;
    $custoFinal = $vend['custo']     - $devCusto;
    if ($vendaFinal < 0) $vendaFinal = 0;
    if ($custoFinal < 0) $custoFinal = 0;

    $lucroV  = $vendaFinal - $custoFinal;
    $percV   = $vendaFinal > 0 ? ($lucroV / $vendaFinal * 100) : 0;
    $ticketV = $vend['atend'] > 0 ? ($vendaFinal / $vend['atend']) : 0;

    $vendedores[] = [
        'cod_vend'   => $codVend,
        'apelido'    => $vend['apelido'],
        'venda_liq'  => $vendaFinal,
        'custo'      => $custoFinal,
        'lucro'      => $lucroV,
        'perc_lucro' => $percV,
        'atend'      => $vend['atend'],
        'ticket'     => $ticketV,
    ];
}

usort($vendedores, fn($a, $b) => $b['venda_liq'] <=> $a['venda_liq']);
$vendedores = array_slice($vendedores, 0, 5);

/* =======================================================
   7) PREVISÃO DO MÊS
   ======================================================= */
$vendaMes    = null;
$previsaoMes = null;
if ($usaMes) {
    $vendaMes = $vendaLiquida;
    $diaHoje   = (int)date('d');
    $diasNoMes = (int)date('t', strtotime($diaInicio));
    $previsaoMes = $diaHoje > 0 ? ($vendaMes / $diaHoje) * $diasNoMes : $vendaMes;
}

/* =======================================================
   RESPOSTA
   ======================================================= */
$data = [
    'loja'          => $nomeLoja,
    'codloja'       => $codloja,
    'data_inicio'   => $diaInicio,
    'data_fim'      => $diaFim,
    'venda_bruta'   => $vendaBruta,
    'venda_liquida' => $vendaLiquida,
    'descontos'     => $descontos,
    'cmv'           => $cmv,
    'lucro'         => $lucro,
    'devolucoes'    => $devolucaoLiquida,
    'estornos'      => $estornoLiquido,
    'atendimentos'  => $atendimentos,
    'classes'       => $classes,
    'vendedores'    => $vendedores,
    'venda_mes'     => $vendaMes,
    'previsao_mes'  => $previsaoMes,
];

echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
