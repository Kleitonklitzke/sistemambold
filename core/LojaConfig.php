
<?php

final class LojaConfig
{
    /** @return array<string, array{path:string,label:string,codloja:int,db:array,metas:array}> */
    public static function all(): array
    {
        return [
            'sapezal'  => [
                'path' => 'sapezal',
                'label' => 'Sapezal',
                'codloja' => 8,
                'db' => [
                    'host' => '45.187.75.236',
                    'dbname' => 'Myouro',
                    'user' => 'root',
                    'password' => 'lad013109a'
                ],
                'metas' => [
                    'desconto' => 25,
                    'ticketMedio' => 25,
                    'vendaLiquida' => 120000,
                    'lucro' => 50
                ]
            ],
            'pbcentro' => [
                'path' => 'pbcentro',
                'label' => 'Pimenta',
                'codloja' => 10,
                'db' => [
                    'host' => 'mbpimenta.ddns.net',
                    'dbname' => 'Myouro',
                    'user' => 'root',
                    'password' => 'lad013113z'
                ],
                'metas' => [
                    'desconto' => 25,
                    'ticketMedio' => 25,
                    'vendaLiquida' => 120000,
                    'lucro' => 50
                ]
            ],
            'alvorada' => [
                'path' => 'alvorada',
                'label' => 'Alvorada',
                'codloja' => 1,
                'db' => [
                    'host' => '177.222.211.245',
                    'dbname' => 'Myouro',
                    'user' => 'root',
                    'password' => 'jab012257g'
                ],
                'metas' => [
                    'desconto' => 25,
                    'ticketMedio' => 25,
                    'vendaLiquida' => 120000,
                    'lucro' => 50
                ]
            ],
        ];
    }

    public static function get(string $id): ?array
    {
        return self::all()[$id] ?? null;
    }

    public static function exists(string $id): bool
    {
        return self::get($id) !== null;
    }

    public static function label(string $id): string
    {
        $loja = self::get($id);
        return $loja['label'] ?? $id;
    }

    public static function path(string $id): string
    {
        $loja = self::get($id);
        return $loja['path'] ?? $id;
    }

    public static function codloja(string $id): int
    {
        $loja = self::get($id);
        return (int)($loja['codloja'] ?? 0);
    }
    
    public static function db(string $id): ?array
    {
        $loja = self::get($id);
        return $loja['db'] ?? null;
    }

    public static function metas(string $id): ?array
    {
        $loja = self::get($id);
        return $loja['metas'] ?? null;
    }
}
