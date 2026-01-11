<?php

final class OrcamentoService
{
    public function __construct(
        private FinanceiroRepository $repo,
        private OrcamentoConfigRepository $cfg,
    ) {}

    /**
     * Calcula a Dotação Orçamentária (limite mensal de compras) para uma loja.
     *
     * Retorno:
     * - cmv_base (mês anterior)
     * - f1_vendas (>=1 por padrão; só corrige após o dia 7 e apenas para o mês atual)
     * - f2_score (fator calendário)
     * - f3_ajuste (R$)
     * - dotacao (R$)
     * - percent_fator (F1 em %)
     */
    public function calcular(string $lojaId, int $codloja, string $mesRef): array
    {
        $mesAnterior = DateHelper::mesAnterior($mesRef);

        // Para bater com o legado SEM usar funções de data (DATE(), HOUR(), etc.),
        // usamos um intervalo com hora fixa:
        //   BETWEEN 'YYYY-MM-DD 00:00:00' AND 'YYYY-MM-DD 23:59:59'
        // Isso inclui o último dia inteiro sem degradar a performance.
        $iniSel = DateHelper::mesIni($mesRef)->format('Y-m-d') . ' 00:00:00';
        $fimSel = DateHelper::mesFim($mesRef)->format('Y-m-d') . ' 23:59:59';

        // ---------- CMV base (mês anterior) ----------
        // Regra: a dotação nasce do CMV do mês ANTERIOR, calculado exatamente como no api_totais.php.
        // Sem multiplicar por quantidade e sem filtros por hora.
        [$iniAntIncl, $fimAntIncl] = DateHelper::monthBoundsDates($mesAnterior);

        $cmvVendas = $this->repo->cmvVendasEntreDatas($iniAntIncl, $fimAntIncl, $codloja);
        $cmvDev    = $this->repo->cmvDevolucoesEntreDatas($iniAntIncl, $fimAntIncl, $codloja);
        $cmvBase   = max(0, $cmvVendas - $cmvDev);

        // ---------- F1 (vendas) ----------
        $f1 = 1.0;
        $percentFator = 100.0;

        $now = DateHelper::now();
        $mesAtual = $now->format('Y-m');

        if ($mesRef === $mesAtual) {
            $dia = (int)$now->format('j');
            if ($dia > 7) {
                $iniAtual = DateHelper::mesIni($mesRef)->format('Y-m-d');
                $agora    = $now->format('Y-m-d');

                $iniAntMesmo = DateHelper::mesIni($mesAnterior)->format('Y-m-d');
                // mesmo "dia do mês" no mês anterior (respeitando limites do mês)
                $fimAntMesmoDT = DateHelper::mesIni($mesAnterior)
                    ->modify('+' . ($dia - 1) . ' days')
                    ->modify('+1 day');
                $fimAntMesmo = $fimAntMesmoDT->format('Y-m-d');

                $vendasAtual = $this->repo->vendasLiqVendas($iniAtual, $agora, $codloja)
                    - $this->repo->vendasLiqDevolucoes($iniAtual, $agora, $codloja);

                $vendasAnt   = $this->repo->vendasLiqVendas($iniAntMesmo, $fimAntMesmo, $codloja)
                    - $this->repo->vendasLiqDevolucoes($iniAntMesmo, $fimAntMesmo, $codloja);

                if ($vendasAnt > 0) {
                    $f1 = max(0.0, $vendasAtual / $vendasAnt);
                    // limita para evitar explosão em casos extremos (ajustável)
                    $f1 = min(1.6, $f1);
                    $percentFator = $f1 * 100.0;
                }
            }
        }

        // ---------- F2 (score calendário) ----------
        $countsSel = DateHelper::contarDiasPorDOW($mesRef);
        $countsAnt = DateHelper::contarDiasPorDOW($mesAnterior);

        // histórico: últimos 3 meses completos anteriores ao mês selecionado
        $histIni = DateHelper::mesIni($mesRef)->modify('-3 months')->format('Y-m-d');
        $histFim = DateHelper::mesIni($mesRef)->format('Y-m-d');
        $medias  = $this->repo->mediaPorDOW($histIni, $histFim, $codloja);

        $scoreSel = 0.0;
        $scoreAnt = 0.0;
        foreach ($medias as $k => $media) {
            $scoreSel += ($countsSel[$k] ?? 0) * $media;
            $scoreAnt += ($countsAnt[$k] ?? 0) * $media;
        }
        $f2 = ($scoreAnt > 0) ? ($scoreSel / $scoreAnt) : 1.0;

        // ---------- F3 (ajuste manual) ----------
        $f3 = $this->cfg->getAjuste($lojaId, $mesRef);

        // ---------- Compras do mês (consumo do orçamento) ----------
        $iniSel = DateHelper::mesIni($mesRef)->format('Y-m-d');
        $fimSel = DateHelper::mesFimExclusive($mesRef)->format('Y-m-d');
        $comprasMes = $this->repo->comprasTotal($iniSel, $fimSel, $codloja);

        // ---------- Categorias (por classe) ----------
        $categorias = $this->calcularCategorias($codloja, $mesRef, $mesAnterior, $f2, $f3);

        // ---------- Dotação ----------
        $dotacao = ($cmvBase * $f1 * $f2) + $f3;
        if ($dotacao < 0) $dotacao = 0;

        $percentConsumido = ($dotacao > 0) ? (($comprasMes / $dotacao) * 100.0) : 0.0;

return [
            'loja_id'       => $lojaId,
            'loja_label'    => LojaConfig::label($lojaId),
            'mes'           => $mesRef,
            'cmv_base'      => round($cmvBase, 2),
            'f1_vendas'     => round($f1, 4),
            'f2_score'      => round($f2, 4),
            'f3_ajuste'     => round($f3, 2),
            'dotacao'       => round($dotacao, 2),
            'compras_mes'    => round($comprasMes, 2),
            'percent_consumido' => round($percentConsumido, 1),
            'percent_fator' => round($percentFator, 2),
            'categorias'    => $categorias,
        ];
    }


