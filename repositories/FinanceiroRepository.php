<?php

final class FinanceiroRepository
{
    public function __construct(private PDO $pdo) {}

    /** Cache simples de existência de colunas (por instância). */
    private array $colCache = [];

    /**
     * Verifica se uma coluna existe na tabela do banco atual.
     * Evita quebrar em lojas/bases que não possuem campos novos (ex.: V_FCP_ST_INTERNO).
     */
    private function hasColumn(string $table, string $column): bool
    {
        $key = strtolower($table . '.' . $column);
        if (array_key_exists($key, $this->colCache)) {
            return (bool)$this->colCache[$key];
        }

        $sql = "
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :t
              AND COLUMN_NAME = :c
            LIMIT 1
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([':t' => $table, ':c' => $column]);
        $ok = (bool)$st->fetchColumn();
        $this->colCache[$key] = $ok;
        return $ok;
    }

    public function cmvVendas(string $inicio, string $fimExclusive, int $codloja): float
    {
        $sql = "
            -- No Myouro, vendaprodutos.CUSTOMEDIO já representa o custo TOTAL do item (linha),
            -- como já é usado no api_totais.php. Portanto, NÃO multiplicar por QUANTPROD.
            SELECT COALESCE(SUM(vp.CUSTOMEDIO),0) AS cmv
            FROM vendaprodutos vp
            INNER JOIN venda v ON v.IDVENDA = vp.IDVENDA
            WHERE v.STATUS = 'F'
              AND v.CODLOJA = :codloja
              AND v.DATAHORAVENDA >= :ini
              AND v.DATAHORAVENDA <  :fim
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([':codloja'=>$codloja, ':ini'=>$inicio, ':fim'=>$fimExclusive]);
        return (float)($st->fetchColumn() ?? 0);
    }

    public function cmvDevolucoes(string $inicio, string $fimExclusive, int $codloja): float
    {
        // No seu sistema, devolucao.CUSTOMEDIO já é total (vide api_totais.php)
        $sql = "
            SELECT COALESCE(SUM(d.CUSTOMEDIO),0) AS cmv
            FROM devolucao d
            WHERE d.CODLOJA = :codloja
              AND d.DATAHORADEVOLUC >= :ini
              AND d.DATAHORADEVOLUC <  :fim
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([':codloja'=>$codloja, ':ini'=>$inicio, ':fim'=>$fimExclusive]);
        return (float)($st->fetchColumn() ?? 0);
    }

    /**
     * CMV de vendas usando BETWEEN com datas (YYYY-MM-DD).
     *
     * Observação: isso replica o padrão antigo do projeto (api_totais.php),
     * onde o filtro é por data (sem hora). É útil para bater números com o
     * sistema de compras/relatórios quando há diferenças de DATETIME.
     */
    public function cmvVendasEntreDatas(string $dataIni, string $dataFim, int $codloja): float
    {
        // Replica exatamente o api_totais.php:
        // - Vendas finalizadas (v.STATUS='F')
        // - Filtro por vp.DATAHORAVENDA BETWEEN 'YYYY-MM-DD' AND 'YYYY-MM-DD' (datas, sem hora)
        // - CMV = SUM(vp.CUSTOMEDIO) (sem multiplicar por quantidade)
        $sql = "
            SELECT COALESCE(SUM(vp.CUSTOMEDIO),0) AS cmv
            FROM vendaprodutos vp
            INNER JOIN venda v ON v.IDVENDA = vp.IDVENDA
            WHERE vp.DATAHORAVENDA BETWEEN :ini AND :fim
              AND v.CODLOJA = :codloja
              AND v.STATUS  = 'F'
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([':codloja'=>$codloja, ':ini'=>$dataIni, ':fim'=>$dataFim]);
        return (float)($st->fetchColumn() ?? 0);
    }

    /** CMV de devoluções usando BETWEEN com datas (YYYY-MM-DD). */
    public function cmvDevolucoesEntreDatas(string $dataIni, string $dataFim, int $codloja): float
    {
        // Replica exatamente o api_totais.php:
        // - devolucao não tem STATUS (no schema enviado) e não precisa JOIN
        // - filtro por d.DATAHORADEVOLUC BETWEEN datas
        // - CMV devolução = SUM(d.CUSTOMEDIO)
        $sql = "
            SELECT COALESCE(SUM(d.CUSTOMEDIO),0) AS cmv
            FROM devolucao d
            WHERE d.DATAHORADEVOLUC BETWEEN :ini AND :fim
              AND d.CODLOJA = :codloja
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([':codloja'=>$codloja, ':ini'=>$dataIni, ':fim'=>$dataFim]);
        return (float)($st->fetchColumn() ?? 0);
    }

