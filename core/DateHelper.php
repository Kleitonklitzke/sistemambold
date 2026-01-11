<?php

final class DateHelper
{
    /**
     * Intervalo do mês em datas (sem hora), no formato YYYY-MM-DD.
     *
     * Mantemos isso para bater com a lógica que já existia no projeto (api_totais.php),
     * evitando divergências por causa de DATETIME e comparações com horas.
     *
     * @return array{0:string,1:string} [data_inicio, data_fim]
     */
    public static function monthBoundsDates(string $mesRef): array
    {
        // mesRef: YYYY-MM
        $dt = DateTimeImmutable::createFromFormat('Y-m', $mesRef);
        if (!$dt) {
            // fallback seguro
            $dt = new DateTimeImmutable('first day of this month');
        }

        $ini = $dt->modify('first day of this month')->format('Y-m-01');
        $fim = $dt->modify('last day of this month')->format('Y-m-t');
        return [$ini, $fim];
    }

    /**
     * Intervalo do dia em datas (sem hora), no formato YYYY-MM-DD.
     *
     * @return array{0:string,1:string} [data_inicio, data_fim] (iguais)
     */
    public static function dayBoundsDates(string $data): array
    {
        // data: YYYY-MM-DD
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $data);
        if (!$dt) {
            $dt = new DateTimeImmutable('today');
        }
        $d = $dt->format('Y-m-d');
        return [$d, $d];
    }
    public static function mesIni(string $ym): DateTimeImmutable
    {
        return new DateTimeImmutable($ym . '-01 00:00:00');
    }

    public static function mesFimExclusive(string $ym): DateTimeImmutable
    {
        $ini = self::mesIni($ym);
        return $ini->modify('first day of next month');
    }

    /**
     * Último dia do mês (inclusivo).
     *
     * Retorna um DateTimeImmutable com horário 00:00:00.
     * Isso evita filtros com horas (que no passado deixaram o sistema mais lento)
     * e combina com comparações usando DATE (YYYY-MM-DD).
     */
    public static function mesFim(string $ym): DateTimeImmutable
    {
        return self::mesFimExclusive($ym)->modify('-1 day');
    }

    public static function mesAnterior(string $ym): string
    {
        $ini = self::mesIni($ym);
        return $ini->modify('-1 month')->format('Y-m');
    }

    public static function contarDiasPorDOW(string $ym): array
    {
        $ini = self::mesIni($ym);
        $fim = self::mesFimExclusive($ym);
        $counts = [
            'dom' => 0, 'seg' => 0, 'ter' => 0, 'qua' => 0, 'qui' => 0, 'sex' => 0, 'sab' => 0,
        ];

        for ($d = $ini; $d < $fim; $d = $d->modify('+1 day')) {
            // PHP: 0=domingo ... 6=sábado
            $w = (int)$d->format('w');
            $key = match ($w) {
                0 => 'dom',
                1 => 'seg',
                2 => 'ter',
                3 => 'qua',
                4 => 'qui',
                5 => 'sex',
                6 => 'sab',
            };
            $counts[$key]++;
        }
        return $counts;
    }

    /** Retorna "agora" no timezone do servidor */
    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now');
    }
}
