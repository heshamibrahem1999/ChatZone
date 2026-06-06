<?php
require_once __DIR__ . '/../includes/security.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$email = trim($_GET['email'] ?? '');
$link = $_SESSION['last_verification_link'] ?? '';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Verify Email - ChatZone</title>
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/extracted/public__verify_notice.css">
</head>

<body>
    <main class="auth-page">
        <section class="auth-card">
            <div class="auth-logo">✉️</div>
            <h1 class="auth-title">Verify your email</h1>
            <p class="auth-subtitle">We created your account. Please verify your email before logging in.</p>
            <?php if ($email): ?><p><b><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></b></p>
            <?php endif; ?><br><a
                class="btn-primary" href="index.php">Back to login</a>
        </section>
    </main>
    <?php if (isset($_SESSION['last_mail_sent']) && !$_SESSION['last_mail_sent']): ?>
    <div class="alert error" style="max-width:680px;margin:15px auto;">
        Email was not sent. <?= e((string)($_SESSION['last_mail_error'] ?? 'Unknown SMTP error')) ?>
        <?php if (!empty($_SESSION['last_verification_link'])): ?>
        <br><br>Local test link: <a href="<?= e($_SESSION['last_verification_link']) ?>">Verify account manually</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</body>

</html>