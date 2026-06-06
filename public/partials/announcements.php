<?php

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        title VARCHAR(160) NOT NULL,
        body TEXT NOT NULL,
        type ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_by INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_announcements_active (is_active, created_at)
    )");
    $annStmt = $pdo->query("SELECT id, title, body, type FROM announcements WHERE is_active = 1 ORDER BY created_at DESC, id DESC LIMIT 3");
    $activeAnnouncements = $annStmt->fetchAll();
} catch (Throwable $e) {
    $activeAnnouncements = [];
}
?>
<?php if (!empty($activeAnnouncements)): ?>
    <div class="site-announcements">
        <?php foreach ($activeAnnouncements as $a): ?>
            <div class="site-announcement site-announcement-<?= e($a['type'] ?? 'info') ?>" data-announcement-id="<?= (int)$a['id'] ?>">
                <div class="site-announcement-title">📢 <?= e($a['title']) ?></div>
                <div class="site-announcement-body"><?= nl2br(e($a['body'])) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
