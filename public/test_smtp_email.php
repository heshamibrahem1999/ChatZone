<?php
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/functions.php';

$to = $_GET['to'] ?? (defined('CZ_SMTP_USERNAME') ? CZ_SMTP_USERNAME : '');
$result = cz_send_smtp_mail(
    $to,
    'ChatZone SMTP Test',
    "SMTP works. This is a test email from ChatZone.",
    '<h2>SMTP works ✅</h2><p>This is a test email from ChatZone.</p>'
);

header('Content-Type: text/plain; charset=utf-8');
print_r($result);
