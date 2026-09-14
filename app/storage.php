<?php

declare(strict_types=1);

if (!defined('KARYA_APP')) {
    http_response_code(404);
    exit;
}

function karya_slug_dir(string $slug): string
{
    return KARYA_DATA_DIR . DIRECTORY_SEPARATOR . $slug;
}

function karya_meta_path(string $slug): string
{
    return karya_slug_dir($slug) . DIRECTORY_SEPARATOR . 'meta.json';
}

function karya_index_path(string $slug): string
{
    return karya_slug_dir($slug) . DIRECTORY_SEPARATOR . 'index.html';
}

function karya_load_meta(string $slug): ?array
{
    $path = karya_meta_path($slug);
    if (!is_file($path)) {
        return null;
    }

    $raw = file_get_contents($path);
    $meta = $raw === false ? null : json_decode($raw, true);

    return is_array($meta) ? $meta : null;
}

// Writes meta.json atomically (tmp file + rename) so a concurrent reader
// never observes a half-written file.
function karya_save_meta(string $slug, array $meta): void
{
    $dir = karya_slug_dir($slug);
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }

    $path = karya_meta_path($slug);
    $tmp = $path . '.tmp';
    $json = json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    file_put_contents($tmp, $json, LOCK_EX);
    rename($tmp, $path);
}

// Writes the child's published page atomically. A refresh that lands exactly
// mid-publish must see either the old page or the new one, never a partial one.
function karya_publish_html(string $slug, string $html): void
{
    $dir = karya_slug_dir($slug);
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }

    $path = karya_index_path($slug);
    $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';

    file_put_contents($tmp, $html, LOCK_EX);
    rename($tmp, $path);
}
