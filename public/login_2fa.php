<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/activity.php';
require_once __DIR__ . '/../includes/email_tokens.php';
require_once __DIR__ . '/../includes/sessions.php';

if (empty($_SESSION['pending_2fa_user_id'])) {
    redirect('login.php');
}

$pendingUserId = (int)$_SESSION['pending_2fa_user_id'];
$pendingEmail = (string)($_SESSION['pending_2fa_email'] ?? '');
$error = '';
$success = '';

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$pendingUserId]);
$user = $stmt->fetch();
if (!$user) {
    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_email'], $_SESSION['pending_2fa_sent'], $_SESSION['pending_2fa_error']);
    redirect('login.php');
}

if (!empty($_SESSION['pending_2fa_sent'])) {
    $success = 'A login code was sent to ' . e($pendingEmail) . '.';
} elseif (!empty($_SESSION['pending_2fa_error'])) {
    $error = 'Email failed: ' . e((string)$_SESSION['pending_2fa_error']);
}

if (isset($_GET['resent'])) {
    $success = 'A new login code was sent to ' . e($pendingEmail) . '.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_or_redirect('login_2fa.php');
    $code = preg_replace('/\D+/', '', (string)($_POST['code'] ?? ''));

    if ($code === '' || strlen($code) !== 6) {
        $error = 'Enter the 6-digit code.';
    } elseif (empty($user['login_otp_hash']) || empty($user['login_otp_expires']) || strtotime((string)$user['login_otp_expires']) < time()) {
        $error = 'Code expired. Please resend a new code.';
    } elseif (!password_verify($code, (string)$user['login_otp_hash'])) {
        $error = 'Invalid code.';
        cz_activity_log($pdo, $pendingUserId, 'login_2fa_failed', 'user', $pendingUserId, 'Invalid 2FA code');
    } else {
        $pdo->prepare('UPDATE users SET login_otp_hash = NULL, login_otp_expires = NULL WHERE id = ?')->execute([$pendingUserId]);
        unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_email'], $_SESSION['pending_2fa_sent'], $_SESSION['pending_2fa_error']);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $pendingUserId;
        cz_create_user_session($pdo, $pendingUserId);
        cz_activity_log($pdo, $pendingUserId, 'login_success_2fa', 'user', $pendingUserId, 'User logged in with 2FA');
        redirect('chat.php');
    }
}

render_header('Two-Factor Login');
?>
<div class="auth-wrap card">
    <h2>Two-Factor Login</h2>
    <p class="small">Enter the 6-digit code sent to <?= e($pendingEmail) ?>.</p>
    <?php if ($success): ?><div class="success"><?= $success ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="field">
            <label>Login code</label>
            <input type="text" name="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required>
        </div>
        <button class="btn" type="submit">Verify & Login</button>
        <a href="resend_login_otp.php" style="margin-left:10px;">Resend code</a>
        <a href="login.php" style="margin-left:10px;">Cancel</a>
    </form>
</div>
<?php render_footer(); ?>