    /** Vendas líquidas (somatório de VALORPRODUTO) usando BETWEEN com datas. */
    public function vendasLiqVendasEntreDatas(string $dataIni, string $dataFim, int $codloja): float
    {
        $sql = "
            SELECT COALESCE(SUM(vp.VALORPRODUTO),0) AS vendas
            FROM vendaprodutos vp
            INNER JOIN venda v ON v.IDVENDA = vp.IDVENDA
            WHERE v.STATUS = 'F'
              AND v.CODLOJA = :codloja
              AND v.DATAHORAVENDA BETWEEN :ini AND :fim
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([':codloja'=>$codloja, ':ini'=>$dataIni, ':fim'=>$dataFim]);
        return (float)($st->fetchColumn() ?? 0);
    }

    /** Devoluções (somatório de VALORPRODUTO) usando BETWEEN com datas. */
    public function vendasLiqDevolucoesEntreDatas(string $dataIni, string $dataFim, int $codloja): float
    {
        $sql = "
            SELECT COALESCE(SUM(d.VALORPRODUTO),0) AS devol
            FROM devolucao d
            WHERE d.CODLOJA = :codloja
              AND d.DATAHORADEVOLUC BETWEEN :ini AND :fim
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([':codloja'=>$codloja, ':ini'=>$dataIni, ':fim'=>$dataFim]);
        return (float)($st->fetchColumn() ?? 0);
    }

    public function vendasLiqVendas(string $inicio, string $fimExclusive, int $codloja): float
    {
        $sql = "
            SELECT COALESCE(SUM(vp.VALORPRODUTO),0) AS vendas
            FROM vendaprodutos vp
            INNER JOIN venda v ON v.IDVENDA = vp.IDVENDA
            WHERE v.STATUS = 'F'
              AND v.CODLOJA = :codloja
              AND v.DATAHORAVENDA >= :ini
              AND v.DATAHORAVENDA <  :fim
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([':codloja'=>$codloja, ':ini'=>$inicio, ':fim'=>$fimExclusive]);
        return (float)($st->fetchColumn() ?? 0);
    }

    public function vendasLiqDevolucoes(string $inicio, string $fimExclusive, int $codloja): float
    {
        $sql = "
            SELECT COALESCE(SUM(d.VALORPRODUTO),0) AS devol
            FROM devolucao d
            WHERE d.CODLOJA = :codloja
              AND d.DATAHORADEVOLUC >= :ini
              AND d.DATAHORADEVOLUC <  :fim
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([':codloja'=>$codloja, ':ini'=>$inicio, ':fim'=>$fimExclusive]);
        return (float)($st->fetchColumn() ?? 0);
    }

