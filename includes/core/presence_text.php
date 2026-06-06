<?php

function last_seen_text(?string $datetime, string $language = 'English'): string
{
    if (!$datetime) {
        return $language === 'Arabic' ? 'غير متصل' : ($language === 'French' ? 'Hors ligne' : 'Offline');
    }

    $last = strtotime($datetime);
    $diff = time() - $last;


    if ($diff < 60) {
        return $language === 'Arabic' ? 'آخر ظهور الآن' : ($language === 'French' ? 'Vu à l’instant' : 'Last seen just now');
    }

    $minutes = floor($diff / 60);

    if ($minutes < 60) {
        if ($language === 'Arabic') return "آخر ظهور منذ {$minutes} دقيقة";
        if ($language === 'French') return "Vu il y a {$minutes} min";
        return "Last seen {$minutes} min ago";
    }

    $hours = floor($minutes / 60);

    if ($hours < 24) {
        if ($language === 'Arabic') return "آخر ظهور منذ {$hours} ساعة";
        if ($language === 'French') return "Vu il y a {$hours} h";
        return "Last seen {$hours} h ago";
    }

    if ($language === 'Arabic') return 'غير متصل';
    if ($language === 'French') return 'Hors ligne';
    return 'Offline';
}
