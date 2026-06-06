<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/email_tokens.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header("Location: chat.php");
    exit;
}

$message = "";

if (!empty($_SESSION['flash_error'])) {
    $message = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_or_redirect('register.php');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $language = trim($_POST['language'] ?? 'English');

    $theme = '#00a884';
    $background = '#efeae2';
    $textColor = '#111111';
    $textBackground = '#d9fdd3';
    $textSize = '16px';
    $profilePhoto = 'default.png';

    if ($firstName === '' || $lastName === '' || $email === '' || $password === '') {
        $message = "Please fill all required fields.";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters.";
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check->execute([$email]);

        if ($check->fetch()) {
            $message = "This email is already registered.";
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
                        $profilePhoto = $newFileName;
                    } else {
                        $message = "Failed to upload profile photo.";
                    }
                }
            }

            if ($message === '') {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $verificationToken = cz_random_token();

                $stmt = $pdo->prepare("
                    INSERT INTO users
                    (first_name, last_name, email, password_hash, language, theme, background,
                     text_color, text_background, text_size, profile_photo, verification_token, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");

                $ok = $stmt->execute([
                    $firstName,
                    $lastName,
                    $email,
                    $passwordHash,
                    $language,
                    $theme,
                    $background,
                    $textColor,
                    $textBackground,
                    $textSize,
                    $profilePhoto,
                    $verificationToken
                ]);

                if ($ok) {
                    $verifyLink = cz_base_url() . '/verify_email.php?token=' . urlencode($verificationToken);
                    $_SESSION['last_verification_link'] = $verifyLink;
                    cz_send_or_show_link($email, 'Verify your ChatZone email', "Open this link to verify your ChatZone account:\n\n" . $verifyLink, $verifyLink);
                    header("Location: verify_notice.php?email=" . urlencode($email));
                    exit;
                }

                $message = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Create Account - ChatZone</title>
    <link rel="stylesheet" href="assets/css/auth.css">
</head>

<body>
    <main class="auth-page">
        <section class="auth-card">
            <div class="auth-logo">💬</div>

            <h1 class="auth-title">Create account</h1>
            <p class="auth-subtitle">Join ChatZone and start chatting with your friends.</p>

            <?php if ($message): ?>
            <div class="alert error">
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label>First name</label>
                        <input type="text" name="first_name" placeholder="First name" required>
                    </div>

                    <div class="form-group">
                        <label>Last name</label>
                        <input type="text" name="last_name" placeholder="Last name" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email address</label>
                    <input type="email" name="email" placeholder="you@example.com" required>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="At least 6 characters" required>
                </div>

                <div class="form-group">
                    <label>Language</label>
                    <select name="language">
                        <option value="English">English</option>
                        <option value="Arabic">Arabic</option>
                        <option value="French">French</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Profile photo</label>
                    <input type="file" name="profile_photo" accept="image/*">
                </div>

                <button class="btn-primary" type="submit">Create Account</button>
            </form>

            <div class="auth-link">
                Already have an account?
                <a href="index.php">Sign in</a>
            </div>
        </section>
    </main>
</body>

</html>