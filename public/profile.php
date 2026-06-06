<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/presence.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];
update_user_presence($pdo, $userId);

$message = "";
$messageType = "error";

$stmt = $pdo->prepare("
    SELECT id, first_name, last_name, email, language, theme, background,
           text_color, text_background, text_size, profile_photo, about
    FROM users
    WHERE id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: index.php");
    exit;
}

$labels = language_labels($user['language']);
$isArabic = ($user['language'] === 'Arabic');
$langCode = $isArabic ? 'ar' : ($user['language'] === 'French' ? 'fr' : 'en');
$dir = $isArabic ? 'rtl' : 'ltr';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_or_redirect('profile.php');
    $firstName = trim($_POST['first_name'] ?? $user['first_name']);
    $lastName = trim($_POST['last_name'] ?? $user['last_name']);
    $email = trim($_POST['email'] ?? $user['email']);
    $about = trim($_POST['about'] ?? ($user['about'] ?? ''));
    if (mb_strlen($about) > 160) {
        $about = mb_substr($about, 0, 160);
    }
    $language = trim($_POST['language'] ?? $user['language']);
    $theme = trim($_POST['theme'] ?? $user['theme']);
    $background = trim($_POST['background'] ?? $user['background']);
    $textColor = trim($_POST['text_color'] ?? $user['text_color']);
    $textBackground = trim($_POST['text_background'] ?? $user['text_background']);
    $textSize = trim($_POST['text_size'] ?? $user['text_size']);
    $profilePhoto = $user['profile_photo'] ?: 'default.png';

    if ($firstName === '' || $lastName === '' || $email === '') {
        $message = "Please fill all required fields.";
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
        $check->execute([$email, $userId]);

        if ($check->fetch()) {
            $message = "This email is already used by another account.";
        } else {
            if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/uploads/profiles/';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $tmpName = $_FILES['profile_photo']['tmp_name'];
                $originalName = $_FILES['profile_photo']['name'];
                $fileSize = $_FILES['profile_photo']['size'];
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                if (!in_array($extension, $allowed, true)) {
                    $message = "Only jpg, jpeg, png, gif, and webp images are allowed.";
                } elseif ($fileSize > 2 * 1024 * 1024) {
                    $message = "Profile photo must be less than 2MB.";
                } elseif (getimagesize($tmpName) === false) {
                    $message = "Uploaded file is not a valid image.";
                } else {
                    $newFileName = time() . '_' . bin2hex(random_bytes(6)) . '.' . $extension;

                    if (move_uploaded_file($tmpName, $uploadDir . $newFileName)) {
                        if (!empty($user['profile_photo']) && $user['profile_photo'] !== 'default.png') {
                            $oldFile = $uploadDir . $user['profile_photo'];
                            if (file_exists($oldFile)) {
                                unlink($oldFile);
                            }
                        }

                        $profilePhoto = $newFileName;
                    } else {
                        $message = "Failed to upload profile photo.";
                    }
                }
            }

            if ($message === '') {
                $update = $pdo->prepare("
                    UPDATE users
                    SET first_name = ?,
                        last_name = ?,
                        email = ?,
                        about = ?,
                        language = ?,
                        theme = ?,
                        background = ?,
                        text_color = ?,
                        text_background = ?,
                        text_size = ?,
                        profile_photo = ?
                    WHERE id = ?
                ");

                $ok = $update->execute([
                    $firstName,
                    $lastName,
                    $email,
                    $about,
                    $language,
                    $theme,
                    $background,
                    $textColor,
                    $textBackground,
                    $textSize,
                    $profilePhoto,
                    $userId
                ]);

                if ($ok) {
                    $_SESSION['first_name'] = $firstName;
                    $_SESSION['last_name'] = $lastName;
                    $_SESSION['user_name'] = trim($firstName . ' ' . $lastName);
                    $_SESSION['user_email'] = $email;
                    $_SESSION['about'] = $about;
                    $_SESSION['language'] = $language;
                    $_SESSION['theme'] = $theme;
                    $_SESSION['background'] = $background;
                    $_SESSION['text_color'] = $textColor;
                    $_SESSION['text_background'] = $textBackground;
                    $_SESSION['text_size'] = $textSize;
                    $_SESSION['profile_photo'] = $profilePhoto;

                    header("Location: profile.php?updated=1");
                    exit;
                }

                $message = "Failed to update profile.";
            }
        }
    }
}

