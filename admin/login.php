<?php
require_once __DIR__ . '/_bootstrap.php';

if (is_admin_logged_in()) {
    redirect('admin/index.php');
}

$needsSetup = admin_count() === 0;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = 'Session expired, please try again.';
    } elseif ($needsSetup) {
        // First-time admin creation, gated by ADMIN_SETUP_KEY.
        $key  = (string)($_POST['setup_key'] ?? '');
        $user = trim((string)($_POST['username'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');
        if ($key !== (string)env('ADMIN_SETUP_KEY', '')) {
            $error = 'Invalid setup key.';
        } elseif (strlen($user) < 3 || strlen($pass) < 8) {
            $error = 'Username min 3 chars, password min 8 chars.';
        } else {
            admin_create($user, $pass, (string)env('ORDER_NOTIFY_EMAIL', null));
            admin_login($user, $pass);
            redirect('admin/index.php');
        }
    } else {
        $user = trim((string)($_POST['username'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');
        if (admin_login($user, $pass)) {
            redirect('admin/index.php');
        }
        $error = 'Incorrect username or password.';
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $needsSetup ? 'Set up admin' : 'Admin login' ?> — PCOS</title>
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <div class="brand"><span class="mark">PC</span> One Stop</div>
        <p class="muted" style="color:#5a6478;margin-top:0">
            <?= $needsSetup ? 'Create your admin account to get started.' : 'Sign in to manage your store.' ?>
        </p>
        <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <?php if ($needsSetup): ?>
                <div class="field">
                    <label>Setup key</label>
                    <input name="setup_key" required placeholder="From your .env ADMIN_SETUP_KEY">
                </div>
            <?php endif; ?>
            <div class="field">
                <label>Username</label>
                <input name="username" required autofocus>
            </div>
            <div class="field">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button class="btn" style="width:100%;justify-content:center" type="submit">
                <?= $needsSetup ? 'Create account & sign in' : 'Sign in' ?>
            </button>
        </form>
    </div>
</div>
</body>
</html>
