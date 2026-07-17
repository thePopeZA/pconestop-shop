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

/** Ensure the admin_users.role column exists & is the 3-value enum (self-migrate). */
function ensure_admin_role_column(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    try {
        $col = db()->query("SHOW COLUMNS FROM admin_users LIKE 'role'")->fetch();
        if (!$col) {
            db()->exec("ALTER TABLE admin_users
                        ADD COLUMN role ENUM('staff','admin','partner') NOT NULL DEFAULT 'admin' AFTER password_hash");
        } elseif (stripos((string)($col['Type'] ?? ''), "'staff'") === false) {
            // Older 2-value enum — widen it to include 'staff'.
            db()->exec("ALTER TABLE admin_users
                        MODIFY COLUMN role ENUM('staff','admin','partner') NOT NULL DEFAULT 'admin'");
        }
    } catch (Throwable $e) {
        // table may not exist yet during first-run setup
    }
}

/** Rank for role hierarchy: staff < admin(owner) < partner(build owner). */
function role_rank(?string $role): int
{
    return ['staff' => 1, 'admin' => 2, 'partner' => 3][$role] ?? 0;
}

/** Human label for a role. */
function role_label(string $role): string
{
    return ['staff' => 'Staff', 'admin' => 'Owner', 'partner' => 'Partner'][$role] ?? ucfirst($role);
}

/**
 * May the acting admin modify (password/role/delete) the target account?
 * - Anyone may change their OWN password.
 * - Partner may manage everyone.
 * - Owner (admin) may manage staff only.
 * - Staff may manage no one but themselves.
 * Crucially: only a partner can ever touch a partner account.
 */
function can_manage_account(array $actor, array $target): bool
{
    if ((int)$actor['id'] === (int)$target['id']) {
        return true; // self
    }
    if (($actor['role'] ?? '') === 'partner') {
        return true;
    }
    if (($actor['role'] ?? '') === 'admin' && ($target['role'] ?? '') === 'staff') {
        return true;
    }
    return false;
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
