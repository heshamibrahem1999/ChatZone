<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/activity.php';
require_once __DIR__ . '/../includes/mailer.php';

if (empty($_SESSION['pending_2fa_user_id'])) {
    redirect('login.php');
}

$userId = (int)$_SESSION['pending_2fa_user_id'];
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    redirect('login.php');
}

$otpCode = (string)random_int(100000, 999999);
$otpHash = password_hash($otpCode, PASSWORD_DEFAULT);
$pdo->prepare('UPDATE users SET login_otp_hash = ?, login_otp_expires = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id = ?')
    ->execute([$otpHash, $userId]);

$email = (string)$user['email'];
$mailResult = cz_send_smtp_mail(
    $email,
    'Your ChatZone login code',
    "Your ChatZone login code is: {$otpCode}\n\nThis code expires in 10 minutes.",
    '<h2>Your ChatZone login code</h2><p>Use this code to finish logging in:</p><h1 style="letter-spacing:4px;">' . e($otpCode) . '</h1><p>This code expires in 10 minutes.</p>'
);

$_SESSION['pending_2fa_email'] = $email;
$_SESSION['pending_2fa_sent'] = !empty($mailResult['sent']);
$_SESSION['pending_2fa_error'] = $mailResult['error'] ?? null;
cz_activity_log($pdo, $userId, 'login_2fa_resent', 'user', $userId, '2FA login code resent');
redirect('login_2fa.php?resent=1');