    /**
     * Monta tabela "Por Departamento" (classe) com:
     * - consumido (compras por classe via itens_compra)
     * - dotação por classe (CMV base por classe do mês anterior * fator por classe * fator calendário + rateio do F3)
     * - % fator (F1_classe * F2)
     * - % util (consumido / dotação)
     */
    private function calcularCategorias(int $codloja, string $mesRef, string $mesAnterior, float $f2, float $f3Total): array
    {
        // Usar somente a DATA (sem hora) para evitar filtros por hora.
        // O fim é EXCLUSIVO (primeiro dia do próximo mês).
        $iniAnt = DateHelper::mesIni($mesAnterior)->format('Y-m-d');
        $fimAnt = DateHelper::mesFimExclusive($mesAnterior)->format('Y-m-d');

        $iniSel = DateHelper::mesIni($mesRef)->format('Y-m-d');
        $fimSel = DateHelper::mesFimExclusive($mesRef)->format('Y-m-d');

        // Base por classe deve usar o mês ANTERIOR (mesAnterior), pois a dotação nasce do CMV do mês anterior
        $cmvBasePorClasse = $this->repo->cmvPorClasse($iniAnt, $fimAnt, $codloja);
        $consumidoPorClasse = $this->repo->comprasPorClasse($iniSel, $fimSel, $codloja);

        // F1 por classe (só após dia 7 e apenas mês atual)
        $now = DateHelper::now();
        $mesAtual = $now->format('Y-m');

        $f1PorClasse = [];
        if ($mesRef === $mesAtual && (int)$now->format('j') > 7) {
            $dia = (int)$now->format('j');
            $iniAtual = DateHelper::mesIni($mesRef)->format('Y-m-d');
            $agora    = $now->format('Y-m-d');

            $iniAntMesmo = DateHelper::mesIni($mesAnterior)->format('Y-m-d');
            $fimAntMesmoDT = DateHelper::mesIni($mesAnterior)
                ->modify('+' . ($dia - 1) . ' days')
                ->modify('+1 day');
            $fimAntMesmo = $fimAntMesmoDT->format('Y-m-d');

            $vAtual = $this->repo->vendasLiqPorClasse($iniAtual, $agora, $codloja);
            $vAnt   = $this->repo->vendasLiqPorClasse($iniAntMesmo, $fimAntMesmo, $codloja);

            $classes = array_unique(array_merge(array_keys($vAtual), array_keys($vAnt)));
            foreach ($classes as $cls) {
                $a = (float)($vAtual[$cls] ?? 0);
                $b = (float)($vAnt[$cls] ?? 0);
                $f1 = 1.0;
                if ($b > 0) {
                    $f1 = max(0.0, $a / $b);
                    $f1 = min(1.6, $f1);
                }
                $f1PorClasse[$cls] = $f1;
            }
        }

        // rateio do ajuste manual (F3) proporcional ao CMV base
        $sumBase = array_sum(array_map('floatval', $cmvBasePorClasse));
        $rateioF3 = [];
        if ($sumBase > 0 && $f3Total != 0.0) {
            foreach ($cmvBasePorClasse as $cls => $base) {
                $rateioF3[$cls] = ((float)$base / $sumBase) * $f3Total;
            }
        }

        $classesAll = array_unique(array_merge(
            array_keys($cmvBasePorClasse),
            array_keys($consumidoPorClasse),
            array_keys($f1PorClasse)
        ));

        $rows = [];
        foreach ($classesAll as $cls) {
            $base = (float)($cmvBasePorClasse[$cls] ?? 0);
            $cons = (float)($consumidoPorClasse[$cls] ?? 0);
            $f1c  = (float)($f1PorClasse[$cls] ?? 1.0);
            $f3c  = (float)($rateioF3[$cls] ?? 0.0);

            $dot = ($base * $f1c * $f2) + $f3c;
            if ($dot < 0) $dot = 0.0;

            $pctFator = ($f1c * $f2) * 100.0;
            $pctUtil  = ($dot > 0) ? (($cons / $dot) * 100.0) : 0.0;

            $rows[] = [
                'nome' => $cls,
                'consumido' => round($cons, 2),
                'dotacao' => round($dot, 2),
                'percent_fator' => round($pctFator, 1),
                'percent_util' => round($pctUtil, 1),
            ];
        }

        // ordena por consumido desc
        usort($rows, fn($a,$b) => ($b['consumido'] <=> $a['consumido']));
        return $rows;
    }
}
