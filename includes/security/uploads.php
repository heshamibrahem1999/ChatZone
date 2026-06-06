<?php
function safe_image_upload(array $file, string $uploadDir, string $publicPrefix, int $maxBytes): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Upload failed'];
    }

    if (($file['size'] ?? 0) > $maxBytes) {
        return ['ok' => false, 'message' => 'Image is too large'];
    }

    $tmpName = $file['tmp_name'] ?? '';
    if (!is_uploaded_file($tmpName)) {
        return ['ok' => false, 'message' => 'Invalid upload'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpName);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime]) || getimagesize($tmpName) === false) {
        return ['ok' => false, 'message' => 'Only valid image files are allowed'];
    }

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        return ['ok' => false, 'message' => 'Upload folder cannot be created'];
    }

    $newFileName = time() . '_' . bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $destination = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $newFileName;

    if (!move_uploaded_file($tmpName, $destination)) {
        return ['ok' => false, 'message' => 'Upload failed'];
    }

    @chmod($destination, 0644);

    return [
        'ok' => true,
        'filename' => $newFileName,
        'path' => rtrim($publicPrefix, '/\\') . '/' . $newFileName,
    ];
}


function upload_error_message(int $error): string
{
    $map = [
        UPLOAD_ERR_INI_SIZE => 'The uploaded file is bigger than upload_max_filesize in PHP settings',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file is bigger than the form limit',
        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was received by PHP',
        UPLOAD_ERR_NO_TMP_DIR => 'PHP temporary upload folder is missing',
        UPLOAD_ERR_CANT_WRITE => 'PHP could not write the upload to disk',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload',
    ];
    return $map[$error] ?? ('Unknown upload error: ' . $error);
}

function safe_audio_upload(array $file, string $uploadDir, string $publicPrefix, int $maxBytes): array
{
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => upload_error_message($error), 'debug' => ['error_code' => $error]];
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0) {
        return ['ok' => false, 'message' => 'Voice message is empty', 'debug' => ['size' => $size]];
    }

    if ($size > $maxBytes) {
        return ['ok' => false, 'message' => 'Voice message is too large. Max is 10MB', 'debug' => ['size' => $size, 'max' => $maxBytes]];
    }

    $tmpName = $file['tmp_name'] ?? '';
    if (!$tmpName || !is_uploaded_file($tmpName)) {
        return ['ok' => false, 'message' => 'Invalid voice upload temp file', 'debug' => ['tmp_name' => $tmpName, 'is_uploaded_file' => is_uploaded_file($tmpName)]];
    }

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        return ['ok' => false, 'message' => 'Voice folder cannot be created', 'debug' => ['upload_dir' => $uploadDir]];
    }

    if (!is_writable($uploadDir)) {
        @chmod($uploadDir, 0755);
    }
    if (!is_writable($uploadDir)) {
        return ['ok' => false, 'message' => 'Voice folder is not writable. Set uploads/voices permission to 755 or 775.', 'debug' => ['upload_dir' => $uploadDir]];
    }

    $mime = 'application/octet-stream';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($tmpName);
        if (is_string($detected) && $detected !== '') {
            $mime = $detected;
        }
    }

    $originalName = strtolower((string)($file['name'] ?? ''));
    $extFromName = pathinfo($originalName, PATHINFO_EXTENSION);

    $allowed = [
        'audio/webm' => 'webm',
        'video/webm' => 'webm',
        'application/webm' => 'webm',
        'application/octet-stream' => $extFromName ?: 'webm',
        'audio/ogg' => 'ogg',
        'video/ogg' => 'ogg',
        'application/ogg' => 'ogg',
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/mp4' => 'm4a',
        'video/mp4' => 'm4a',
        'application/mp4' => 'm4a',
        'audio/aac' => 'aac',
        'audio/x-m4a' => 'm4a',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/vnd.wave' => 'wav',
    ];

    $allowedExts = ['webm', 'ogg', 'mp3', 'm4a', 'mp4', 'aac', 'wav'];
    if (isset($allowed[$mime])) {
        $ext = $allowed[$mime];
    } elseif (in_array($extFromName, $allowedExts, true)) {
        $ext = $extFromName === 'mp4' ? 'm4a' : $extFromName;
    } else {
        return [
            'ok' => false,
            'message' => 'Only valid audio files are allowed. Server detected: ' . $mime,
            'debug' => ['mime' => $mime, 'original_name' => $originalName]
        ];
    }

    $newFileName = time() . '_' . bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $newFileName;

    if (!move_uploaded_file($tmpName, $destination)) {
        return ['ok' => false, 'message' => 'Voice file could not be moved to uploads/voices', 'debug' => ['destination' => $destination, 'upload_dir' => $uploadDir]];
    }

    @chmod($destination, 0644);

    return [
        'ok' => true,
        'filename' => $newFileName,
        'path' => rtrim($publicPrefix, '/\\') . '/' . $newFileName,
        'debug' => ['mime' => $mime, 'size' => $size, 'saved_to' => $destination]
    ];
}

function safe_unlink_public_file(string $baseDir, ?string $relativePath): void
{
    if (!$relativePath) return;

    $base = realpath($baseDir);
    $file = realpath($baseDir . DIRECTORY_SEPARATOR . ltrim($relativePath, '/\\'));

    if ($base && $file && str_starts_with($file, $base) && is_file($file)) {
        unlink($file);
    }
}
