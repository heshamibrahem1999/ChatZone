<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$message = '';
$valid = false;
if ($token !== '') {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > NOW() LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    $valid = (bool)$row;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
        require_csrf_or_redirect('reset_password.php?token=' . urlencode($token));
        $p1 = $_POST['password'] ?? '';
        $p2 = $_POST['password_confirm'] ?? '';
        if (strlen($p1) < 6) {
            $message = 'Password must be at least 6 characters.';
        } elseif ($p1 !== $p2) {
            $message = 'Passwords do not match.';
        } else {
            $hash = password_hash($p1, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?')->execute([$hash, (int)$row['id']]);
            $message = 'Password changed successfully. You can login now.';
            $valid = false;
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reset Password - ChatZone</title><link rel="stylesheet" href="assets/css/auth.css"></head><body><main class="auth-page"><section class="auth-card"><div class="auth-logo">🔐</div><h1 class="auth-title">Reset password</h1><?php if ($message): ?><div class="alert <?= str_contains($message, 'successfully') ? '' : 'error' ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><?php if ($valid): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"><div class="form-group"><label>New password</label><input type="password" name="password" required></div><div class="form-group"><label>Confirm password</label><input type="password" name="password_confirm" required></div><button class="btn-primary">Change password</button></form><?php else: ?><?php if (!$message): ?><p class="auth-subtitle">Invalid or expired reset link.</p><?php endif; ?><a class="btn-primary" href="forgot_password.php">Request new link</a><?php endif; ?><div class="auth-link"><a href="index.php">Back to login</a></div></section></main></body></html>
