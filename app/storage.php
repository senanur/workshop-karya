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

function karya_versi_dir(string $slug): string
{
    return karya_slug_dir($slug) . DIRECTORY_SEPARATOR . 'versi';
}

function karya_foto_dir(string $slug): string
{
    return karya_slug_dir($slug) . DIRECTORY_SEPARATOR . 'foto';
}

// Jalur Scratch (PRD-jalur-scratch §4): karya.sb3 is the published, reassembled
// file; draf.sb3 is the last upload that hasn't been published yet. Versions live
// in the same versi/ directory as the web track's snapshots, under their own
// naming scheme so the two never collide.
function karya_sb3_path(string $slug): string
{
    return karya_slug_dir($slug) . DIRECTORY_SEPARATOR . 'karya.sb3';
}

function karya_draf_sb3_path(string $slug): string
{
    return karya_slug_dir($slug) . DIRECTORY_SEPARATOR . 'draf.sb3';
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

// Same atomic tmp-file-then-rename pattern as karya_save_text_atomic, for
// binary payloads — a .sb3 upload is a zip,and must never be half-written on
// disk where a concurrent reader could observe it. ($tmp and $path must sit on
// the same filesystem for rename() to be atomic — they do: both in the child's dir.)
function karya_save_binary_atomic(string $path, string $data): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }

    $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';

    file_put_contents($tmp, $data, LOCK_EX);
    rename($tmp, $path);
}

// Keeps the last KARYA_MAX_VERSI_SB3 published .sb3 snapshots, newest kept,
// oldest pruned. Unlike the web track the whole file is one version, not a
// pair of text files, so it gets its own save path rather than reusing
// karya_save_versi().
function karya_save_versi_sb3(string $slug, string $data): void
{
    $dir = karya_versi_dir($slug);
    $id = gmdate('Ymd-His');

    karya_save_binary_atomic($dir . DIRECTORY_SEPARATOR . $id . '.sb3', $data);

    $ids = karya_sb3_versi_ids($slug);
    foreach (array_slice($ids, KARYA_MAX_VERSI_SB3)as $lama) {
        @unlink($dir . DIRECTORY_SEPARATOR . $lama . '.sb3');
    }
}

/** @return string[] .sb3 version ids, newest first */
function karya_sb3_versi_ids(string $slug): array
{
    $dir = karya_versi_dir($slug);
    if (!is_dir($dir)) {
        return [];
    }

    $ids = [];
    foreach ((array) scandir($dir)as $nama) {
        if (is_string($nama) && preg_match('/^(\d{8}-\d{6})\.sb3$/', $nama, $m)) {
            $ids[] = $m[1];
        }
    }

    rsort($ids);

    return $ids;
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

// A version id is the publish timestamp: sortable, human-readable on disk, and
// collision-free per child because publishing is rate-limited to 1/3s per slug.
function karya_versi_id_is_valid(string $id): bool
{
    return (bool) preg_match('/^\d{8}-\d{6}$/', $id);
}

// Keeps the last KARYA_MAX_VERSI published snapshots, newest kept, oldest
// pruned. Stores the sanitized text, i.e. exactly what was published.
function karya_save_versi(string $slug, string $isi, string $gaya): void
{
    $dir = karya_versi_dir($slug);
    $id = gmdate('Ymd-His');

    karya_save_text_atomic($dir . DIRECTORY_SEPARATOR . $id . '-isi.html', $isi);
    karya_save_text_atomic($dir . DIRECTORY_SEPARATOR . $id . '-gaya.css', $gaya);

    $ids = karya_versi_ids($slug);
    foreach (array_slice($ids, KARYA_MAX_VERSI) as $lama) {
        @unlink($dir . DIRECTORY_SEPARATOR . $lama . '-isi.html');
        @unlink($dir . DIRECTORY_SEPARATOR . $lama . '-gaya.css');
    }
}

/** @return string[] version ids, newest first */
function karya_versi_ids(string $slug): array
{
    $dir = karya_versi_dir($slug);
    if (!is_dir($dir)) {
        return [];
    }

    $ids = [];
    foreach ((array) scandir($dir) as $nama) {
        if (is_string($nama) && preg_match('/^(\d{8}-\d{6})-isi\.html$/', $nama, $m)) {
            $ids[] = $m[1];
        }
    }

    rsort($ids); // ids are zero-padded timestamps, so string sort is time sort

    return $ids;
}

/** @return list<array{id: string, waktu: string}> newest first, for the editor list */
function karya_list_versi(string $slug): array
{
    $daftar = [];
    foreach (array_slice(karya_versi_ids($slug), 0, KARYA_MAX_VERSI) as $id) {
        // 20260918-074512 -> 2026-09-18T07:45:12Z, so the browser can format it
        $daftar[] = [
            'id' => $id,
            'waktu' => substr($id, 0, 4) . '-' . substr($id, 4, 2) . '-' . substr($id, 6, 2)
                . 'T' . substr($id, 9, 2) . ':' . substr($id, 11, 2) . ':' . substr($id, 13, 2) . 'Z',
        ];
    }

    return $daftar;
}

/** @return array{isi: string, gaya: string}|null */
function karya_load_versi(string $slug, string $id): ?array
{
    if (!karya_versi_id_is_valid($id)) {
        return null;
    }

    $dir = karya_versi_dir($slug);
    $isi = karya_load_text($dir . DIRECTORY_SEPARATOR . $id . '-isi.html');
    if ($isi === null) {
        return null;
    }

    return [
        'isi' => $isi,
        'gaya' => karya_load_text($dir . DIRECTORY_SEPARATOR . $id . '-gaya.css') ?? '',
    ];
}
