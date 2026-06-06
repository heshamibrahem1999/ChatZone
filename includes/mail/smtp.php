<?php
require_once __DIR__ . '/../mail_config.php';

function cz_smtp_read($socket): string {
    $data = '';
    while (($line = fgets($socket, 515)) !== false) {
        $data .= $line;
        if (isset($line[3]) && $line[3] === ' ') break;
    }
    return $data;
}

function cz_smtp_cmd($socket, string $cmd, array $okCodes): string {
    fwrite($socket, $cmd . "\r\n");
    $response = cz_smtp_read($socket);
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, $okCodes, true)) {
        throw new RuntimeException(trim($response));
    }
    return $response;
}

function cz_send_smtp_mail(string $to, string $subject, string $plainBody, ?string $htmlBody = null): array {
    if (!defined('CZ_SMTP_ENABLED') || !CZ_SMTP_ENABLED) {
        return ['sent' => false, 'error' => 'SMTP is disabled in includes/mail_config.php'];
    }

    if (!CZ_SMTP_HOST || !CZ_SMTP_USERNAME || !CZ_SMTP_PASSWORD) {
        return ['sent' => false, 'error' => 'SMTP settings are incomplete in includes/mail_config.php'];
    }

    $host = CZ_SMTP_HOST;
    $port = (int)CZ_SMTP_PORT;
    $secure = strtolower((string)CZ_SMTP_SECURE);
    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;

    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        return ['sent' => false, 'error' => "SMTP connection failed: $errstr ($errno)"];
    }

    stream_set_timeout($socket, 15);

    try {
        $greeting = cz_smtp_read($socket);
        if ((int)substr($greeting, 0, 3) !== 220) {
            throw new RuntimeException(trim($greeting));
        }

        cz_smtp_cmd($socket, 'EHLO localhost', [250]);

        if ($secure === 'tls') {
            cz_smtp_cmd($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Could not enable TLS encryption.');
            }
            cz_smtp_cmd($socket, 'EHLO localhost', [250]);
        }

        cz_smtp_cmd($socket, 'AUTH LOGIN', [334]);
        cz_smtp_cmd($socket, base64_encode(CZ_SMTP_USERNAME), [334]);
        cz_smtp_cmd($socket, base64_encode(CZ_SMTP_PASSWORD), [235]);

        $fromEmail = CZ_MAIL_FROM_EMAIL ?: CZ_SMTP_USERNAME;
        $fromName = CZ_MAIL_FROM_NAME ?: 'ChatZone';
        cz_smtp_cmd($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        cz_smtp_cmd($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        cz_smtp_cmd($socket, 'DATA', [354]);

        $boundary = 'cz_' . bin2hex(random_bytes(12));
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'From: ' . mb_encode_mimeheader($fromName) . ' <' . $fromEmail . '>';
        $headers[] = 'To: <' . $to . '>';
        $headers[] = 'Subject: ' . mb_encode_mimeheader($subject);
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $htmlBody = $htmlBody ?: nl2br(htmlspecialchars($plainBody, ENT_QUOTES, 'UTF-8'));
        $message = implode("\r\n", $headers) . "\r\n\r\n";
        $message .= '--' . $boundary . "\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $plainBody . "\r\n\r\n";
        $message .= '--' . $boundary . "\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $htmlBody . "\r\n\r\n";
        $message .= '--' . $boundary . "--\r\n";
        $message = preg_replace("/\r?\n\. /", "\r\n..", $message);

        fwrite($socket, $message . "\r\n.\r\n");
        $response = cz_smtp_read($socket);
        $code = (int)substr($response, 0, 3);
        if ($code !== 250) {
            throw new RuntimeException(trim($response));
        }

        cz_smtp_cmd($socket, 'QUIT', [221]);
        fclose($socket);
        return ['sent' => true, 'error' => null];
    } catch (Throwable $e) {
        @fwrite($socket, "QUIT\r\n");
        @fclose($socket);
        return ['sent' => false, 'error' => $e->getMessage()];
    }
}
