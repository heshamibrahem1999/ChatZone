<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/email_tokens.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$email = trim($_GET['email'] ?? $_POST['email'] ?? '');
$message = '';
$link = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $email !== '') {
    if ($email === '') {
        $message = 'Enter your email.';
    } else {
        $stmt = $pdo->prepare('SELECT id, email_verified_at FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && empty($user['email_verified_at'])) {
            $token = cz_random_token();
            $pdo->prepare('UPDATE users SET verification_token = ? WHERE id = ?')->execute([$token, (int)$user['id']]);
            $link = cz_base_url() . '/verify_email.php?token=' . urlencode($token);
            cz_send_or_show_link($email, 'Verify your ChatZone email', "Open this link to verify your ChatZone account:\n\n" . $link, $link);
            $message = 'Verification link generated.';
        } elseif ($user) {
            $message = 'This email is already verified.';
        } else {
            $message = 'No account found with this email.';
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Resend Verification - ChatZone</title><link rel="stylesheet" href="assets/css/auth.css"><link rel="stylesheet" href="assets/css/extracted/public__resend_verification.css"></head><body><main class="auth-page"><section class="auth-card"><div class="auth-logo">✉️</div><h1 class="auth-title">Resend verification</h1><?php if ($message): ?><div class="alert <?= $link ? '' : 'error' ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><form method="post"><div class="form-group"><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required></div><button class="btn-primary">Send link</button></form><?php if ($link): ?><div class="small-link"><a href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?></a></div><?php endif; ?><div class="auth-link"><a href="index.php">Back to login</a></div></section></main></body></html>