    /**
     * Média de faturamento por dia da semana (últimos N meses encerrados até o fim do mês anterior ao mês selecionado)
     * Retorna array com chaves dom/seg/ter/qua/qui/sex/sab
     */
    public function mediaPorDOW(string $inicio, string $fimExclusive, int $codloja): array
    {
        // Observação: aqui usamos vendas do dia (VALORPRODUTO) sem abater devolução para manter leve.
        // Se você quiser 100% igual ao outro sistema, dá para refinar abatendo devolução por dia.
        $sql = "
            SELECT
              x.dow_mysql AS dow_mysql,
              AVG(x.dia_total) AS media_dia
            FROM (
              SELECT
                DATE(v.DATAHORAVENDA) AS dia,
                DAYOFWEEK(v.DATAHORAVENDA) AS dow_mysql,
                SUM(vp.VALORPRODUTO) AS dia_total
              FROM venda v
              INNER JOIN vendaprodutos vp ON vp.IDVENDA = v.IDVENDA
              WHERE v.STATUS='F'
                AND v.CODLOJA = :codloja
                AND v.DATAHORAVENDA >= :ini
                AND v.DATAHORAVENDA <  :fim
              GROUP BY DATE(v.DATAHORAVENDA), DAYOFWEEK(v.DATAHORAVENDA)
            ) x
            GROUP BY dow_mysql
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([':codloja'=>$codloja, ':ini'=>$inicio, ':fim'=>$fimExclusive]);

        $out = ['dom'=>0,'seg'=>0,'ter'=>0,'qua'=>0,'qui'=>0,'sex'=>0,'sab'=>0];
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $dow = (int)$r['dow_mysql']; // 1=Dom ... 7=Sáb
            $media = (float)$r['media_dia'];
            $key = match ($dow) {
                1 => 'dom',
                2 => 'seg',
                3 => 'ter',
                4 => 'qua',
                5 => 'qui',
                6 => 'sex',
                7 => 'sab',
                default => null,
            };
            if ($key) $out[$key] = $media;
        }
        return $out;
    }

    // ---------------- COMPRAS ----------------

    public function comprasTotal(string $inicio, string $fimExclusive, int $codloja): float
    {
        // Fórmula oficial (batendo com relatório "Compras por Classe"):
        // Bruto: SUM(qtd * valoritem)
        // Desconto: SUM(ABS(desconto_unit) * qtd)
        // Tributos: frete + despesas + IPI + ICMS ST + FCP ST
        $fcpExpr = $this->hasColumn('itens_compra', 'V_FCP_ST_INTERNO')
            ? 'COALESCE(i.V_FCP_ST_INTERNO,0)'
            : '0';

        $sql = "
            SELECT ROUND(
                COALESCE(SUM(i.QUANTITEMCOMPRA * i.VALORITEMCOMPRA),0)
              - COALESCE(SUM(ABS(COALESCE(i.DESCONTO,0)) * COALESCE(i.QUANTITEMCOMPRA,0)),0)
              + COALESCE(SUM(
                    COALESCE(i.VALORFRETE,0)
                  + COALESCE(i.VALORDESP,0)
                  + COALESCE(i.VALORIPI,0)
                  + COALESCE(i.VALORICMSSUBST,0)
                  + {$fcpExpr}
                ),0)
            ,2) AS total
            FROM itens_compra i
            INNER JOIN compras c ON c.CODCOMPRA = i.CODCOMPRA
            WHERE c.CODLOJA = :codloja
              AND c.STATUS = 'F'
              AND c.DATAEMISSAO >= :ini
              AND c.DATAEMISSAO <  :fim
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([':codloja'=>$codloja, ':ini'=>$inicio, ':fim'=>$fimExclusive]);
        return (float)($st->fetchColumn() ?? 0);
    }

    /**
     * Compras por classe (itens_compra -> produtos -> classes), usando VALORITEMCOMPRA.
     * Retorna array [nome_classe => valor]
     */
    public function comprasPorClasse(string $inicio, string $fimExclusive, int $codloja): array
    {
        // Mesma fórmula oficial, porém agrupada por classe.
        $fcpExpr = $this->hasColumn('itens_compra', 'V_FCP_ST_INTERNO')
            ? 'COALESCE(i.V_FCP_ST_INTERNO,0)'
            : '0';

        $sql = "
            SELECT
                COALESCE(cl.NOMECLASS,'Sem classe') AS nome_classe,
                ROUND(
                    COALESCE(SUM(i.QUANTITEMCOMPRA * i.VALORITEMCOMPRA),0)
                  - COALESCE(SUM(ABS(COALESCE(i.DESCONTO,0)) * COALESCE(i.QUANTITEMCOMPRA,0)),0)
                  + COALESCE(SUM(
                        COALESCE(i.VALORFRETE,0)
                      + COALESCE(i.VALORDESP,0)
                      + COALESCE(i.VALORIPI,0)
                      + COALESCE(i.VALORICMSSUBST,0)
                      + {$fcpExpr}
                    ),0)
                ,2) AS valor
            FROM itens_compra i
            INNER JOIN compras c ON c.CODCOMPRA = i.CODCOMPRA
            LEFT JOIN produtos p ON p.CODPROD = i.CODPROD
            LEFT JOIN classes cl ON cl.CODCLASS = p.CODCLASSE
            WHERE c.CODLOJA = :codloja
              AND c.STATUS = 'F'
              AND c.DATAEMISSAO >= :ini
              AND c.DATAEMISSAO <  :fim
            GROUP BY COALESCE(cl.NOMECLASS,'Sem classe')
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([':codloja'=>$codloja, ':ini'=>$inicio, ':fim'=>$fimExclusive]);

        $out = [];
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[$r['nome_classe']] = (float)$r['valor'];
        }
        return $out;
    }

    // ---------------- VENDAS POR CLASSE (para F1 e CMV base por categoria) ----------------

    /** CMV (custo) por classe no período (vendas finalizadas - devoluções) */
    public function cmvPorClasse(string $inicio, string $fimExclusive, int $codloja): array
    {
        $sqlV = "
            SELECT COALESCE(cl.NOMECLASS,'Sem classe') AS nome_classe,
                   COALESCE(SUM(vp.CUSTOMEDIO),0) AS cmv
            FROM vendaprodutos vp
            INNER JOIN venda v ON v.IDVENDA = vp.IDVENDA
            LEFT JOIN produtos p ON p.CODPROD = vp.CODPROD
            LEFT JOIN classes cl ON cl.CODCLASS = p.CODCLASSE
            WHERE v.STATUS='F'
              AND v.CODLOJA = :codloja
              AND v.DATAHORAVENDA >= :ini
              AND v.DATAHORAVENDA <  :fim
            GROUP BY COALESCE(cl.NOMECLASS,'Sem classe')
        ";
        $st = $this->pdo->prepare($sqlV);
        $st->execute([':codloja'=>$codloja, ':ini'=>$inicio, ':fim'=>$fimExclusive]);
        $v = [];
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $v[$r['nome_classe']] = (float)$r['cmv'];
        }

        $sqlD = "
            SELECT COALESCE(cl.NOMECLASS,'Sem classe') AS nome_classe,
                   COALESCE(SUM(d.CUSTOMEDIO),0) AS cmv
            FROM devolucao d
            LEFT JOIN produtos p ON p.CODPROD = d.CODPROD
            LEFT JOIN classes cl ON cl.CODCLASS = p.CODCLASSE
            WHERE d.CODLOJA = :codloja
              AND d.DATAHORADEVOLUC >= :ini
              AND d.DATAHORADEVOLUC <  :fim
            GROUP BY COALESCE(cl.NOMECLASS,'Sem classe')
        ";
        $st2 = $this->pdo->prepare($sqlD);
        $st2->execute([':codloja'=>$codloja, ':ini'=>$inicio, ':fim'=>$fimExclusive]);
        while ($r = $st2->fetch(PDO::FETCH_ASSOC)) {
            $k = $r['nome_classe'];
            $v[$k] = ($v[$k] ?? 0) - (float)$r['cmv'];
        }

        foreach ($v as $k => $val) {
            if ($val < 0) $v[$k] = 0.0;
        }
        return $v;
    }

    /** Vendas líquidas por classe (VALORPRODUTO - devolução) no período */
    public function vendasLiqPorClasse(string $inicio, string $fimExclusive, int $codloja): array
    {
        $sqlV = "
            SELECT COALESCE(cl.NOMECLASS,'Sem classe') AS nome_classe,
                   COALESCE(SUM(vp.VALORPRODUTO),0) AS valor
            FROM vendaprodutos vp
            INNER JOIN venda v ON v.IDVENDA = vp.IDVENDA
            LEFT JOIN produtos p ON p.CODPROD = vp.CODPROD
            LEFT JOIN classes cl ON cl.CODCLASS = p.CODCLASSE
            WHERE v.STATUS='F'
              AND v.CODLOJA = :codloja
              AND v.DATAHORAVENDA >= :ini
              AND v.DATAHORAVENDA <  :fim
            GROUP BY COALESCE(cl.NOMECLASS,'Sem classe')
        ";
        $st = $this->pdo->prepare($sqlV);
        $st->execute([':codloja'=>$codloja, ':ini'=>$inicio, ':fim'=>$fimExclusive]);
        $v = [];
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $v[$r['nome_classe']] = (float)$r['valor'];
        }

        $sqlD = "
            SELECT COALESCE(cl.NOMECLASS,'Sem classe') AS nome_classe,
                   COALESCE(SUM(d.VALORPRODUTO),0) AS valor
            FROM devolucao d
            LEFT JOIN produtos p ON p.CODPROD = d.CODPROD
            LEFT JOIN classes cl ON cl.CODCLASS = p.CODCLASSE
            WHERE d.CODLOJA = :codloja
              AND d.DATAHORADEVOLUC >= :ini
              AND d.DATAHORADEVOLUC <  :fim
            GROUP BY COALESCE(cl.NOMECLASS,'Sem classe')
        ";
        $st2 = $this->pdo->prepare($sqlD);
        $st2->execute([':codloja'=>$codloja, ':ini'=>$inicio, ':fim'=>$fimExclusive]);
        while ($r = $st2->fetch(PDO::FETCH_ASSOC)) {
            $k = $r['nome_classe'];
            $v[$k] = ($v[$k] ?? 0) - (float)$r['valor'];
        }

        foreach ($v as $k => $val) {
            if ($val < 0) $v[$k] = 0.0;
        }
        return $v;
    }

}
