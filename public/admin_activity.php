<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin/admin_common.php';
require_once __DIR__ . '/../includes/activity.php';

$user = require_login($pdo);
cz_require_admin($pdo, $user);
cz_activity_ensure_table($pdo);

$action = trim((string)($_GET['action'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
if ($action !== '') { $where[] = 'al.action = ?'; $params[] = $action; }
if ($q !== '') {
    $where[] = '(al.details LIKE ? OR al.target_type LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs al LEFT JOIN users u ON u.id = al.user_id $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$sql = "SELECT al.*, u.first_name, u.last_name, u.email
        FROM activity_logs al
        LEFT JOIN users u ON u.id = al.user_id
        $whereSql
        ORDER BY al.created_at DESC, al.id DESC
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$actions = [];
try { $actions = $pdo->query('SELECT action, COUNT(*) c FROM activity_logs GROUP BY action ORDER BY c DESC, action ASC')->fetchAll(); } catch (Throwable $e) { $actions = []; }
$pages = max(1, (int)ceil($total / $perPage));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Activity Logs - ChatZone</title>
<link rel="stylesheet" href="assets/css/chat.css?v=20260530-cachefix-1">
<link rel="stylesheet" href="assets/css/extracted/public__admin_activity.css">
</head>
<body>
<main class="wrap">
  <div class="top">
    <div><h1>🧾 Activity Logs</h1><p class="muted">Admin audit trail for important actions.</p></div>
    <div><a class="btn" href="admin_dashboard.php">📊 Dashboard</a> <a class="btn" href="chat.php">← Chat</a></div>
  </div>
  <div class="card">
    <form class="filters" method="get">
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search details, target, user email/name">
      <select name="action"><option value="">All actions</option><?php foreach ($actions as $a): ?><option value="<?= e($a['action']) ?>" <?= $action===$a['action']?'selected':'' ?>><?= e($a['action']) ?> (<?= (int)$a['c'] ?>)</option><?php endforeach; ?></select>
      <button class="btn" type="submit">Filter</button><a class="btn btn-light" href="admin_activity.php">Reset</a>
    </form>
  </div>
  <div class="card">
    <p class="muted">Showing <?= count($logs) ?> of <?= $total ?> log entries.</p>
    <table class="table"><thead><tr><th>Time</th><th>User</th><th>Action</th><th class="hide-sm">Target</th><th>Details</th><th class="hide-sm">IP</th></tr></thead><tbody>
    <?php if (!$logs): ?><tr><td colspan="6">No logs yet.</td></tr><?php endif; ?>
    <?php foreach ($logs as $l): ?>
      <tr>
        <td><b><?= e($l['created_at'] ?? '') ?></b></td>
        <td><?= $l['user_id'] ? '<b>'.e(trim(($l['first_name'] ?? '').' '.($l['last_name'] ?? ''))).'</b><br><span class="muted">'.e($l['email'] ?? ('ID '.$l['user_id'])).'</span>' : '<span class="muted">System/Guest</span>' ?></td>
        <td><span class="badge"><?= e($l['action']) ?></span></td>
        <td class="hide-sm"><?= e((string)($l['target_type'] ?? '')) ?><?= $l['target_id'] ? ' #'.(int)$l['target_id'] : '' ?></td>
        <td class="details"><?= e((string)($l['details'] ?? '')) ?></td>
        <td class="hide-sm"><span class="muted"><?= e((string)($l['ip_address'] ?? '')) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table>
  </div>
  <div class="pager"><?php if($page>1): ?><a class="btn btn-light" href="?page=<?= $page-1 ?>&action=<?= urlencode($action) ?>&q=<?= urlencode($q) ?>">Previous</a><?php endif; ?><?php if($page<$pages): ?><a class="btn" href="?page=<?= $page+1 ?>&action=<?= urlencode($action) ?>&q=<?= urlencode($q) ?>">Next</a><?php endif; ?></div>
</main>
</body>
</html>
