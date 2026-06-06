<?php
function cz_client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function cz_login_rate_check(PDO $pdo, string $email, int $maxAttempts = 5, int $windowMinutes = 15): array
{
    $ip = cz_client_ip();
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE (email = ? OR ip_address = ?) AND success = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL {$windowMinutes} MINUTE)");
        $stmt->execute([$email, $ip]);
        $count = (int)$stmt->fetchColumn();
        if ($count >= $maxAttempts) {
            return ['allowed' => false, 'message' => "Too many failed login attempts. Try again after {$windowMinutes} minutes."];
        }
    } catch (Throwable $e) {
        return ['allowed' => true, 'message' => null];
    }
    return ['allowed' => true, 'message' => null];
}

function cz_record_login_attempt(PDO $pdo, ?string $email, bool $success, ?int $userId = null): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO login_attempts (user_id, email, ip_address, user_agent, success, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->execute([
            $userId,
            $email,
            cz_client_ip(),
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            $success ? 1 : 0
        ]);
    } catch (Throwable $e) {}
}

function cz_clear_failed_login_attempts(PDO $pdo, string $email): void
{
    try {
        $pdo->prepare('DELETE FROM login_attempts WHERE email = ? AND success = 0')->execute([$email]);
    } catch (Throwable $e) {}
}
