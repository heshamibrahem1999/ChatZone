<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin/admin_common.php';

$user = cz_admin_require($pdo);
$userId = (int)$user['id'];

function cz_health_table_exists(PDO $pdo, string $table): bool {
    try { $s = $pdo->prepare('SHOW TABLES LIKE ?'); $s->execute([$table]); return (bool)$s->fetchColumn(); }
    catch (Throwable $e) { return false; }
}
function cz_count_rows(PDO $pdo, string $table): ?int {
    try { return (int)$pdo->query('SELECT COUNT(*) FROM `' . str_replace('`','',$table) . '`')->fetchColumn(); }
    catch (Throwable $e) { return null; }
}
function cz_size($bytes): string {
    $bytes = (float)$bytes;
    $units = ['B','KB','MB','GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units)-1) { $bytes /= 1024; $i++; }
    return number_format($bytes, $i === 0 ? 0 : 2) . ' ' . $units[$i];
}
function cz_check_dir(string $label, string $path): array {
    return ['label'=>$label,'path'=>$path,'exists'=>is_dir($path),'writable'=>is_dir($path) && is_writable($path),'size'=>is_dir($path) ? cz_folder_size($path) : 0];
}
function cz_folder_size(string $dir): int {
    $size = 0;
    try {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) { if ($file->isFile()) $size += $file->getSize(); }
    } catch (Throwable $e) {}
    return $size;
}
function badge(bool $ok, string $good='OK', string $bad='Problem'): string {
    return $ok ? '<span class="badge ok">'.$good.'</span>' : '<span class="badge bad">'.$bad.'</span>';
}

$requiredTables = ['users','friendships','messages','message_reactions','message_stars','groups','group_members','group_messages','reports','login_attempts','activity_logs','scheduled_messages'];
$tableRows = [];
foreach ($requiredTables as $t) $tableRows[] = ['name'=>$t,'exists'=>cz_health_table_exists($pdo,$t),'count'=>cz_count_rows($pdo,$t)];

$public = __DIR__;
$dirs = [
    cz_check_dir('Profile uploads', $public . '/uploads/profiles'),
    cz_check_dir('Image uploads', $public . '/uploads/messages'),
    cz_check_dir('Voice uploads', $public . '/uploads/voices'),
    cz_check_dir('Group uploads', $public . '/uploads/groups'),
    cz_check_dir('Files uploads', $public . '/uploads/files'),
];

$mailConfig = __DIR__ . '/../includes/mail_config.php';
$smtpEnabled = false;
$smtpUser = '';
if (is_file($mailConfig)) {
    require_once $mailConfig;
    $smtpEnabled = defined('CZ_SMTP_ENABLED') && CZ_SMTP_ENABLED;
    $smtpUser = defined('CZ_SMTP_USERNAME') ? CZ_SMTP_USERNAME : '';
}

$dbOk = true; $dbMessage = 'Connected';
try { $pdo->query('SELECT 1'); } catch (Throwable $e) { $dbOk=false; $dbMessage=$e->getMessage(); }

$phpChecks = [
    ['PHP version', PHP_VERSION, version_compare(PHP_VERSION, '8.0.0', '>=')],
    ['PDO MySQL', extension_loaded('pdo_mysql') ? 'Enabled' : 'Missing', extension_loaded('pdo_mysql')],
    ['Fileinfo', extension_loaded('fileinfo') ? 'Enabled' : 'Missing', extension_loaded('fileinfo')],
    ['OpenSSL', extension_loaded('openssl') ? 'Enabled' : 'Missing', extension_loaded('openssl')],
    ['Upload max filesize', ini_get('upload_max_filesize'), true],
    ['Post max size', ini_get('post_max_size'), true],
    ['Max execution time', ini_get('max_execution_time') . ' sec', true],
];

$errorLog = __DIR__ . '/../error_log';
$xamppLog = dirname(__DIR__) . '/apache/logs/error.log';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>System Health - ChatZone</title>
    <link rel="stylesheet" href="assets/css/extracted/public__admin_health.css">
</head>

<body>
    <div class="wrap">
        <div class="top">
            <div>
                <h1>🩺 ChatZone System Health</h1>
                <div class="muted">Quick admin checks for database, uploads, SMTP, PHP extensions, and project tables.
                </div>
            </div>
            <div><a class="btn" href="admin_dashboard.php">Dashboard</a> <a class="btn" href="chat.php">Chat</a></div>
        </div>

        <div class="grid">
            <div class="card">
                <h2>Database</h2>
                <div class="row"><span>Status</span><?= badge($dbOk) ?></div>
                <div class="muted"><?= e($dbMessage) ?></div>
            </div>
            <div class="card">
                <h2>SMTP</h2>
                <div class="row"><span>Email
                        sending</span><?= $smtpEnabled ? '<span class="badge ok">Enabled</span>' : '<span class="badge warn">Disabled</span>' ?>
                </div>
                <div class="muted"><?= e($smtpUser ?: 'No SMTP username configured') ?></div>
            </div>
            <div class="card">
                <h2>Cache</h2>
                <p class="muted">If UI looks old or broken, clear browser cache/service worker.</p>
                <a class="btn" href="clear_cache.php">Clear Cache</a>
            </div>

            <div class="card wide">
                <h2>PHP Checks</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Check</th>
                            <th>Value</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($phpChecks as $c): ?><tr>
                            <td><?= e($c[0]) ?></td>
                            <td><?= e((string)$c[1]) ?></td>
                            <td><?= badge((bool)$c[2]) ?></td>
                        </tr><?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card wide">
                <h2>Upload Folders</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Folder</th>
                            <th>Path</th>
                            <th>Writable</th>
                            <th>Size</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dirs as $d): ?><tr>
                            <td><?= e($d['label']) ?></td>
                            <td><code><?= e($d['path']) ?></code></td>
                            <td><?= badge($d['exists'] && $d['writable'], 'Writable', $d['exists'] ? 'Not writable' : 'Missing') ?>
                            </td>
                            <td><?= e(cz_size($d['size'])) ?></td>
                        </tr><?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card wide">
                <h2>Database Tables</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Table</th>
                            <th>Status</th>
                            <th>Rows</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tableRows as $t): ?><tr>
                            <td><?= e($t['name']) ?></td>
                            <td><?= badge($t['exists'], 'Exists', 'Missing') ?></td>
                            <td><?= $t['count'] === null ? '-' : (int)$t['count'] ?></td>
                        </tr><?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>