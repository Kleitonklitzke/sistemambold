<?php
// Inclui a nova estrutura centralizada
require_once __DIR__ . '/core/LojaConfig.php';
require_once __DIR__ . '/core/PdoLoader.php';

// =========================
// CONFIGURAÇÃO DAS LOJAS
// =========================
// A função getLojas() agora vem do LojaConfig.php
$lojas = LojaConfig::all();

// =========================
// PERÍODO
// =========================
$datainicio = date('Y-m-d 00:00:00');
$datafim    = date('Y-m-d 23:59:59');
$status_venda   = 'F';
$status_estorno = 'C';

// =========================
// ARRAYS DE RESULTADO
// =========================
$resultados_loja = [];
$vendedores      = [];  // consolidado de vendedores
$classes         = [];  // consolidado de classes

// =========================
// LOOP NAS LOJAS
// =========================
foreach ($lojas as $nomeLoja => $cfg) {
    try {
        // A função PdoLoader::conecta() agora é usada para obter a conexão
        $pdo = PdoLoader::conecta($nomeLoja);

        // =========================
        // 1) VENDAS POR CLASSE (base pra tudo)
        // =========================
        $sqlVendas = "
            SELECT
                vp.CODPROD,
                SUM(vp.VALORBRUTO)     AS soma_valor_bruto,
                SUM(vp.VALORPRODUTO)   AS soma_valor_produto,
                SUM(vp.VALORSUBSIDIO)  AS soma_valor_subsidio,
                SUM(vp.CUSTOMEDIO)     AS soma_custo_medio,
                SUM(vp.QUANTPROD)      AS soma_quant,
                p.CODCLASSE            AS cod_classe,
                c.NOMECLASS            AS nome_classe
            FROM vendaprodutos vp
            INNER JOIN produtos p ON vp.CODPROD = p.CODPROD
            INNER JOIN venda v ON vp.IDVENDA = v.IDVENDA
            INNER JOIN classes c ON p.CODCLASSE = c.CODCLASS
            WHERE vp.DATAHORAVENDA BETWEEN ? AND ?
              AND v.CODLOJA = ?
              AND v.STATUS = ?
            GROUP BY p.CODCLASSE
        ";
        $stVendas = $pdo->prepare($sqlVendas);
        $stVendas->execute([$datainicio, $datafim, $cfg['codloja'], $status_venda]);
        $vendasPorClasse = $stVendas->fetchAll(PDO::FETCH_ASSOC);

        // DEVOLUÇÕES POR CLASSE
        $sqlDev = "
            SELECT
                p.CODCLASSE AS cod_classe,
                SUM(d.VALORBRUTO)   AS devoluc_bruto,
                SUM(d.VALORPRODUTO) AS devoluc_liquido,
                SUM(d.CUSTOMEDIO)   AS custo_devoluc
            FROM devolucao d
            INNER JOIN produtos p ON d.CODPROD = p.CODPROD
            WHERE d.DATAHORADEVOLUC BETWEEN ? AND ?
              AND d.CODLOJA = ?
            GROUP BY p.CODCLASSE
        ";
        $stDev = $pdo->prepare($sqlDev);
        $stDev->execute([$datainicio, $datafim, $cfg['codloja']]);
        $devPorClasse = [];
        foreach ($stDev as $row) {
            $devPorClasse[$row['cod_classe']] = $row;
        }

        // ESTORNOS POR CLASSE
        $sqlEst = "
            SELECT
                p.CODCLASSE AS cod_classe,
                SUM(vp.VALORBRUTO)    AS est_bruto,
                SUM(vp.VALORPRODUTO)  AS est_liquido,
                SUM(vp.CUSTOMEDIO)    AS est_custo
            FROM vendaprodutos vp
            INNER JOIN produtos p ON vp.CODPROD = p.CODPROD
            INNER JOIN venda v ON vp.IDVENDA = v.IDVENDA
            WHERE vp.DATAHORAVENDA BETWEEN ? AND ?
              AND v.CODLOJA = ?
              AND v.STATUS = ?
            GROUP BY p.CODCLASSE
        ";
        $stEst = $pdo->prepare($sqlEst);
        $stEst->execute([$datainicio, $datafim, $cfg['codloja'], $status_estorno]);
        $estPorClasse = [];
        foreach ($stEst as $row) {
            $estPorClasse[$row['cod_classe']] = $row;
        }

        // ATENDIMENTOS
        $sqlAt = "
            SELECT COUNT(DISTINCT v.IDVENDA) AS atendimentos
            FROM venda v
            WHERE v.DATAHORAVENDA BETWEEN ? AND ?
              AND v.CODLOJA = ?
              AND v.STATUS = ?
        ";
        $stAt = $pdo->prepare($sqlAt);
        $stAt->execute([$datainicio, $datafim, $cfg['codloja'], $status_venda]);
        $atendimentos = (int)$stAt->fetchColumn();

        // =========================
        // CALCULO DO TOTAL DA LOJA E MONTAGEM DO CONSOLIDADO DE CLASSES
        // =========================
        $vendaBrutaLoja   = 0;
        $vendaLiquidaLoja = 0;
        $custoLoja        = 0;

        foreach ($vendasPorClasse as $v) {
            $codClasse = $v['cod_classe'];
            $nomeClasse = $v['nome_classe'];
            $bruto     = (float)$v['soma_valor_bruto'];
            $liquido   = (float)$v['soma_valor_produto'];
            $custo     = (float)$v['soma_custo_medio'];
            $quant     = (float)$v['soma_quant'];

            if (isset($devPorClasse[$codClasse])) {
                $bruto   -= (float)$devPorClasse[$codClasse]['devoluc_bruto'];
                $liquido -= (float)$devPorClasse[$codClasse]['devoluc_liquido'];
                $custo   -= (float)$devPorClasse[$codClasse]['custo_devoluc'];
            }
            if (isset($estPorClasse[$codClasse])) {
                $bruto   -= (float)$estPorClasse[$codClasse]['est_bruto'];
                $liquido -= (float)$estPorClasse[$codClasse]['est_liquido'];
                $custo   -= (float)$estPorClasse[$codClasse]['est_custo'];
            }

            $vendaBrutaLoja   += $bruto;
            $vendaLiquidaLoja += $liquido;
            $custoLoja        += $custo;

            $chaveClasse = $codClasse . '|' . $nomeClasse;
            if (!isset($classes[$chaveClasse])) {
                $classes[$chaveClasse] = [
                    'cod_classe'  => $codClasse,
                    'nome_classe' => $nomeClasse,
                    'venda_bruta' => 0,
                    'venda_liq'   => 0,
                    'custo'       => 0,
                    'quant'       => 0,
                ];
            }
            $classes[$chaveClasse]['venda_bruta'] += $bruto;
            $classes[$chaveClasse]['venda_liq']   += $liquido;
            $classes[$chaveClasse]['custo']       += $custo;
            $classes[$chaveClasse]['quant']       += $quant;
        }

        $descontos = $vendaBrutaLoja - $vendaLiquidaLoja;
        $lucro     = $vendaLiquidaLoja - $custoLoja;

        $resultados_loja[$nomeLoja] = [
            'venda_bruta'   => $vendaBrutaLoja,
            'venda_liquida' => $vendaLiquidaLoja,
            'descontos'     => $descontos,
            'cmv'           => $custoLoja,
            'lucro'         => $lucro,
            'atendimentos'  => $atendimentos,
            'erro'          => null,
        ];

        // =========================
        // 2) VENDEDOR DESSA LOJA
        // =========================
        $sqlVendVendedor = "
            SELECT
                SUM(v.VALORTOTAL)         AS soma_venda,
                SUM(v.VALORBRUTO)         AS soma_bruto,
                v.CODVEND                 AS codvend,
                COUNT(DISTINCT v.IDVENDA) AS atendimentos,
                vd.APELIDO                AS apelido
            FROM venda v
            INNER JOIN vendedores vd ON v.CODVEND = vd.CODVEND
            WHERE v.DATAHORAVENDA BETWEEN ? AND ?
              AND v.CODLOJA = ?
              AND v.STATUS  = ?
            GROUP BY v.CODVEND, vd.APELIDO
        ";
        $stVendVendedor = $pdo->prepare($sqlVendVendedor);
        $stVendVendedor->execute([$datainicio, $datafim, $cfg['codloja'], $status_venda]);
        $vendasVendedor = $stVendVendedor->fetchAll(PDO::FETCH_ASSOC);

        $sqlDevVend = "
            SELECT
                d.CODVEND AS codvend,
                SUM(d.VALORPRODUTO) AS devoluc_vend
            FROM devolucao d
            WHERE d.DATAHORADEVOLUC BETWEEN ? AND ?
              AND d.CODLOJA = ?
            GROUP BY d.CODVEND
        ";
        $stDevVend = $pdo->prepare($sqlDevVend);
        $stDevVend->execute([$datainicio, $datafim, $cfg['codloja']]);
        $devVendedor = [];
        foreach ($stDevVend as $row) {
            $devVendedor[$row['codvend']] = (float)$row['devoluc_vend'];
        }

        foreach ($vendasVendedor as $v) {
            $apelido = $v['apelido'];
            $codvend = $v['codvend'];
            $venda   = (float)$v['soma_venda'];
            $atends  = (int)$v['atendimentos'];

            $valor_devolvido = isset($devVendedor[$codvend]) ? $devVendedor[$codvend] : 0;
            $venda_liq_vend  = $venda - $valor_devolvido;

            if (!isset($vendedores[$apelido])) {
                $vendedores[$apelido] = [
                    'apelido'      => $apelido,
                    'venda'        => 0,
                    'devolucao'    => 0,
                    'venda_liq'    => 0,
                    'atendimentos' => 0,
                    'lojas'        => [],
                ];
            }

            $vendedores[$apelido]['venda']        += $venda;
            $vendedores[$apelido]['devolucao']    += $valor_devolvido;
            $vendedores[$apelido]['venda_liq']    += $venda_liq_vend;
            $vendedores[$apelido]['atendimentos'] += $atends;
            $vendedores[$apelido]['lojas'][]       = $nomeLoja;
        }

    } catch (Exception $e) {
        $resultados_loja[$nomeLoja] = [
            'venda_bruta'   => 0,
            'venda_liquida' => 0,
            'descontos'     => 0,
            'cmv'           => 0,
            'lucro'         => 0,
            'atendimentos'  => 0,
            'erro'          => $e->getMessage(),
        ];
    }
}

