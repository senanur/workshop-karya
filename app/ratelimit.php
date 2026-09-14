<?php

declare(strict_types=1);

if (!defined('KARYA_APP')) {
    http_response_code(404);
    exit;
}

// No DB/Redis is available, and PHP-CGI/php-fpm workers share no process
// memory between requests, so rate limiting has to live on disk. Every check
// is guarded by flock() to stay correct under concurrent publishes.

function karya_ratelimit_check_slug(string $slug): bool
{
    $path = KARYA_RATELIMIT_DIR . DIRECTORY_SEPARATOR . 'slug-' . $slug . '.lock';
    $fh = fopen($path, 'c+');
    if ($fh === false) {
        return true; // fail open: never block publishing because of a filesystem hiccup
    }

    flock($fh, LOCK_EX);
    $last = (float) fread($fh, 64);
    $now = microtime(true);
    $allowed = ($now - $last) >= 3.0;

    if ($allowed) {
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, (string) $now);
        fflush($fh);
    }

    flock($fh, LOCK_UN);
    fclose($fh);

    return $allowed;
}

function karya_ratelimit_check_global(): bool
{
    $path = KARYA_RATELIMIT_DIR . DIRECTORY_SEPARATOR . 'global.log';
    $fh = fopen($path, 'c+');
    if ($fh === false) {
        return true;
    }

    flock($fh, LOCK_EX);
    $now = microtime(true);
    $windowStart = $now - 60.0;

    $lines = [];
    rewind($fh);
    while (($line = fgets($fh)) !== false) {
        $ts = (float) trim($line);
        if ($ts >= $windowStart) {
            $lines[] = $ts;
        }
    }

    $allowed = count($lines) < 60;
    if ($allowed) {
        $lines[] = $now;
    }

    ftruncate($fh, 0);
    rewind($fh);
    foreach ($lines as $ts) {
        fwrite($fh, sprintf("%.6f\n", $ts));
    }
    fflush($fh);

    flock($fh, LOCK_UN);
    fclose($fh);

    return $allowed;
}
