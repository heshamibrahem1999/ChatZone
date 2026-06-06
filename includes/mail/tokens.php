<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/smtp.php';

function cz_random_token(): string {
    return bin2hex(random_bytes(32));
}

function cz_base_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/ChatZone-PHP/public')), '/');
    return $scheme . '://' . $host . $dir;
}

function cz_html_link_email(string $subject, string $body, string $link): string {
    $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
    $safeBody = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
    $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

    return '<!doctype html><html><body style="font-family:Arial,sans-serif;background:#f6f7f9;padding:24px;color:#111827;">'
        . '<div style="max-width:620px;margin:auto;background:#ffffff;border-radius:16px;padding:24px;border:1px solid #e5e7eb;">'
        . '<h2 style="margin-top:0;color:#00a884;">' . $safeSubject . '</h2>'
        . '<p style="line-height:1.6;">' . $safeBody . '</p>'
        . '<p><a href="' . $safeLink . '" style="display:inline-block;background:#00a884;color:#fff;text-decoration:none;padding:12px 18px;border-radius:10px;font-weight:bold;">Open Link</a></p>'
        . '<p style="font-size:12px;color:#6b7280;word-break:break-all;">' . $safeLink . '</p>'
        . '</div></body></html>';
}

function cz_send_or_show_link(string $to, string $subject, string $body, string $link): array {
    $html = cz_html_link_email($subject, $body, $link);

    if (function_exists('cz_send_smtp_mail')) {
        $result = cz_send_smtp_mail($to, $subject, $body, $html);
        return [
            'sent' => !empty($result['sent']),
            'error' => $result['error'] ?? null,
            'link' => $link,
        ];
    }

    return [
        'sent' => false,
        'error' => 'SMTP sender is not loaded.',
        'link' => $link,
    ];
}

function cz_user_column_exists(PDO $pdo, string $column): bool {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE ?");
    $stmt->execute([$column]);
    return (bool)$stmt->fetch();
}
