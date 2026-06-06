<?php
require_once __DIR__ . '/../includes/security.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['user_id'])) { echo json_encode(['success'=>false,'message'=>'Login first']); exit; }
require_csrf_or_json();
$upload = safe_audio_upload($_FILES['voice'] ?? [], __DIR__ . '/uploads/voices/', 'uploads/voices', 10*1024*1024);
echo json_encode(['success'=>$upload['ok'], 'result'=>$upload, 'files'=>$_FILES, 'folder'=>__DIR__.'/uploads/voices/', 'writable'=>is_writable(__DIR__.'/uploads/voices/')], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);