<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

$user = require_login($pdo);
$userId = (int)$user['id'];
$message = $_SESSION['privacy_message'] ?? '';
$error = $_SESSION['privacy_error'] ?? '';
unset($_SESSION['privacy_message'], $_SESSION['privacy_error']);
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy & Data - ChatZone</title>
    <link rel="stylesheet" href="assets/css/chat.css?v=20260530-privacy">
    <link rel="stylesheet" href="assets/css/extracted/public__privacy.css">
</head>

<body class="<?= ($_COOKIE['cz_dark_mode'] ?? '') === '1' ? 'dark-mode' : '' ?>">
    <div class="privacy-wrap">
        <div class="privacy-card">
            <h2>Privacy & Data</h2>
            <p class="small">Manage your ChatZone data and account privacy.</p>
            <p><a href="chat.php">← Back to chat</a></p>
        </div>

        <?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice err"><?= e($error) ?></div><?php endif; ?>

        <div class="privacy-card">
            <h3>Download my data</h3>
            <p>Download a JSON file containing your profile, private messages, group memberships, group messages, stars,
                archives, mutes, reports you submitted, and scheduled messages.</p>
            <div class="privacy-actions">
                <a class="btn" href="download_my_data.php">Download JSON</a>
            </div>
        </div>

        <div class="privacy-card danger-zone">
            <h3>Delete my account</h3>
            <p>This will anonymize your profile, remove your login email/password, log you out, and prevent future
                login. Existing messages will remain as <b>Deleted User</b> so other chats do not break.</p>
            <form method="post" action="delete_account.php"
                onsubmit="return confirm('Delete your account permanently? This cannot be undone.');">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="field">
                    <label>Enter your password to confirm</label>
                    <input type="password" name="password" required>
                </div>
                <button class="btn danger" type="submit">Delete my account</button>
            </form>
        </div>
    </div>
</body>

</html>