// =========================
// SOMA TOTAL DAS LOJAS
// =========================
$total = [
    'venda_bruta'   => 0,
    'venda_liquida' => 0,
    'descontos'     => 0,
    'cmv'           => 0,
    'lucro'         => 0,
    'atendimentos'  => 0,
];
foreach ($resultados_loja as $r) {
    if (isset($r['erro']) && $r['erro'] !== null) continue;
    $total['venda_bruta']   += $r['venda_bruta'];
    $total['venda_liquida'] += $r['venda_liquida'];
    $total['descontos']     += $r['descontos'];
    $total['cmv']           += $r['cmv'];
    $total['lucro']         += $r['lucro'];
    $total['atendimentos']  += $r['atendimentos'];
}

// ordenar vendedores por venda líquida
usort($vendedores, function($a, $b) {
    return $b['venda_liq'] <=> $a['venda_liq'];
});

// ordenar classes por venda líquida também
usort($classes, function($a, $b) {
    return $b['venda_liq'] <=> $a['venda_liq'];
});
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Consolidado</title>
    <style>
        table { border-collapse: collapse; margin-bottom: 25px; }
        th, td { border:1px solid #ccc; padding:6px 10px; text-align:right; }
        th:first-child, td:first-child { text-align:left; }
        h2 { margin-top: 30px; }
        .error { color: red; font-size: 0.8em; }
    </style>
</head>
<body>
 <h2>Consolidado por loja (<?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($datainicio))) ?> até <?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($datafim))) ?>)</h2>
