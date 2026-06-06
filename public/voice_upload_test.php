<?php
require_once __DIR__ . '/../includes/security.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) { echo 'Login first, then open this page again.'; exit; }
?>
<!doctype html><html><head><meta charset="utf-8"><title>Voice Upload Test</title></head><body>
<h2>Voice Upload Test</h2>
<p>Open this page after login. Record 2 seconds, stop, then click Upload Test. It saves only the file, not a chat message.</p>
<button id="rec">Record</button> <button id="stop" disabled>Stop</button> <button id="upload" disabled data-csrf="<?= e(csrf_token()) ?>">Upload Test</button>
<pre id="out"></pre>
<script src="assets/js/voice-upload-test.js"></script>
</body></html>
