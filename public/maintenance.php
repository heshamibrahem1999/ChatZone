<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$user = current_user($pdo);
if (!$user) {
    redirect('login.php');
}
if (!cz_maintenance_enabled($pdo) || (int)($user['is_admin'] ?? 0) === 1) {
    redirect('chat.php');
}
$message = cz_maintenance_message($pdo);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Maintenance - ChatZone</title>
<link rel="stylesheet" href="assets/css/extracted/public__maintenance.css">
</head>
<body>
<div class="card">
  <div class="icon">🛠️</div>
  <h1>ChatZone is under maintenance</h1>
  <p class="muted"><?= nl2br(e($message)) ?></p>
  <a class="btn" href="logout.php">Logout</a>
</div>
</body>
</html>
