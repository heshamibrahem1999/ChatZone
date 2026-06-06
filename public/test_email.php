<?php
require_once __DIR__ . '/../includes/mailer.php';

$result = cz_send_smtp_mail(
    'heshamibrahemchatzone@gmail.com',
    'ChatZone SMTP Test',
    'SMTP works',
    '<h2>SMTP works ✅</h2><p>This is a test email.</p>'
);

echo '<pre>';
var_dump($result);
echo '</pre>';