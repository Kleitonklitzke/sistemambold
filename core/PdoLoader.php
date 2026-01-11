<?php

final class PdoLoader
{
    /**
     * Carrega a conexão PDO de uma loja a partir do arquivo <loja>/conexaopdo.php.
     *
     * @throws RuntimeException
     */
    public static function fromLojaId(string $lojaId): PDO
    {
        $path = LojaConfig::path($lojaId);
        $file = __DIR__ . '/../' . $path . '/conexaopdo.php';
        if (!is_file($file)) {
            throw new RuntimeException('Arquivo de conexão não encontrado: ' . $file);
        }

        // Isola o include para não vazar variáveis para o escopo global.
        $pdo = (static function (string $filePath) {
            $con = null;
            require $filePath; // deve definir $con (PDO)
            if (!$con instanceof PDO) {
                throw new RuntimeException('conexaopdo.php não retornou um PDO válido.');
            }
            return $con;
        })($file);

        // Configurações seguras
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    }
}
