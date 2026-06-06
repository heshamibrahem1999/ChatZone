<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login($pdo);
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Clear ChatZone Cache</title>
    <link rel="stylesheet" href="assets/css/extracted/public__clear_cache.css">
</head>

<body>
    <div class="box">
        <h1>ChatZone cache clear</h1>
        <p id="status">Clearing browser cache, service worker, and local storage...</p>
        <pre id="log"></pre>
        <a href="chat.php">Back to Chat</a>
    </div>
    <script src="assets/js/extracted/public__clear_cache-1.js"></script>
</body>

</html>