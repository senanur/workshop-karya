<?php

declare(strict_types=1);

if (!defined('KARYA_APP')) {
    http_response_code(404);
    exit;
}

// No DB/Redis is available, and PHP-CGI/php-fpm workers share no process
// memory between requests, so rate limiting has to live on disk. Every check
// is guarded by flock() to stay correct under concurrent publishes.

// The web track publishes at most 1/3s per slug. The Scratch track uploads at
// most 1/10s per slug (§5 PRD-jalur-scratch) — the same lock file per slug
// serves both since a slug is either web or scratch, never both.
function karya_ratelimit_check_slug(string $slug, float $interval = 3.0): bool
{
    $path = KARYA_RATELIMIT_DIR . DIRECTORY_SEPARATOR . 'slug-' . $slug . '.lock';
    $fh = fopen($path, 'c+');
    if ($fh === false) {
        return true; // fail open: never block publishing because of a filesystem hiccup
    }

    flock($fh, LOCK_EX);
    $last = (float) fread($fh, 64);
    $now = microtime(true);
    $allowed = ($now - $last) >= $interval;

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

// Guards password_verify() calls against guessing a 6-char edit code:
// 10 attempts / 5 minutes per slug (§6). Call _check() before verifying the
// code, and _record() only when the code turned out to be wrong — a correct
// code never counts against the window.
function karya_ratelimit_check_wrong_attempts(string $slug): bool
{
    $path = KARYA_RATELIMIT_DIR . DIRECTORY_SEPARATOR . 'kode-' . $slug . '.log';
    $fh = fopen($path, 'c+');
    if ($fh === false) {
        return true;
    }

    flock($fh, LOCK_SH);
    $now = microtime(true);
    $windowStart = $now - 300.0;
    $count = 0;
    rewind($fh);
    while (($line = fgets($fh)) !== false) {
        if ((float) trim($line) >= $windowStart) {
            $count++;
        }
    }
    flock($fh, LOCK_UN);
    fclose($fh);

    return $count < 10;
}

function karya_ratelimit_record_wrong_attempt(string $slug): void
{
    $path = KARYA_RATELIMIT_DIR . DIRECTORY_SEPARATOR . 'kode-' . $slug . '.log';
    $fh = fopen($path, 'c+');
    if ($fh === false) {
        return;
    }

    flock($fh, LOCK_EX);
    $now = microtime(true);
    $windowStart = $now - 300.0;

    $lines = [];
    rewind($fh);
    while (($line = fgets($fh)) !== false) {
        $ts = (float) trim($line);
        if ($ts >= $windowStart) {
            $lines[] = $ts;
        }
    }
    $lines[] = $now;

    ftruncate($fh, 0);
    rewind($fh);
    foreach ($lines as $ts) {
        fwrite($fh, sprintf("%.6f\n", $ts));
    }
    fflush($fh);

    flock($fh, LOCK_UN);
    fclose($fh);
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

// Guards POST /admin/seed's token check — stricter than the per-child kode
// window (5/15min vs 10/5min) because a leaked or guessed admin token lets
// someone create arbitrary children across every school, not just take over
// one slug. Global (one log file), not per-slug, since there's only one token.
function karya_ratelimit_check_admin_wrong_attempts(): bool
{
    $path = KARYA_RATELIMIT_DIR . DIRECTORY_SEPARATOR . 'admin-token.log';
    $fh = fopen($path, 'c+');
    if ($fh === false) {
        return true;
    }

    flock($fh, LOCK_SH);
    $now = microtime(true);
    $windowStart = $now - 900.0;
    $count = 0;
    rewind($fh);
    while (($line = fgets($fh)) !== false) {
        if ((float) trim($line) >= $windowStart) {
            $count++;
        }
    }
    flock($fh, LOCK_UN);
    fclose($fh);

    return $count < 5;
}

function karya_ratelimit_record_admin_wrong_attempt(): void
{
    $path = KARYA_RATELIMIT_DIR . DIRECTORY_SEPARATOR . 'admin-token.log';
    $fh = fopen($path, 'c+');
    if ($fh === false) {
        return;
    }

    flock($fh, LOCK_EX);
    $now = microtime(true);
    $windowStart = $now - 900.0;

    $lines = [];
    rewind($fh);
    while (($line = fgets($fh)) !== false) {
        $ts = (float) trim($line);
        if ($ts >= $windowStart) {
            $lines[] = $ts;
        }
    }
    $lines[] = $now;

    ftruncate($fh, 0);
    rewind($fh);
    foreach ($lines as $ts) {
        fwrite($fh, sprintf("%.6f\n", $ts));
    }
    fflush($fh);

    flock($fh, LOCK_UN);
    fclose($fh);
}

// Scratch uploads are allowed at most 20 per minute globally, on their own
// counter — a .sb3 is far bigger than a web publish, so mixing it into the
// web's 60/min global counter (and vice versa) would let either track starve
//the other.
function karya_ratelimit_check_unggah_global(): bool
{
    $path = KARYA_RATELIMIT_DIR . DIRECTORY_SEPARATOR . 'unggah-global.log';
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

    $allowed = count($lines) < 20;
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
