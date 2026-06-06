<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/activity.php';
require_once __DIR__ . '/../includes/email_tokens.php';
require_once __DIR__ . '/../includes/sessions.php';
if (!empty($_SESSION['pending_2fa_user_id'])) {
    redirect('login_2fa.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && current_user($pdo)) {
    redirect('chat.php');
}
$error = isset($_GET['banned']) ? 'Your account has been banned. Please contact the admin.' : '';
if (isset($_GET['session_revoked'])) { $error = 'This session was logged out from another device.'; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_or_redirect('login.php');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rate = cz_login_rate_check($pdo, $email);
    if (!$rate['allowed']) {
        $error = $rate['message'];
    } else {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        cz_record_login_attempt($pdo, $email, true, (int)$user['id']);
        cz_clear_failed_login_attempts($pdo, $email);
        if (cz_user_column_exists($pdo, 'login_locked_until') && !empty($user['login_locked_until']) && strtotime($user['login_locked_until']) > time()) {
            $reason = !empty($user['login_lock_reason']) ? ' Reason: ' . e($user['login_lock_reason']) : '';
            $error = 'Your account is temporarily locked until ' . e($user['login_locked_until']) . '.' . $reason;
            cz_activity_log($pdo, (int)$user['id'], 'login_blocked_account_locked', 'user', (int)$user['id'], 'Login blocked: account locked');
        } elseif ((int)($user['is_banned'] ?? 0) === 1) {
            $error = 'Your account has been banned. Please contact the admin.';
        } elseif (cz_user_column_exists($pdo, 'email_verified_at') && empty($user['email_verified_at']) && !empty($user['verification_token'])) {
            $error = 'Please verify your email before login. <a href="resend_verification.php?email=' . urlencode($email) . '">Resend verification link</a>';
        } elseif ((int)($user['two_factor_enabled'] ?? 0) === 1) {
            $otpCode = (string)random_int(100000, 999999);
            $otpHash = password_hash($otpCode, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET login_otp_hash = ?, login_otp_expires = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id = ?')
                ->execute([$otpHash, (int)$user['id']]);

            require_once __DIR__ . '/../includes/mailer.php';
            $mailResult = cz_send_smtp_mail(
                $email,
                'Your ChatZone login code',
                "Your ChatZone login code is: {$otpCode}

This code expires in 10 minutes.",
                '<h2>Your ChatZone login code</h2><p>Use this code to finish logging in:</p><h1 style="letter-spacing:4px;">' . e($otpCode) . '</h1><p>This code expires in 10 minutes.</p>'
            );

            unset($_SESSION['user_id']);
            session_regenerate_id(true);
            $_SESSION['pending_2fa_user_id'] = (int)$user['id'];
            $_SESSION['pending_2fa_email'] = $email;
            $_SESSION['pending_2fa_sent'] = !empty($mailResult['sent']);
            $_SESSION['pending_2fa_error'] = $mailResult['error'] ?? null;
            cz_activity_log($pdo, (int)$user['id'], 'login_2fa_sent', 'user', (int)$user['id'], '2FA login code sent');
            redirect('login_2fa.php');
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            cz_create_user_session($pdo, (int)$user['id']);
            cz_activity_log($pdo, (int)$user['id'], 'login_success', 'user', (int)$user['id'], 'User logged in');
            redirect('chat.php');
        }
    } else {
        $error = 'Invalid email or password.';
        cz_record_login_attempt($pdo, $email, false, null);
        cz_activity_log($pdo, null, 'login_failed', 'user', null, 'Failed login for email: ' . $email);
    }
    }
}
render_header('Login');
?>
<div class="auth-wrap card">
    <h2>Login</h2>
    <?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="field"><label>Email</label><input type="email" name="email" required></div>
        <div class="field"><label>Password</label><input type="password" name="password" required></div>
        <button class="btn" type="submit">Login</button>
        <a href="register.php" style="margin-left:10px;">Create account</a>
        <a href="forgot_password.php" style="margin-left:10px;">Forgot password?</a>
    </form>
</div>
<?php render_footer(); ?>