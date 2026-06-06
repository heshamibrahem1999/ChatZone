<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/activity.php';

$user = require_login($pdo);
$message = '';
$error = '';

$pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0");
$pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS login_otp_hash VARCHAR(255) DEFAULT NULL");
$pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS login_otp_expires DATETIME DEFAULT NULL");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_or_redirect('security_2fa.php');
    $action = $_POST['action'] ?? '';
    if ($action === 'enable') {
        $pdo->prepare('UPDATE users SET two_factor_enabled = 1 WHERE id = ?')->execute([(int)$user['id']]);
        cz_activity_log($pdo, (int)$user['id'], '2fa_enabled', 'user', (int)$user['id'], 'User enabled email 2FA');
        $message = 'Email two-factor login enabled.';
        $user['two_factor_enabled'] = 1;
    } elseif ($action === 'disable') {
        $pdo->prepare('UPDATE users SET two_factor_enabled = 0, login_otp_hash = NULL, login_otp_expires = NULL WHERE id = ?')->execute([(int)$user['id']]);
        cz_activity_log($pdo, (int)$user['id'], '2fa_disabled', 'user', (int)$user['id'], 'User disabled email 2FA');
        $message = 'Email two-factor login disabled.';
        $user['two_factor_enabled'] = 0;
    } else {
        $error = 'Invalid action.';
    }
}

render_header('Two-Factor Security', $user);
?>
<script src="assets/js/extracted/public__security_2fa-1.js"></script>
<link rel="stylesheet" href="assets/css/extracted/public__security_2fa.css">
    <div class="card" style="max-width:720px;margin:30px auto;">
        <div class="between">
            <div>
                <h2>Two-Factor Login</h2>
                <p class="small">Protect your account with a 6-digit code sent to your email after password login.</p>
            </div>
            <a class="btn secondary" href="chat.php">Back to Chat</a>
        </div>

        <?php if ($message): ?><div class="success"><?= e($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

        <p><strong>Status:</strong> <?= ((int)($user['two_factor_enabled'] ?? 0) === 1) ? 'Enabled ✅' : 'Disabled ❌' ?></p>
        <p><strong>Email:</strong> <?= e((string)$user['email']) ?></p>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <?php if ((int)($user['two_factor_enabled'] ?? 0) === 1): ?>
                <input type="hidden" name="action" value="disable">
                <button class="btn danger" type="submit">Disable 2FA</button>
            <?php else: ?>
                <input type="hidden" name="action" value="enable">
                <button class="btn" type="submit">Enable Email 2FA</button>
            <?php endif; ?>
        </form>
    </div>
<?php render_footer(); ?>
