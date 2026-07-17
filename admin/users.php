<?php
require_once __DIR__ . '/_bootstrap.php';
require_admin(); // any logged-in admin; per-action permissions enforced below
ensure_admin_role_column();

$me = admin_user();
$myRole = $me['role'] ?? 'admin';

/** Load a target account by id. */
$loadUser = function (int $id): ?array {
    $s = db()->prepare('SELECT id, username, email, role FROM admin_users WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        // Partner may create any role; owner may create staff only; staff cannot create.
        $role = ($_POST['role'] ?? 'staff');
        $role = in_array($role, ['staff', 'admin', 'partner'], true) ? $role : 'staff';
        $allowed = $myRole === 'partner' ? ['staff', 'admin', 'partner']
                 : ($myRole === 'admin' ? ['staff'] : []);
        $u    = trim((string)($_POST['username'] ?? ''));
        $mail = trim((string)($_POST['email'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');
        if (!in_array($role, $allowed, true)) {
            flash('You are not allowed to create that role.', 'error');
        } elseif (strlen($u) < 3 || strlen($pass) < 8) {
            flash('Username needs 3+ characters and password 8+.', 'error');
        } else {
            $exists = db()->prepare('SELECT 1 FROM admin_users WHERE username = ?');
            $exists->execute([$u]);
            if ($exists->fetch()) {
                flash('That username already exists.', 'error');
            } else {
                // New logins get a temporary password and must set their own on first login.
                admin_create($u, $pass, $mail ?: null, $role, true);
                flash('Created login “' . $u . '” (' . role_label($role) . '). They will set their own password on first sign-in.', 'success');
            }
        }
        redirect('admin/users.php');
    }

    if ($action === 'set_password') {
        $target = $loadUser((int)($_POST['id'] ?? 0));
        $pass = (string)($_POST['password'] ?? '');
        if (!$target || !can_manage_account($me, $target)) {
            flash('You are not allowed to change that password.', 'error');
        } elseif (strlen($pass) < 8) {
            flash('Password needs 8+ characters.', 'error');
        } else {
            // Setting your OWN password clears the flag; resetting someone else's
            // forces them to choose a new one on their next login.
            $forceChange = (int)$target['id'] === (int)$me['id'] ? 0 : 1;
            db()->prepare('UPDATE admin_users SET password_hash = ?, must_change_password = ? WHERE id = ?')
                ->execute([password_hash($pass, PASSWORD_DEFAULT), $forceChange, (int)$target['id']]);
            if ($forceChange === 0) {
                $_SESSION['admin']['must_change'] = false;
            }
            flash($forceChange
                ? 'Temporary password set for “' . $target['username'] . '” — they will choose their own on next login.'
                : 'Your password has been updated.', 'success');
        }
        redirect('admin/users.php');
    }

    if ($action === 'set_role') {
        // Only a partner may change roles at all.
        $target = $loadUser((int)($_POST['id'] ?? 0));
        $role = ($_POST['role'] ?? 'staff');
        $role = in_array($role, ['staff', 'admin', 'partner'], true) ? $role : 'staff';
        if ($myRole !== 'partner') {
            flash('Only the partner can change roles.', 'error');
        } elseif (!$target) {
            flash('Account not found.', 'error');
        } elseif ((int)$target['id'] === (int)$me['id'] && $role !== 'partner') {
            flash('You cannot remove partner access from your own account.', 'error');
        } else {
            db()->prepare('UPDATE admin_users SET role = ? WHERE id = ?')->execute([$role, (int)$target['id']]);
            flash('Role for “' . $target['username'] . '” set to ' . role_label($role) . '.', 'success');
        }
        redirect('admin/users.php');
    }

    if ($action === 'delete') {
        $target = $loadUser((int)($_POST['id'] ?? 0));
        if (!$target) {
            flash('Account not found.', 'error');
        } elseif ((int)$target['id'] === (int)$me['id']) {
            flash('You cannot delete the account you are signed in with.', 'error');
        } elseif (!can_manage_account($me, $target)) {
            flash('You are not allowed to delete that account.', 'error');
        } else {
            db()->prepare('DELETE FROM admin_users WHERE id = ?')->execute([(int)$target['id']]);
            flash('Login “' . $target['username'] . '” removed.', 'info');
        }
        redirect('admin/users.php');
    }
}

/* Rows this actor may see: partner → all; owner → staff + self; staff → self only. */
$all = db()->query('SELECT id, username, email, role, last_login FROM admin_users ORDER BY FIELD(role,"partner","admin","staff"), username')->fetchAll();
$users = array_values(array_filter($all, function ($u) use ($me, $myRole) {
    if ($myRole === 'partner') return true;
    if ((int)$u['id'] === (int)$me['id']) return true;
    if ($myRole === 'admin' && $u['role'] === 'staff') return true;
    return false;
}));

$canCreate = in_array($myRole, ['partner', 'admin'], true);
$createRoles = $myRole === 'partner' ? ['staff', 'admin', 'partner'] : ['staff'];

$adminTitle = 'Admin users';
$adminNav = 'users';
include __DIR__ . '/_header.php';
?>
<div class="panel" style="margin-bottom:20px">
    <h2 style="margin-top:0">Admin logins</h2>
    <p class="muted" style="margin-top:0;font-size:.88rem">
        <strong>Partner</strong> = build owner: full control, sees commission &amp; profit.
        <strong>Owner</strong> = shop owner: manages staff, no commission access.
        <strong>Staff</strong> = shop worker.
        <?php if ($myRole !== 'partner'): ?><br>Only the partner can change roles or manage the partner account.<?php endif; ?>
    </p>
    <table class="grid">
        <thead><tr><th>Username</th><th>Email</th><th>Role</th><th>Last login</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u):
            $self = (int)$u['id'] === (int)$me['id'];
            $canManage = can_manage_account($me, $u); ?>
            <tr>
                <td><strong><?= e($u['username']) ?></strong><?= $self ? ' <span class="muted">(you)</span>' : '' ?></td>
                <td class="muted"><?= e($u['email'] ?? '—') ?></td>
                <td>
                    <?php if ($myRole === 'partner' && !$self): ?>
                        <form method="post" style="margin:0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="set_role">
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <select name="role" onchange="this.form.submit()">
                                <?php foreach (['staff','admin','partner'] as $r): ?>
                                    <option value="<?= $r ?>" <?= $u['role']===$r?'selected':'' ?>><?= role_label($r) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    <?php else: ?>
                        <span class="badge b-grey"><?= e(role_label($u['role'])) ?></span>
                    <?php endif; ?>
                </td>
                <td class="muted" style="font-size:.82rem"><?= $u['last_login'] ? e(date('d M Y H:i', strtotime($u['last_login']))) : 'never' ?></td>
                <td style="display:flex;gap:6px;flex-wrap:wrap">
                    <?php if ($canManage): ?>
                    <details>
                        <summary class="btn btn-sm btn-ghost" style="cursor:pointer"><?= $self ? 'Change my password' : 'Set password' ?></summary>
                        <form method="post" style="display:flex;gap:6px;margin-top:6px">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="set_password">
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <input type="password" name="password" placeholder="New password" required minlength="8" style="padding:.4em">
                            <button class="btn btn-sm" type="submit">Save</button>
                        </form>
                    </details>
                    <?php endif; ?>
                    <?php if ($canManage && !$self): ?>
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

<?php if ($canCreate): ?>
<div class="panel" style="max-width:520px">
    <h2 style="margin-top:0">Add a login</h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="field"><label>Username</label><input name="username" required minlength="3"></div>
        <div class="field"><label>Email (optional)</label><input name="email" type="email"></div>
        <div class="field"><label>Password</label><input name="password" type="password" required minlength="8"></div>
        <?php if (count($createRoles) > 1): ?>
        <div class="field">
            <label>Role</label>
            <select name="role">
                <option value="staff">Staff — shop worker</option>
                <option value="admin">Owner — shop owner (no commission)</option>
                <option value="partner">Partner — build owner (sees commission)</option>
            </select>
        </div>
        <?php else: ?>
            <input type="hidden" name="role" value="staff">
            <p class="muted" style="font-size:.85rem">New accounts you create are <strong>Staff</strong>.</p>
        <?php endif; ?>
        <button class="btn" type="submit">Create login</button>
    </form>
</div>
<?php endif; ?>
<?php include __DIR__ . '/_footer.php'; ?>
