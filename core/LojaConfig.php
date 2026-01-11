<?php

final class LojaConfig
{
    /** @return array<string, array{path:string,label:string,codloja:int}> */
    public static function all(): array
    {
        return [
            'sapezal'  => ['path' => 'sapezal',  'label' => 'Sapezal',  'codloja' => 8],
            'pbcentro' => ['path' => 'pbcentro', 'label' => 'Pimenta',  'codloja' => 10],
            'alvorada' => ['path' => 'alvorada', 'label' => 'Alvorada', 'codloja' => 1],
        ];
    }

    public static function exists(string $id): bool
    {
        $all = self::all();
        return array_key_exists($id, $all);
    }

    public static function label(string $id): string
    {
        $all = self::all();
        return $all[$id]['label'] ?? $id;
    }

    public static function path(string $id): string
    {
        $all = self::all();
        return $all[$id]['path'] ?? $id;
    }

    public static function codloja(string $id): int
    {
        $all = self::all();
        return (int)($all[$id]['codloja'] ?? 0);
    }
}
