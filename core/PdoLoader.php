<?php

final class PdoLoader
{
    /**
     * Carrega a conexão PDO de uma loja a partir da configuração centralizada.
     *
     * @throws RuntimeException
     */
    public static function fromLojaId(string $lojaId): PDO
    {
        $dbConfig = LojaConfig::db($lojaId);

        if ($dbConfig === null) {
            throw new RuntimeException('Configuração de banco de dados não encontrada para a loja: ' . $lojaId);
        }

        $dsn = sprintf('mysql:host=%s;dbname=%s', $dbConfig['host'], $dbConfig['dbname']);

        try {
            $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password']);
        } catch (PDOException $e) {
            throw new RuntimeException('Não foi possível conectar com o servidor de ' . LojaConfig::label($lojaId), 0, $e);
        }

        // Configurações seguras
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }
}
