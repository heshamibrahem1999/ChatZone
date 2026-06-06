<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_login($pdo);
header('Content-Type: text/plain');
echo "Logged user ID: " . (int)$user['id'] . "
";
echo "Email: " . ($user['email'] ?? '') . "
";
echo "two_factor_enabled: " . (int)($user['two_factor_enabled'] ?? 0) . "
";
echo "If this says 0, enable 2FA again from security_2fa.php.
";