<table>
    <tr>
        <th>Loja</th>
        <th>Venda Bruta</th>
        <th>Venda Líquida</th>
        <th>Descontos</th>
        <th>CMV</th>
        <th>CMV %</th>
        <th>Lucro</th>
        <th>Lucro %</th>
        <th>Atendimentos</th>
        <th>Status</th>
    </tr>
    <?php foreach ($resultados_loja as $loja => $r): 
        $vendaLiq = $r['venda_liquida'];
        $custo    = $r['cmv'];
        $lucro    = $r['lucro'];

        $cmvPercent   = $vendaLiq > 0 ? ($custo / $vendaLiq) * 100 : 0;
        $lucroPercent = $vendaLiq > 0 ? ($lucro / $vendaLiq) * 100 : 0;
    ?>
    <tr>
        <td><?= htmlspecialchars($loja) ?></td>
        <td><?= number_format($r['venda_bruta'], 2, ',', '.') ?></td>
        <td><?= number_format($vendaLiq, 2, ',', '.') ?></td>
        <td><?= number_format($r['descontos'], 2, ',', '.') ?></td>
        <td><?= number_format($custo, 2, ',', '.') ?></td>
        <td><?= number_format($cmvPercent, 2, ',', '.') ?>%</td>
        <td><?= number_format($lucro, 2, ',', '.') ?></td>
        <td><?= number_format($lucroPercent, 2, ',', '.') ?>%</td>
        <td><?= (int)$r['atendimentos'] ?></td>
        <td class="error"><?= isset($r['erro']) ? htmlspecialchars($r['erro']) : 'OK' ?></td>
    </tr>
    <?php endforeach; ?>

    <?php
        $vendaLiqTot = $total['venda_liquida'];
        $custoTot    = $total['cmv'];
        $lucroTot    = $total['lucro'];

        $cmvPercentTot   = $vendaLiqTot > 0 ? ($custoTot / $vendaLiqTot) * 100 : 0;
        $lucroPercentTot = $vendaLiqTot > 0 ? ($lucroTot / $vendaLiqTot) * 100 : 0;
    ?>
    <tr style="font-weight:bold">
        <th>Total</th>
        <th><?= number_format($total['venda_bruta'], 2, ',', '.') ?></th>
        <th><?= number_format($vendaLiqTot, 2, ',', '.') ?></th>
        <th><?= number_format($total['descontos'], 2, ',', '.') ?></th>
        <th><?= number_format($custoTot, 2, ',', '.') ?></th>
        <th><?= number_format($cmvPercentTot, 2, ',', '.') ?>%</th>
        <th><?= number_format($lucroTot, 2, ',', '.') ?></th>
        <th><?= number_format($lucroPercentTot, 2, ',', '.') ?>%</th>
        <th><?= (int)$total['atendimentos'] ?></th>
        <th>-</th>
    </tr>
