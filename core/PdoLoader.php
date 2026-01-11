<?php
require_once __DIR__ . '/LojaConfig.php';

final class PdoLoader
{
    /** @var array<string, PDO> */
    private static array $connections = [];

    public static function conecta(string $lojaId): PDO
    {
        if (isset(self::$connections[$lojaId])) {
            return self::$connections[$lojaId];
        }

        $dbConfig = LojaConfig::db($lojaId);

        if ($dbConfig === null) {
            throw new RuntimeException("Configuração de banco de dados não encontrada para a loja: {$lojaId}");
        }

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8',
            $dbConfig['host'],
            $dbConfig['dbname']
        );

        try {
            $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$connections[$lojaId] = $pdo;
            return $pdo;
        } catch (PDOException $e) {
            throw new RuntimeException("Falha ao conectar no banco de dados da loja {$lojaId}: " . $e->getMessage());
        }
    }
}