if (isset($_GET['updated'])) {
    $message = "Profile updated successfully.";
    $messageType = "success";

    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, email, language, theme, background,
               text_color, text_background, text_size, profile_photo, about
        FROM users
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
}

$labels = language_labels($user['language']);
$isArabic = ($user['language'] === 'Arabic');
$langCode = $isArabic ? 'ar' : ($user['language'] === 'French' ? 'fr' : 'en');
$dir = $isArabic ? 'rtl' : 'ltr';
?>
<!DOCTYPE html>
<html lang="<?= e($langCode) ?>" dir="<?= e($dir) ?>">
<head>
    <meta charset="UTF-8">
    <title><?= e($labels['edit_profile'] ?? 'Edit Profile') ?> - ChatZone</title>
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
    <main class="auth-page">
        <section class="auth-card settings-card">
            <div class="auth-logo">⚙️</div>

            <h1 class="auth-title"><?= e($labels['settings'] ?? 'Settings') ?></h1>
            <p class="auth-subtitle">Update your account, appearance, language, and profile photo.</p>

            <?php if ($message): ?>
                <div class="alert <?= htmlspecialchars($messageType) ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <img
                class="profile-photo-preview"
                src="uploads/profiles/<?= e($user['profile_photo'] ?: 'default.png') ?>"
                alt="Profile photo"
            >

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label><?= e($labels['first_name'] ?? 'First Name') ?></label>
                        <input type="text" name="first_name" value="<?= e($user['first_name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label><?= e($labels['last_name'] ?? 'Last Name') ?></label>
                        <input type="text" name="last_name" value="<?= e($user['last_name']) ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label><?= e($labels['email'] ?? 'Email') ?></label>
                    <input type="email" name="email" value="<?= e($user['email']) ?>" required>
                </div>

                <div class="form-group">
                    <label>About / Bio</label>
                    <textarea name="about" maxlength="160" rows="3" placeholder="Available, busy, at work..." style="width:100%;resize:vertical;"><?= e($user['about'] ?? '') ?></textarea>
                    <small style="color:#667781;">Max 160 characters.</small>
                </div>

                <div class="form-group">
                    <label><?= e($labels['language'] ?? 'Language') ?></label>
                    <select name="language">
                        <option value="English" <?= $user['language'] === 'English' ? 'selected' : '' ?>>English</option>
                        <option value="Arabic" <?= $user['language'] === 'Arabic' ? 'selected' : '' ?>>Arabic</option>
                        <option value="French" <?= $user['language'] === 'French' ? 'selected' : '' ?>>French</option>
                    </select>
                </div>

                <div class="color-grid">
                    <div class="form-group">
                        <label><?= e($labels['theme'] ?? 'Theme') ?></label>
                        <input type="color" name="theme" value="<?= e($user['theme'] ?: '#00a884') ?>">
                    </div>

                    <div class="form-group">
                        <label><?= e($labels['background'] ?? 'Background') ?></label>
                        <input type="color" name="background" value="<?= e($user['background'] ?: '#efeae2') ?>">
                    </div>

                    <div class="form-group">
                        <label><?= e($labels['text_color'] ?? 'Text Color') ?></label>
                        <input type="color" name="text_color" value="<?= e($user['text_color'] ?: '#111111') ?>">
                    </div>

                    <div class="form-group">
                        <label><?= e($labels['text_background'] ?? 'Message Bubble') ?></label>
                        <input type="color" name="text_background" value="<?= e($user['text_background'] ?: '#d9fdd3') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label><?= e($labels['text_size'] ?? 'Text Size') ?></label>
                    <select name="text_size">
                        <option value="14px" <?= $user['text_size'] === '14px' ? 'selected' : '' ?>>Small</option>
                        <option value="16px" <?= $user['text_size'] === '16px' ? 'selected' : '' ?>>Medium</option>
                        <option value="18px" <?= $user['text_size'] === '18px' ? 'selected' : '' ?>>Large</option>
                    </select>
                </div>

                <div class="form-group">
                    <label><?= e($labels['profile_photo'] ?? 'Profile Photo') ?></label>
                    <input type="file" name="profile_photo" accept="image/*">
                </div>

                <button class="btn-primary" type="submit">
                    <?= e($labels['save'] ?? 'Save Changes') ?>
                </button>
            </form>

            <a class="back-link" href="chat.php">
                ← <?= e($labels['back_to_chat'] ?? 'Back to Chat') ?>
            </a>
        </section>
    </main>
</body>
</html>