</table>


    <h2>Consolidado por vendedor (todas as lojas)</h2>
    <table>
        <tr>
            <th>Vendedor</th>
            <th>Venda Bruta</th>
            <th>Devoluções</th>
            <th>Venda Líquida</th>
            <th>Atendimentos</th>
            <th>Lojas</th>
        </tr>
        <?php foreach ($vendedores as $vend): ?>
        <tr>
            <td><?= htmlspecialchars($vend['apelido']) ?></td>
            <td><?= number_format($vend['venda'], 2, ',', '.') ?></td>
            <td><?= number_format($vend['devolucao'], 2, ',', '.') ?></td>
            <td><?= number_format($vend['venda_liq'], 2, ',', '.') ?></td>
            <td><?= (int)$vend['atendimentos'] ?></td>
            <td><?= htmlspecialchars(implode(', ', array_unique($vend['lojas']))) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

<h2>Consolidado por classe (todas as lojas)</h2>
<table>
    <tr>
        <th>Cód. Classe</th>
        <th>Classe</th>
        <th>Venda Bruta</th>
        <th>Venda Líquida</th>
        <th>CMV</th>
        <th>CMV %</th>
        <th>Lucro</th>
        <th>Lucro %</th>
        <th>Qtd.</th>
    </tr>
    <?php foreach ($classes as $cls):
        $vendaLiq    = $cls['venda_liq'];
        $custo       = $cls['custo'];
        $lucroClasse = $vendaLiq - $custo;

        $cmvPercent   = $vendaLiq > 0 ? ($custo / $vendaLiq) * 100 : 0;
        $lucroPercent = $vendaLiq > 0 ? ($lucroClasse / $vendaLiq) * 100 : 0;
    ?>
    <tr>
        <td><?= htmlspecialchars($cls['cod_classe']) ?></td>
        <td><?= htmlspecialchars($cls['nome_classe']) ?></td>
        <td><?= number_format($cls['venda_bruta'], 2, ',', '.') ?></td>
        <td><?= number_format($vendaLiq, 2, ',', '.') ?></td>
        <td><?= number_format($custo, 2, ',', '.') ?></td>
        <td><?= number_format($cmvPercent, 2, ',', '.') ?>%</td>
        <td><?= number_format($lucroClasse, 2, ',', '.') ?></td>
        <td><?= number_format($lucroPercent, 2, ',', '.') ?>%</td>
        <td><?= number_format($cls['quant'], 2, ',', '.') ?></td>
    </tr>
    <?php endforeach; ?>
</table>

</body>
</html>
