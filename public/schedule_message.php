<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/blocking.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

function fail_schedule($msg) { echo json_encode(['success' => false, 'message' => $msg]); exit; }

if (empty($_SESSION['user_id'])) fail_schedule('Unauthorized');
require_csrf_or_json();

$userId = (int)$_SESSION['user_id'];
$friendId = (int)($_POST['friend_id'] ?? 0);
$body = trim($_POST['body'] ?? '');
$scheduledAtRaw = trim($_POST['scheduled_at'] ?? '');

if ($friendId <= 0) fail_schedule('Invalid friend');
if ($body === '') fail_schedule('Write a message first.');
if (mb_strlen($body) > 1000) fail_schedule('Message is too long.');

$ts = strtotime($scheduledAtRaw);
if (!$ts) fail_schedule('Invalid date/time.');
if ($ts <= time() + 30) fail_schedule('Choose a time at least 1 minute in the future.');
$scheduledAt = date('Y-m-d H:i:s', $ts);

$stmt = $pdo->prepare("SELECT id FROM friendships WHERE (user_one_id = ? AND user_two_id = ?) OR (user_one_id = ? AND user_two_id = ?) LIMIT 1");
$stmt->execute([$userId, $friendId, $friendId, $userId]);
$friendship = $stmt->fetch();
if (!$friendship) fail_schedule('Friendship not found');

if (!users_can_message($pdo, $userId, $friendId)) fail_schedule('You cannot schedule messages in a blocked chat.');

$pdo->exec("CREATE TABLE IF NOT EXISTS scheduled_messages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  friendship_id INT UNSIGNED NOT NULL,
  sender_id INT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  scheduled_at DATETIME NOT NULL,
  status ENUM('pending','sent','cancelled') NOT NULL DEFAULT 'pending',
  sent_message_id INT UNSIGNED DEFAULT NULL,
  sent_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_sched_due (status, scheduled_at),
  INDEX idx_sched_sender (sender_id, status),
  INDEX idx_sched_friendship (friendship_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$insert = $pdo->prepare("INSERT INTO scheduled_messages (friendship_id, sender_id, body, scheduled_at) VALUES (?, ?, ?, ?)");
$insert->execute([(int)$friendship['id'], $userId, $body, $scheduledAt]);

echo json_encode(['success' => true, 'message' => 'Message scheduled.', 'scheduled_at' => $scheduledAt]);
