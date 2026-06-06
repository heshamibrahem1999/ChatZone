<?php

function render_header(string $title, array $user = null): void {
    $theme = $user['theme'] ?? '#000000';
    $background = $user['background'] ?? '#ffffff';
    $textColor = $user['text_color'] ?? '#ffffff';
    $textBackground = $user['text_background'] ?? '#000000';
    $textSize = $user['text_size'] ?? '16px';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . e($title) . '</title>';
    echo '<link rel="stylesheet" href="assets/css/extracted/includes__core__rendering.css">';
    echo '<script defer src="assets/js/render-theme.js"></script>';
    echo '</head><body data-theme="' . e($theme) . '" data-bg="' . e($background) . '" data-text="' . e($textBackground) . '" data-chip="' . e($textColor) . '" data-size="' . e($textSize) . '"><div class="container">';
}

function render_footer(): void {
    echo '</div></body></html>';
}
