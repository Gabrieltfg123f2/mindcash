<?php
/**
 * MindCash — Conexão PDO Segura
 */

require_once __DIR__ . '/config.php';

// ── Credenciais (use variáveis de ambiente em produção) ───────
define('DB_HOST',    getenv('MC_DB_HOST')    ?: '127.0.0.1');
define('DB_PORT',    getenv('MC_DB_PORT')    ?: '3306');
define('DB_NAME',    getenv('MC_DB_NAME')    ?: 'mindcash');
define('DB_USER',    getenv('MC_DB_USER')    ?: 'root');
define('DB_PASS',    getenv('MC_DB_PASS')    ?: '');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,   // Prepared Statements reais
        PDO::ATTR_PERSISTENT         => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Nunca exponha detalhes em produção
        $msg = (ENV === 'development')
            ? 'Erro DB: ' . $e->getMessage()
            : 'Serviço temporariamente indisponível.';
        http_response_code(503);
        die(json_encode(['erro' => $msg]));
    }

    return $pdo;
}

/**
 * Atalho para executar uma query preparada e retornar o statement.
 */
function dbQuery(string $sql, array $params = []): PDOStatement {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
