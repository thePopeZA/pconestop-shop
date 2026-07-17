<?php
require_once __DIR__ . '/_bootstrap.php';
require_admin(); // any logged-in admin; if must_change is set they are sent here

$me = admin_user();
$forced = password_change_required();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $new     = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');
    if (strlen($new) < 8) {
        flash('Password must be at least 8 characters.', 'error');
    } elseif ($new !== $confirm) {
        flash('The two passwords do not match.', 'error');
    } else {
        db()->prepare('UPDATE admin_users SET password_hash = ?, must_change_password = 0 WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), (int)$me['id']]);
        $_SESSION['admin']['must_change'] = false;
        flash('Your password has been updated.', 'success');
        redirect('admin/index.php');
    }
    redirect('admin/change_password.php');
}

$adminTitle = 'Change password';
$adminNav = '';
include __DIR__ . '/_header.php';
?>
<div class="panel" style="max-width:460px">
    <h2 style="margin-top:0"><?= $forced ? 'Set your password' : 'Change password' ?></h2>
    <?php if ($forced): ?>
        <p class="muted" style="margin-top:0">Welcome, <strong><?= e($me['username']) ?></strong>. Please choose your own password before continuing — you're using a temporary one.</p>
    <?php else: ?>
        <p class="muted" style="margin-top:0">Update the password for <strong><?= e($me['username']) ?></strong>.</p>
    <?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="field">
            <label>New password</label>
            <input type="password" name="password" required minlength="8" autofocus placeholder="min 8 characters">
        </div>
        <div class="field">
            <label>Confirm new password</label>
            <input type="password" name="confirm" required minlength="8">
        </div>
        <button class="btn" type="submit">Save password</button>
    </form>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
