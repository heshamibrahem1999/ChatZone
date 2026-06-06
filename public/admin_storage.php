<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin/admin_common.php';

$user = cz_admin_require($pdo);
$userId = (int)$user['id'];

function cz_storage_format_bytes(int|float $bytes): string {
    $units = ['B','KB','MB','GB','TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) { $bytes /= 1024; $i++; }
    return number_format((float)$bytes, $i === 0 ? 0 : 2) . ' ' . $units[$i];
}

function cz_storage_scan(string $dir): array {
    $result = ['exists' => is_dir($dir), 'writable' => is_writable($dir), 'files' => 0, 'dirs' => 0, 'bytes' => 0, 'latest' => null];
    if (!is_dir($dir)) return $result;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isDir()) { $result['dirs']++; continue; }
        $result['files']++;
        $result['bytes'] += (int)$file->getSize();
        $mtime = (int)$file->getMTime();
        if ($result['latest'] === null || $mtime > $result['latest']) $result['latest'] = $mtime;
    }
    return $result;
}

function cz_storage_table_exists(PDO $pdo, string $table): bool {
    try { $st = $pdo->prepare('SHOW TABLES LIKE ?'); $st->execute([$table]); return (bool)$st->fetchColumn(); }
    catch (Throwable $e) { return false; }
}

function cz_count_db_media(PDO $pdo, string $table): int {
    if (!cz_storage_table_exists($pdo, $table)) return 0;
    try {
        $st = $pdo->query("SELECT COUNT(*) FROM `$table` WHERE COALESCE(file_path,'') <> ''");
        return (int)$st->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

$public = __DIR__;
$folders = [
    'Profile photos' => $public . '/uploads/profiles',
    'Private images' => $public . '/uploads/images',
    'Voice messages' => $public . '/uploads/voices',
    'Files/documents' => $public . '/uploads/files',
    'Group avatars' => $public . '/uploads/groups',
    'Temp uploads' => $public . '/uploads/tmp',
];

$rows = [];
$totalBytes = 0;
$totalFiles = 0;
foreach ($folders as $name => $path) {
    $scan = cz_storage_scan($path);
    $totalBytes += (int)$scan['bytes'];
    $totalFiles += (int)$scan['files'];
    $rows[] = ['name'=>$name, 'path'=>$path] + $scan;
}

$dbPrivateMedia = cz_count_db_media($pdo, 'messages');
$dbGroupMedia = cz_count_db_media($pdo, 'group_messages');

$missingFolders = array_filter($rows, fn($r) => !$r['exists']);
$notWritable = array_filter($rows, fn($r) => $r['exists'] && !$r['writable']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Storage - ChatZone</title>
<link rel="stylesheet" href="assets/css/extracted/public__admin_storage.css">
</head>
<body>
<div class="wrap">
    <div class="top">
        <div><h1>🗂️ Storage Manager</h1><div class="muted">Monitor upload folders and media usage.</div></div>
        <div class="actions"><a class="btn" href="admin_dashboard.php">Dashboard</a><a class="btn" href="admin_health.php">Health</a><a class="btn" href="chat.php">Chat</a></div>
    </div>

    <section class="grid">
        <div class="card"><div class="muted">Upload files</div><strong><?= (int)$totalFiles ?></strong></div>
        <div class="card"><div class="muted">Used storage</div><strong><?= e(cz_storage_format_bytes($totalBytes)) ?></strong></div>
        <div class="card"><div class="muted">DB private media</div><strong><?= (int)$dbPrivateMedia ?></strong></div>
        <div class="card"><div class="muted">DB group media</div><strong><?= (int)$dbGroupMedia ?></strong></div>
    </section>

    <?php if (!empty($missingFolders)): ?>
        <div class="notice"><b>Missing folders:</b> <?= e(implode(', ', array_map(fn($r) => $r['name'], $missingFolders))) ?></div>
    <?php endif; ?>
    <?php if (!empty($notWritable)): ?>
        <div class="notice"><b>Not writable:</b> <?= e(implode(', ', array_map(fn($r) => $r['name'], $notWritable))) ?>. Uploads may fail there.</div>
    <?php endif; ?>

    <table class="table">
        <thead><tr><th>Folder</th><th>Status</th><th>Files</th><th>Size</th><th>Latest file</th><th>Path</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><b><?= e($r['name']) ?></b></td>
                <td>
                    <?php if (!$r['exists']): ?><span class="badge bad">Missing</span>
                    <?php elseif (!$r['writable']): ?><span class="badge warn">Not writable</span>
                    <?php else: ?><span class="badge ok">Writable</span><?php endif; ?>
                </td>
                <td><?= (int)$r['files'] ?></td>
                <td><?= e(cz_storage_format_bytes((int)$r['bytes'])) ?></td>
                <td><?= $r['latest'] ? e(date('Y-m-d H:i', (int)$r['latest'])) : '<span class="muted">—</span>' ?></td>
                <td><code><?= e($r['path']) ?></code></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="notice">
        Safe cleanup rule: do not manually delete upload files unless you first export a backup and confirm the file is not used in `messages`, `group_messages`, or user profile/avatar fields.
    </div>
</div>
</body>
</html>
