<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$message = '';
$messageType = 'success';

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS notify_sound TINYINT(1) NOT NULL DEFAULT 1");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS notify_browser TINYINT(1) NOT NULL DEFAULT 1");
} catch (Throwable $e) {
    
    try { $pdo->exec("ALTER TABLE users ADD COLUMN notify_sound TINYINT(1) NOT NULL DEFAULT 1"); } catch (Throwable $ignore) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN notify_browser TINYINT(1) NOT NULL DEFAULT 1"); } catch (Throwable $ignore) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_or_redirect('notification_settings.php');
    $notifySound = isset($_POST['notify_sound']) ? 1 : 0;
    $notifyBrowser = isset($_POST['notify_browser']) ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE users SET notify_sound = ?, notify_browser = ? WHERE id = ?");
    $stmt->execute([$notifySound, $notifyBrowser, $userId]);
    header('Location: notification_settings.php?saved=1');
    exit;
}

$stmt = $pdo->prepare("SELECT first_name, last_name, language, notify_sound, notify_browser FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if (isset($_GET['saved'])) {
    $message = 'Notification settings saved.';
}

$isArabic = (($user['language'] ?? 'English') === 'Arabic');
$langCode = $isArabic ? 'ar' : (($user['language'] ?? 'English') === 'French' ? 'fr' : 'en');
$dir = $isArabic ? 'rtl' : 'ltr';
?>
<!DOCTYPE html>
<html lang="<?= e($langCode) ?>" dir="<?= e($dir) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - ChatZone</title>
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/extracted/public__notification_settings.css">
</head>
<body>
<main class="auth-page">
    <section class="auth-card settings-card">
        <div class="auth-logo">🔔</div>
        <h1 class="auth-title">Notification Settings</h1>
        <p class="auth-subtitle">Control sound and browser notification preferences for your account.</p>

        <?php if ($message): ?>
            <div class="alert <?= e($messageType) ?>"><?= e($message) ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <label class="switch-row">
                <div>
                    <strong>Message sound</strong>
                    <span>Play a small sound when new messages arrive.</span>
                </div>
                <input type="checkbox" name="notify_sound" value="1" <?= ((int)($user['notify_sound'] ?? 1) === 1) ? 'checked' : '' ?>>
            </label>

            <label class="switch-row">
                <div>
                    <strong>Browser notifications</strong>
                    <span>Show notifications when ChatZone is open in another tab, minimized, or inactive.</span>
                    <span id="browserPermissionStatus" style="display:block;margin-top:6px;font-size:12px;"></span>
                </div>
                <input type="checkbox" name="notify_browser" value="1" <?= ((int)($user['notify_browser'] ?? 1) === 1) ? 'checked' : '' ?>>
            </label>

            <button class="btn-primary" type="submit">Save Settings</button>
            <button class="back-link" type="button" id="enableBrowserNotifications" style="border:0;cursor:pointer;">Enable browser permission</button>
        </form>

        <div class="mini-actions">
            <a class="back-link" href="chat.php">← Back to Chat</a>
            <a class="back-link" href="profile.php">Profile Settings</a>
        </div>
    </section>
</main>

<script src="assets/js/push-notifications.js?v=20260605-v19-push"></script>
<script src="assets/js/extracted/public__notification_settings-1.js"></script>
</body>
</html>
