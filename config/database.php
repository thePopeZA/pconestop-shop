<?php
/**
 * PDO database connection (singleton).
 */

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host    = env('DB_HOST', 'localhost');
    $name    = env('DB_NAME', 'pconestop');
    $user    = env('DB_USER', 'root');
    $pass    = (string)($_ENV['DB_PASS'] ?? '');
    $charset = env('DB_CHARSET', 'utf8mb4');

    $dsn = "mysql:host=$host;dbname=$name;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        if (APP_DEBUG) {
            die('DB connection failed: ' . $e->getMessage());
        }
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        die('A database error occurred. Please try again later.');
    }

    return $pdo;
}
