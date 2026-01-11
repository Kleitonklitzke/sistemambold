<?php

final class OrcamentoConfigRepository
{
    private string $file;

    public function __construct(?string $filePath = null)
    {
        $this->file = $filePath ?: (__DIR__ . '/../storage/orcamento_ajustes.json');
        if (!is_file($this->file)) {
            @file_put_contents($this->file, json_encode(new stdClass(), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
        }
    }

    public function getAjuste(string $lojaId, string $mesRef): float
    {
        $data = $this->readAll();
        return (float)($data[$lojaId][$mesRef] ?? 0);
    }

    public function setAjuste(string $lojaId, string $mesRef, float $valor): void
    {
        $data = $this->readAll();
        if (!isset($data[$lojaId])) $data[$lojaId] = [];
        $data[$lojaId][$mesRef] = $valor;
        file_put_contents($this->file, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    }

    private function readAll(): array
    {
        $raw = @file_get_contents($this->file);
        $json = json_decode($raw ?: '{}', true);
        return is_array($json) ? $json : [];
    }
}
