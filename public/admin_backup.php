<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin/admin_common.php';
require_once __DIR__ . '/../includes/activity.php';

$user = cz_admin_require($pdo);
$userId = (int)$user['id'];

function cz_backup_quote_identifier(string $name): string {
    return '`' . str_replace('`', '``', $name) . '`';
}

function cz_backup_sql_value($value): string {
    if ($value === null) return 'NULL';
    if (is_int($value) || is_float($value)) return (string)$value;
    return "'" . str_replace(["\\", "'", "\0", "\n", "\r", "\x1a"], ["\\\\", "\\'", "\\0", "\\n", "\\r", "\\Z"], (string)$value) . "'";
}

function cz_get_tables(PDO $pdo): array {
    $tables = [];
    $stmt = $pdo->query('SHOW FULL TABLES');
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        if (($row[1] ?? '') === 'BASE TABLE') {
            $tables[] = $row[0];
        }
    }
    return $tables;
}

function cz_export_database(PDO $pdo, string $dbname): string {
    $tables = cz_get_tables($pdo);
    $out = [];
    $out[] = '-- ChatZone database backup';
    $out[] = '-- Generated: ' . date('Y-m-d H:i:s');
    $out[] = '-- Database: ' . $dbname;
    $out[] = 'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";';
    $out[] = 'SET time_zone = "+00:00";';
    $out[] = 'SET FOREIGN_KEY_CHECKS = 0;';
    $out[] = '';

    foreach ($tables as $table) {
        $quotedTable = cz_backup_quote_identifier($table);
        $out[] = '-- --------------------------------------------------------';
        $out[] = '-- Table structure for ' . $quotedTable;
        $out[] = 'DROP TABLE IF EXISTS ' . $quotedTable . ';';

        $stmtCreate = $pdo->query('SHOW CREATE TABLE ' . $quotedTable);
        $createRow = $stmtCreate->fetch(PDO::FETCH_ASSOC);
        $createSql = $createRow['Create Table'] ?? array_values($createRow)[1] ?? '';
        $out[] = $createSql . ';';
        $out[] = '';

        $count = (int)$pdo->query('SELECT COUNT(*) FROM ' . $quotedTable)->fetchColumn();
        if ($count === 0) {
            $out[] = '-- No data for ' . $quotedTable;
            $out[] = '';
            continue;
        }

        $out[] = '-- Data for ' . $quotedTable;
        $stmtRows = $pdo->query('SELECT * FROM ' . $quotedTable);
        $columns = [];
        for ($i = 0; $i < $stmtRows->columnCount(); $i++) {
            $meta = $stmtRows->getColumnMeta($i);
            $columns[] = cz_backup_quote_identifier($meta['name']);
        }
        $columnList = implode(', ', $columns);

        $batch = [];
        $batchSize = 80;
        while ($row = $stmtRows->fetch(PDO::FETCH_NUM)) {
            $values = array_map('cz_backup_sql_value', $row);
            $batch[] = '(' . implode(', ', $values) . ')';
            if (count($batch) >= $batchSize) {
                $out[] = 'INSERT INTO ' . $quotedTable . ' (' . $columnList . ') VALUES';
                $out[] = implode(",\n", $batch) . ';';
                $batch = [];
            }
        }
        if ($batch) {
            $out[] = 'INSERT INTO ' . $quotedTable . ' (' . $columnList . ') VALUES';
            $out[] = implode(",\n", $batch) . ';';
        }
        $out[] = '';
    }

    $out[] = 'SET FOREIGN_KEY_CHECKS = 1;';
    return implode("\n", $out) . "\n";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('csrf_verify')) {
        csrf_verify();
    } elseif (function_exists('verify_csrf')) {
        verify_csrf();
    } elseif (!empty($_POST['csrf_token']) && !empty($_SESSION['csrf_token']) && !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }

    $sql = cz_export_database($pdo, $dbname ?? 'chatzone');
    cz_activity_log($pdo, (int)$user['id'], 'database_backup_download', 'database', null, 'Downloaded full SQL backup');
    $filename = 'chatzone-backup-' . date('Y-m-d-His') . '.sql';
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($sql));
    echo $sql;
    exit;
}

$tables = [];
try {
    foreach (cz_get_tables($pdo) as $table) {
        $count = (int)$pdo->query('SELECT COUNT(*) FROM ' . cz_backup_quote_identifier($table))->fetchColumn();
        $tables[] = ['name' => $table, 'rows' => $count];
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Database Backup - ChatZone</title>
    <link rel="stylesheet" href="assets/css/chat.css?v=20260530-cachefix-1">
    <link rel="stylesheet" href="assets/css/extracted/public__admin_backup.css">
</head>

<body>
    <main class="wrap">
        <div class="top">
            <div>
                <h1>🧰 Database Backup</h1>
                <p class="muted">Export all ChatZone tables as one SQL file before testing or updating.</p>
            </div>
            <div>
                <a class="btn secondary" href="admin_dashboard.php">← Dashboard</a>
                <a class="btn secondary" href="chat.php">Chat</a>
            </div>
        </div>

        <div class="warn">Keep the downloaded SQL file private. It may contain users, messages, reports, and uploaded
            file paths.</div>

        <section class="card">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="btn" type="submit">⬇️ Download SQL Backup</button>
            </form>
        </section>

        <section class="card">
            <h2>Tables included</h2>
            <?php if (!empty($error ?? '')): ?><p class="warn"><?= e($error) ?></p><?php endif; ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Table</th>
                        <th>Rows</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tables as $t): ?>
                    <tr>
                        <td><?= e($t['name']) ?></td>
                        <td><?= (int)$t['rows'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
</body>

</html>