<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/email_tokens.php';
require_once __DIR__ . '/../includes/security.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$message = '';
$link = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_or_redirect('forgot_password.php');
    $email = trim($_POST['email'] ?? '');
    if ($email !== '') {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            $token = cz_random_token();
            $pdo->prepare('UPDATE users SET reset_token = ?, reset_token_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?')->execute([$token, (int)$user['id']]);
            $link = cz_base_url() . '/reset_password.php?token=' . urlencode($token);
            cz_send_or_show_link($email, 'Reset your ChatZone password', "Open this link to reset your password. It expires in 1 hour:\n\n" . $link, $link);
        }
        $message = 'If this email exists, a reset link has been generated.';
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Forgot Password - ChatZone</title>
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/extracted/public__forgot_password.css">
</head>

<body>
    <main class="auth-page">
        <section class="auth-card">
            <div class="auth-logo">🔑</div>
            <h1 class="auth-title">Forgot password</h1><?php if ($message): ?><div class="alert">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><form method="post"><input
                    type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-group"><label>Email</label><input type="email" name="email" required></div><button
                    class="btn-primary">Generate reset link</button>
            </form><?php if ($link): ?><p>Local testing link:</p>
            <div class="small-link"><a
                    href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?></a>
            </div><?php endif; ?><div class="auth-link"><a href="index.php">Back to login</a></div>
        </section>
    </main>
</body>

</html>