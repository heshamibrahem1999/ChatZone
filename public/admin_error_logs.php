<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin/admin_common.php';

$user = cz_admin_require($pdo);
$userId = (int)$user['id'];

function cz_log_candidates(): array {
    $xamppRoot = dirname(dirname(dirname(__DIR__)));
    $paths = [];
    $iniLog = ini_get('error_log');
    if ($iniLog) $paths['PHP error_log setting'] = $iniLog;
    $paths['Project error_log'] = dirname(__DIR__) . '/error_log';
    $paths['Project public error_log'] = __DIR__ . '/error_log';
    $paths['Project PHP log'] = dirname(__DIR__) . '/logs/php_errors.log';
    $paths['XAMPP Apache error.log'] = $xamppRoot . '/apache/logs/error.log';
    $paths['XAMPP PHP error log'] = $xamppRoot . '/php/logs/php_error_log';
    return $paths;
}

function cz_tail_file(string $file, int $lines = 250): string {
    if (!is_file($file) || !is_readable($file)) return '';
    $data = @file($file, FILE_IGNORE_NEW_LINES);
    if (!$data) return '';
    return implode("\n", array_slice($data, -$lines));
}

$selectedKey = $_GET['log'] ?? '';
$logs = cz_log_candidates();
if (!$selectedKey || !isset($logs[$selectedKey])) {
    foreach ($logs as $k => $path) { if (is_file($path) && is_readable($path)) { $selectedKey = $k; break; } }
    if (!$selectedKey) $selectedKey = array_key_first($logs);
}
$selectedPath = $logs[$selectedKey] ?? '';
$contents = cz_tail_file($selectedPath, 300);
$status = is_file($selectedPath) ? (is_readable($selectedPath) ? 'Readable' : 'Not readable') : 'Not found';
$size = is_file($selectedPath) ? filesize($selectedPath) : 0;
function cz_log_size($bytes): string {
    $bytes = (float)$bytes; $units=['B','KB','MB','GB']; $i=0;
    while($bytes>=1024 && $i<count($units)-1){$bytes/=1024;$i++;}
    return number_format($bytes, $i===0?0:2).' '.$units[$i];
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Error Logs - ChatZone</title>
    <link rel="stylesheet" href="assets/css/extracted/public__admin_error_logs.css">
</head>

<body>
    <div class="wrap">
        <div class="top">
            <div>
                <h1>🧯 Error Logs</h1>
                <div class="muted">View the last 300 lines from common PHP/Apache log files.</div>
            </div>
            <div><a class="btn" href="admin_health.php">Health</a> <a class="btn"
                    href="admin_dashboard.php">Dashboard</a> <a class="btn" href="chat.php">Chat</a></div>
        </div>

        <div class="card">
            <form method="get">
                <label class="muted">Choose log file</label><br><br>
                <select name="log" onchange="this.form.submit()">
                    <?php foreach ($logs as $label => $path): ?>
                    <?php $exists = is_file($path) && is_readable($path); ?>
                    <option value="<?= e($label) ?>" <?= $label === $selectedKey ? 'selected' : '' ?>><?= e($label) ?> —
                        <?= $exists ? 'found' : 'missing' ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <div class="card">
            <div class="row"><span>Status</span><span
                    class="badge <?= $status === 'Readable' ? 'ok' : ($status === 'Not found' ? 'warn' : 'bad') ?>"><?= e($status) ?></span>
            </div>
            <div class="row"><span>Path</span><code><?= e($selectedPath) ?></code></div>
            <div class="row"><span>Size</span><span><?= e(cz_log_size((int)$size)) ?></span></div>
        </div>

        <div class="card">
            <h2>Latest lines</h2>
            <?php if ($contents === ''): ?>
            <p class="muted">No readable log content found here. Try another option above.</p>
            <?php else: ?>
            <pre><?= e($contents) ?></pre>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>