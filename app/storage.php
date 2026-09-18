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

function karya_isi_path(string $slug): string
{
    return karya_slug_dir($slug) . DIRECTORY_SEPARATOR . 'isi.html';
}

function karya_gaya_path(string $slug): string
{
    return karya_slug_dir($slug) . DIRECTORY_SEPARATOR . 'gaya.css';
}

function karya_load_text(string $path): ?string
{
    if (!is_file($path)) {
        return null;
    }

    $raw = file_get_contents($path);

    return $raw === false ? null : $raw;
}

// Same atomic tmp-file-then-rename pattern as karya_save_meta/karya_publish_html,
// generalized so isi.html/gaya.css/index.html all go through one code path.
function karya_save_text_atomic(string $path, string $content): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }

    $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';

    file_put_contents($tmp, $content, LOCK_EX);
    rename($tmp, $path);
}

function karya_load_templat(): array
{
    return [
        'isi' => karya_load_text(KARYA_TEMPLAT_DIR . DIRECTORY_SEPARATOR . 'isi.html') ?? '',
        'gaya' => karya_load_text(KARYA_TEMPLAT_DIR . DIRECTORY_SEPARATOR . 'gaya.css') ?? '',
    ];
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
    $json = json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    karya_save_text_atomic(karya_meta_path($slug), $json);
}

// Writes the child's published page atomically. A refresh that lands exactly
// mid-publish must see either the old page or the new one, never a partial one.
function karya_publish_html(string $slug, string $html): void
{
    karya_save_text_atomic(karya_index_path($slug), $html);
}
