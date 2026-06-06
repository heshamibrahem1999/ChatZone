<?php

function cz_site_setting(PDO $pdo, string $key, ?string $default = null): ?string {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
            setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
            setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $stmt = $pdo->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string)$value;
    } catch (Throwable $e) {
        return $default;
    }
}

function cz_set_site_setting(PDO $pdo, string $key, string $value): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
        setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
        setting_value TEXT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $stmt = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, $value]);
}

function cz_maintenance_enabled(PDO $pdo): bool {
    return cz_site_setting($pdo, 'maintenance_enabled', '0') === '1';
}

function cz_maintenance_message(PDO $pdo): string {
    return cz_site_setting($pdo, 'maintenance_message', 'ChatZone is temporarily under maintenance. Please check back soon.') ?: 'ChatZone is temporarily under maintenance. Please check back soon.';
}

function cz_enforce_maintenance(PDO $pdo, array $user): void {
    if (!cz_maintenance_enabled($pdo) || cz_is_admin_user($user)) {
        return;
    }
    $page = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $allowed = ['maintenance.php', 'logout.php'];
    if (!in_array($page, $allowed, true)) {
        redirect('maintenance.php');
    }
}
