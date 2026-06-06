<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/email_tokens.php';
$token = trim($_GET['token'] ?? '');
$message = 'Invalid verification link.';
$ok = false;
if ($token !== '') {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE verification_token = ? LIMIT 1');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user) {
        $up = $pdo->prepare('UPDATE users SET email_verified_at = NOW(), verification_token = NULL WHERE id = ?');
        $up->execute([(int)$user['id']]);
        $message = 'Email verified successfully. You can now login.';
        $ok = true;
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Email Verification - ChatZone</title>
    <link rel="stylesheet" href="assets/css/auth.css">
</head>

<body>
    <main class="auth-page">
        <section class="auth-card">
            <div class="auth-logo"><?= $ok ? '✅' : '⚠️' ?></div>
            <h1 class="auth-title">Email Verification</h1>
            <p class="auth-subtitle"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><a class="btn-primary"
                href="index.php">Login</a>
        </section>
    </main>
</body>

</html>