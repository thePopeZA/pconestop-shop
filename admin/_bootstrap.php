<?php
/**
 * Admin bootstrap: config, auth helpers, guard.
 * Pages that require login should call require_admin() after including this.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/shop.php';
require_once BASE_PATH . '/includes/orders.php';

function admin_user(): ?array
{
    return $_SESSION['admin'] ?? null;
}

function is_admin_logged_in(): bool
{
    return !empty($_SESSION['admin']);
}

function require_admin(): void
{
    if (!is_admin_logged_in()) {
        redirect('admin/login.php');
    }
}

function admin_count(): int
{
    try {
        return (int)db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function admin_login(string $username, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM admin_users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $u = $stmt->fetch();
    if ($u && password_verify($password, $u['password_hash'])) {
        $_SESSION['admin'] = ['id' => (int)$u['id'], 'username' => $u['username']];
        db()->prepare('UPDATE admin_users SET last_login = NOW() WHERE id = ?')->execute([(int)$u['id']]);
        session_regenerate_id(true);
        $_SESSION['admin'] = ['id' => (int)$u['id'], 'username' => $u['username']];
        return true;
    }
    return false;
}

function admin_create(string $username, string $password, ?string $email = null): int
{
    $stmt = db()->prepare('INSERT INTO admin_users (username, email, password_hash) VALUES (?,?,?)');
    $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
    return (int)db()->lastInsertId();
}

/** Admin URL helper. */
function admin_url(string $path = ''): string
{
    return url('admin/' . ltrim($path, '/'));
}
