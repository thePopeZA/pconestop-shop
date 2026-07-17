<?php
require_once __DIR__ . '/_bootstrap.php';
require_partner(); // only the build owner manages logins & roles

ensure_admin_role_column();

$me = admin_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $u    = trim((string)($_POST['username'] ?? ''));
        $mail = trim((string)($_POST['email'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');
        $role = ($_POST['role'] ?? 'admin') === 'partner' ? 'partner' : 'admin';
        $exists = db()->prepare('SELECT 1 FROM admin_users WHERE username = ?');
        $exists->execute([$u]);
        if (strlen($u) < 3 || strlen($pass) < 8) {
            flash('Username needs 3+ characters and password 8+.', 'error');
        } elseif ($exists->fetch()) {
            flash('That username already exists.', 'error');
        } else {
            admin_create($u, $pass, $mail ?: null, $role);
            flash('Created login “' . $u . '” (' . $role . ').', 'success');
        }
        redirect('admin/users.php');
    }

    if ($action === 'set_password') {
        $id   = (int)($_POST['id'] ?? 0);
        $pass = (string)($_POST['password'] ?? '');
        if (strlen($pass) < 8) {
            flash('Password needs 8+ characters.', 'error');
        } else {
            db()->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
            flash('Password updated.', 'success');
        }
        redirect('admin/users.php');
    }

    if ($action === 'set_role') {
        $id   = (int)($_POST['id'] ?? 0);
        $role = ($_POST['role'] ?? 'admin') === 'partner' ? 'partner' : 'admin';
        if ($id === (int)$me['id'] && $role !== 'partner') {
            flash('You cannot remove partner access from your own account.', 'error');
        } else {
            db()->prepare('UPDATE admin_users SET role = ? WHERE id = ?')->execute([$role, $id]);
            flash('Role updated.', 'success');
        }
        redirect('admin/users.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$me['id']) {
            flash('You cannot delete the account you are signed in with.', 'error');
        } else {
            db()->prepare('DELETE FROM admin_users WHERE id = ?')->execute([$id]);
            flash('Login removed.', 'info');
        }
        redirect('admin/users.php');
    }
}

$users = db()->query('SELECT id, username, email, role, last_login FROM admin_users ORDER BY role DESC, username')->fetchAll();

$adminTitle = 'Admin users';
$adminNav = 'users';
include __DIR__ . '/_header.php';
?>
<div class="panel" style="margin-bottom:20px">
    <h2 style="margin-top:0">Admin logins</h2>
    <p class="muted" style="margin-top:0;font-size:.88rem">
        <strong>Partner</strong> = you (build owner): sees Profit split &amp; controls commission.
        <strong>Admin</strong> = shop staff/owner: everything except commission.
    </p>
    <table class="grid">
        <thead><tr><th>Username</th><th>Email</th><th>Role</th><th>Last login</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): $self = (int)$u['id'] === (int)$me['id']; ?>
            <tr>
                <td><strong><?= e($u['username']) ?></strong><?= $self ? ' <span class="muted">(you)</span>' : '' ?></td>
                <td class="muted"><?= e($u['email'] ?? '—') ?></td>
                <td>
                    <form method="post" style="display:flex;gap:6px;align-items:center">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="set_role">
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <select name="role" onchange="this.form.submit()" <?= $self ? 'disabled' : '' ?>>
                            <option value="admin"   <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
                            <option value="partner" <?= $u['role']==='partner'?'selected':'' ?>>Partner</option>
                        </select>
                    </form>
                </td>
                <td class="muted" style="font-size:.82rem"><?= $u['last_login'] ? e(date('d M Y H:i', strtotime($u['last_login']))) : 'never' ?></td>
                <td style="display:flex;gap:6px;flex-wrap:wrap">
                    <details>
                        <summary class="btn btn-sm btn-ghost" style="cursor:pointer">Set password</summary>
                        <form method="post" style="display:flex;gap:6px;margin-top:6px">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="set_password">
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <input type="password" name="password" placeholder="New password" required minlength="8" style="padding:.4em">
                            <button class="btn btn-sm" type="submit">Save</button>
                        </form>
                    </details>
                    <?php if (!$self): ?>
                    <form method="post" onsubmit="return confirm('Delete login “<?= e($u['username']) ?>”?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <button class="btn btn-sm btn-ghost" type="submit" style="color:var(--red,#c0392b)">Delete</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="panel" style="max-width:520px">
    <h2 style="margin-top:0">Add a login</h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="field"><label>Username</label><input name="username" required minlength="3"></div>
        <div class="field"><label>Email (optional)</label><input name="email" type="email"></div>
        <div class="field"><label>Password</label><input name="password" type="password" required minlength="8"></div>
        <div class="field">
            <label>Role</label>
            <select name="role">
                <option value="admin">Admin — shop staff/owner (no commission)</option>
                <option value="partner">Partner — build owner (sees commission)</option>
            </select>
        </div>
        <button class="btn" type="submit">Create login</button>
    </form>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
