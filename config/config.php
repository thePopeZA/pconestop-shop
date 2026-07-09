<?php
/**
 * PC One Stop Shop — bootstrap / configuration loader.
 * Loads .env, defines helpers, starts session, sets error handling.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

/* ---- Minimal .env parser ---- */
function load_env(string $path): void
{
    if (!is_readable($path)) {
        die('Missing .env file. Copy .env.example to .env and configure it.');
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        // Strip surrounding quotes
        if (strlen($val) >= 2 && ($val[0] === '"' || $val[0] === "'")) {
            $val = substr($val, 1, -1);
        }
        $_ENV[$key] = $val;
        putenv("$key=$val");
    }
}

load_env(BASE_PATH . '/.env');

/* ---- env() helper with typing ---- */
function env(string $key, $default = null)
{
    $val = $_ENV[$key] ?? getenv($key);
    if ($val === false || $val === null || $val === '') {
        return $default;
    }
    return match (strtolower((string)$val)) {
        'true'  => true,
        'false' => false,
        'null'  => null,
        default => $val,
    };
}

/* ---- App constants ---- */
define('APP_ENV',   env('APP_ENV', 'local'));
define('APP_NAME',  env('APP_NAME', 'PC One Stop Shop'));
define('APP_URL',   rtrim((string)env('APP_URL', ''), '/'));
define('APP_DEBUG', (bool)env('APP_DEBUG', false));

define('MARKUP_MULTIPLIER', (float)env('MARKUP_MULTIPLIER', 1.25));
define('VAT_MULTIPLIER',    (float)env('VAT_MULTIPLIER', 1.15));
define('VAT_RATE',          (float)env('VAT_RATE', 0.15));
define('SHIPPING_FLAT',     (float)env('SHIPPING_FLAT', 99.00));
define('SHIPPING_FREE_OVER',(float)env('SHIPPING_FREE_OVER', 1000.00));

define('YOCO_PUBLIC_KEY', (string)env('YOCO_PUBLIC_KEY', ''));
define('YOCO_SECRET_KEY', (string)env('YOCO_SECRET_KEY', ''));

/* ---- Error handling ---- */
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');
}

date_default_timezone_set('Africa/Johannesburg');

/* ---- Session ---- */
if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    session_start();
}

/* ---- Load core helpers & DB ---- */
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
