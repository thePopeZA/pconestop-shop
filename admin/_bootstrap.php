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

/** Current admin's role ('admin' or 'partner'), or null if not logged in. */
function admin_role(): ?string
{
    return $_SESSION['admin']['role'] ?? null;
}

/** Partner = build owner; the only role allowed to see commission & profit split. */
function is_partner(): bool
{
    return admin_role() === 'partner';
}

function require_admin(): void
{
    if (!is_admin_logged_in()) {
        redirect('admin/login.php');
    }
}

/** Guard partner-only pages (commission / profit split). */
function require_partner(): void
{
    require_admin();
    if (!is_partner()) {
        http_response_code(403);
        flash('That area is restricted to the account owner.', 'error');
        redirect('admin/index.php');
    }
}

/** Ensure the admin_users.role column exists (self-migrate older databases). */
function ensure_admin_role_column(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    try {
        $has = db()->query("SHOW COLUMNS FROM admin_users LIKE 'role'")->fetch();
        if (!$has) {
            db()->exec("ALTER TABLE admin_users
                        ADD COLUMN role ENUM('admin','partner') NOT NULL DEFAULT 'admin' AFTER password_hash");
        }
    } catch (Throwable $e) {
        // table may not exist yet during first-run setup
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
    ensure_admin_role_column();
    $stmt = db()->prepare('SELECT * FROM admin_users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $u = $stmt->fetch();
    if ($u && password_verify($password, $u['password_hash'])) {
        $session = ['id' => (int)$u['id'], 'username' => $u['username'], 'role' => $u['role'] ?? 'admin'];
        db()->prepare('UPDATE admin_users SET last_login = NOW() WHERE id = ?')->execute([(int)$u['id']]);
        session_regenerate_id(true);
        $_SESSION['admin'] = $session;
        return true;
    }
    return false;
}

function admin_create(string $username, string $password, ?string $email = null, string $role = 'admin'): int
{
    ensure_admin_role_column();
    $role = $role === 'partner' ? 'partner' : 'admin';
    $stmt = db()->prepare('INSERT INTO admin_users (username, email, password_hash, role) VALUES (?,?,?,?)');
    $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
    return (int)db()->lastInsertId();
}

/** Admin URL helper. */
function admin_url(string $path = ''): string
{
    return url('admin/' . ltrim($path, '/'));
}
