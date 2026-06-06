<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin/admin_common.php';

$user = cz_admin_require($pdo);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])
    ) {
        $error = 'Invalid request token.';
    } else {
        $enabled = ($_POST['maintenance_enabled'] ?? '0') === '1' ? '1' : '0';
        $message = trim((string)($_POST['maintenance_message'] ?? ''));
        if ($message === '') {
            $message = 'ChatZone is temporarily under maintenance. Please check back soon.';
        }
        try {
            cz_set_site_setting($pdo, 'maintenance_enabled', $enabled);
            cz_set_site_setting($pdo, 'maintenance_message', $message);
            if (function_exists('cz_log_activity')) {
                cz_log_activity($pdo, (int)$user['id'], 'maintenance_update', 'Maintenance mode ' . ($enabled === '1' ? 'enabled' : 'disabled'));
            }
            $success = 'Maintenance settings saved.';
        } catch (Throwable $e) {
            $error = 'Failed to save settings: ' . $e->getMessage();
        }
    }
}

$enabled = cz_maintenance_enabled($pdo);
$message = cz_maintenance_message($pdo);
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance Mode - ChatZone</title>
    <link rel="stylesheet" href="assets/css/chat.css?v=20260530-maintenance-1">
    <link rel="stylesheet" href="assets/css/admin/admin_maintenance.css">
</head>

<body>
    <div class="wrap">
        <div class="top">
            <div>
                <h1>🛠️ Maintenance Mode</h1>
                <p class="hint">Temporarily close ChatZone for normal users while admins continue working.</p>
            </div>
            <div><a class="btn secondary" href="admin_dashboard.php">← Dashboard</a> <a class="btn secondary"
                    href="chat.php">Chat</a></div>
        </div>
        <div class="card">
            <?php if ($success): ?><div class="alert ok"><?= e($success) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
            <p>Current status: <span class="status <?= $enabled ? 'on' : 'off' ?>"><?= $enabled ? 'ON' : 'OFF' ?></span>
            </p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label>Mode</label>
                <div class="switch-row">
                    <label><input type="radio" name="maintenance_enabled" value="0" <?= !$enabled ? 'checked' : '' ?>>
                        Off</label>
                    <label><input type="radio" name="maintenance_enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
                        On</label>
                </div>
                <label>Message shown to users</label>
                <textarea name="maintenance_message"><?= e($message) ?></textarea>
                <div class="preview"><b>Preview:</b><br><?= nl2br(e($message)) ?></div>
                <p class="hint">Admins are not blocked. Normal users will be redirected to <code>maintenance.php</code>.
                </p>
                <button class="btn" type="submit">Save Settings</button>
            </form>
        </div>
    </div>
</body>

</html>