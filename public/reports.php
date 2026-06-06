<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/activity.php';

$user = require_login($pdo);
$userId = (int)$user['id'];


try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_admin TINYINT(1) NOT NULL DEFAULT 0");
} catch (Throwable $e) {
    
}

$stmtAdmin = $pdo->prepare('SELECT is_admin FROM users WHERE id = ? LIMIT 1');
$stmtAdmin->execute([$userId]);
$isAdmin = (int)($stmtAdmin->fetchColumn() ?: 0);

if ($isAdmin !== 1) {
    http_response_code(403);
    echo 'Access denied. Your user id is ' . $userId . '. Set users.is_admin = 1 for this id.';
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT NOT NULL,
    reported_user_id INT DEFAULT NULL,
    message_id INT DEFAULT NULL,
    friendship_id INT DEFAULT NULL,
    reason TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reporter (reporter_id),
    INDEX idx_reported_user (reported_user_id),
    INDEX idx_message (message_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_or_redirect('reports.php');
    $reportId = (int)($_POST['report_id'] ?? 0);
    $status = (string)($_POST['status'] ?? '');
    if ($reportId > 0 && in_array($status, ['open', 'reviewed', 'closed'], true)) {
        $stmt = $pdo->prepare('UPDATE reports SET status = ? WHERE id = ?');
        $stmt->execute([$status, $reportId]);
        cz_activity_log($pdo, $userId, 'report_status_update', 'report', $reportId, 'Changed report status to ' . $status);
    }
    header('Location: reports.php');
    exit;
}

$statusFilter = $_GET['status'] ?? 'open';
if (!in_array($statusFilter, ['open', 'reviewed', 'closed', 'all'], true)) {
    $statusFilter = 'open';
}

$sql = "SELECT r.*, 
        CONCAT(rep.first_name, ' ', rep.last_name) AS reporter_name, rep.email AS reporter_email,
        CONCAT(bad.first_name, ' ', bad.last_name) AS reported_name, bad.email AS reported_email,
        m.body AS message_body, m.message_type, m.file_path
    FROM reports r
    LEFT JOIN users rep ON rep.id = r.reporter_id
    LEFT JOIN users bad ON bad.id = r.reported_user_id
    LEFT JOIN messages m ON m.id = r.message_id";
$params = [];
if ($statusFilter !== 'all') {
    $sql .= ' WHERE r.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY r.created_at DESC, r.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reports - ChatZone</title>
    <link rel="stylesheet" href="assets/css/chat.css?v=20260530-cachefix-1">
</head>

<body>
    <main class="reports-page-main">
        <div class="reports-header">
            <div>
                <h1>⚠️ Reports</h1>
                <p>Review reported users and messages.</p>
            </div>
            <a class="back-link" href="chat.php">← Back to chat</a>
        </div>

        <div class="report-tabs">
            <?php foreach (['open' => 'Open', 'reviewed' => 'Reviewed', 'closed' => 'Closed', 'all' => 'All'] as $key => $label): ?>
            <a class="media-tab <?= $statusFilter === $key ? 'active' : '' ?>"
                href="reports.php?status=<?= e($key) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($reports)): ?>
        <div class="media-empty">
            <div class="media-empty-icon">✅</div>
            <h2>No reports found</h2>
        </div>
        <?php else: ?>
        <div class="reports-list">
            <?php foreach ($reports as $report): ?>
            <article class="report-card">
                <div class="report-card-top">
                    <strong>#<?= (int)$report['id'] ?> · <?= e(ucfirst($report['status'])) ?></strong>
                    <span><?= e($report['created_at']) ?></span>
                </div>
                <div class="report-grid">
                    <div>
                        <b>Reporter:</b><br><?= e(trim(($report['reporter_name'] ?? '') ?: 'User')) ?><br><small><?= e($report['reporter_email'] ?? '') ?></small>
                    </div>
                    <div>
                        <b>Reported:</b><br><?= e(trim(($report['reported_name'] ?? '') ?: 'Unknown')) ?><br><small><?= e($report['reported_email'] ?? '') ?></small>
                    </div>
                </div>
                <div class="report-reason"><b>Reason:</b><br><?= nl2br(e($report['reason'])) ?></div>
                <?php if (!empty($report['message_id'])): ?>
                <div class="reported-message">
                    <b>Message
                        <?php if ($report['message_type'] === 'image' && $report['file_path']): ?>
                        <br><a href="<?= e($report['file_path']) ?>" target="_blank">Open image</a>
                        <?php elseif ($report['message_type'] === 'voice' && $report['file_path']): ?>
                        <br><audio controls preload="metadata" src="<?= e($report['file_path']) ?>"></audio>
                        <?php else: ?>
                        <br><?= nl2br(e($report['message_body'] ?? '')) ?>
                        <?php endif; ?>
                </div>
                <?php endif; ?>
                <form method="post" class="report-actions">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>">
                    <button name="status" value="open">Open</button>
                    <button name="status" value="reviewed">Reviewed</button>
                    <button name="status" value="closed">Close</button>
                </form>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</body>

</html>