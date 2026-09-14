<?php

declare(strict_types=1);

define('KARYA_APP', true);

require __DIR__ . '/app/config.php';
require __DIR__ . '/app/security.php';
require __DIR__ . '/app/storage.php';
require __DIR__ . '/app/ratelimit.php';
require __DIR__ . '/app/blocks.php';
require __DIR__ . '/app/render.php';
require __DIR__ . '/app/editor_view.php';

karya_ensure_dirs();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$path = '/' . trim($path, '/');

if ($method === 'GET' && $path === '/bikin') {
    header('Content-Type: text/html; charset=utf-8');
    echo karya_render_editor_page();
    exit;
}

if ($method === 'POST' && $path === '/api/buka') {
    karya_handle_buka();
}

if ($method === 'POST' && $path === '/api/terbit') {
    karya_handle_terbit();
}

if (($method === 'GET' || $method === 'HEAD') && $path !== '/' && substr_count($path, '/') === 1) {
    karya_handle_show_slug(substr($path, 1), $method === 'HEAD');
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Not found';
exit;

// --- route handlers ---------------------------------------------------

function karya_handle_show_slug(string $slug, bool $headOnly = false): never
{
    karya_send_child_page_headers();

    if (!karya_slug_is_valid($slug)) {
        http_response_code(404);
        exit;
    }

    $indexPath = karya_index_path($slug);
    $path = is_file($indexPath)
        ? $indexPath
        : KARYA_SISTEM_DIR . DIRECTORY_SEPARATOR . 'belum-ada.html'; // valid slug, nothing published yet — still HTTP 200 per spec

    if (!$headOnly) {
        readfile($path);
    }
    exit;
}

function karya_handle_buka(): never
{
    $body = karya_json_body();
    $slug = is_string($body['slug'] ?? null) ? strtolower(trim($body['slug'])) : '';
    $kode = is_string($body['kode'] ?? null) ? trim($body['kode']) : '';

    if (!karya_slug_is_valid($slug) || $kode === '') {
        karya_json_error(404, 'Slug atau kode tidak dikenal.');
    }

    $meta = karya_load_meta($slug);
    if ($meta === null) {
        // Dev/testing convenience mirroring /api/terbit's auto-create: a slug
        // that doesn't exist yet but has a validly-formatted code opens as a
        // blank page. Nothing is written until the child actually publishes.
        // In production, slugs are pre-seeded (Bagian 7), so meta is never
        // null here and this branch never triggers.
        if (!karya_kode_is_valid($kode)) {
            karya_json_error(404, 'Slug atau kode tidak dikenal.');
        }
        karya_json_ok(['fields' => ['judul' => $slug, 'tentang' => ''], 'blocks' => []]);
    }

    if (!isset($meta['kode_hash']) || !password_verify($kode, (string) $meta['kode_hash'])) {
        // Same generic error as an unknown slug — never reveal that the slug
        // exists but the code was wrong.
        karya_json_error(404, 'Slug atau kode tidak dikenal.');
    }

    $konten = is_array($meta['konten'] ?? null) ? $meta['konten'] : [];
    karya_json_ok([
        'fields' => is_array($konten['fields'] ?? null) ? $konten['fields'] : ['judul' => $meta['nama'] ?? $slug, 'tentang' => ''],
        'blocks' => is_array($konten['blocks'] ?? null) ? $konten['blocks'] : [],
    ]);
}

function karya_handle_terbit(): never
{
    $body = karya_json_body();
    $slug = is_string($body['slug'] ?? null) ? strtolower(trim($body['slug'])) : '';
    $kode = is_string($body['kode'] ?? null) ? trim($body['kode']) : '';
    $fieldsIn = is_array($body['fields'] ?? null) ? $body['fields'] : [];
    $blocksIn = is_array($body['blocks'] ?? null) ? $body['blocks'] : [];

    if (!karya_slug_is_valid($slug)) {
        karya_json_error(404, 'Slug tidak valid.');
    }

    if (!karya_ratelimit_check_global()) {
        karya_json_error(429, 'Server sedang sibuk, coba lagi sebentar lagi.');
    }
    if (!karya_ratelimit_check_slug($slug)) {
        karya_json_error(429, 'Tunggu beberapa detik sebelum menerbitkan lagi.');
    }

    $meta = karya_load_meta($slug);

    if ($meta === null) {
        // Dev/testing convenience: first publish for a brand-new slug creates
        // it. Production slugs are pre-seeded (Bagian 7) before a session, so
        // this path is not expected to trigger once seeding exists.
        if (!karya_kode_is_valid($kode)) {
            karya_json_error(422, 'Kode harus 6 karakter huruf/angka (tanpa 0 O 1 l I).');
        }
        $now = gmdate('c');
        $meta = [
            'nama' => karya_clamp_text(is_string($fieldsIn['judul'] ?? null) ? $fieldsIn['judul'] : $slug, 60),
            'sekolah' => null,
            'kohort' => null,
            'kode_hash' => password_hash($kode, PASSWORD_BCRYPT),
            'dibuat' => $now,
            'terakhir_ubah' => $now,
        ];
    } elseif (!isset($meta['kode_hash']) || !password_verify($kode, (string) $meta['kode_hash'])) {
        karya_json_error(401, 'Kode salah.');
    }

    $fields = [
        'judul' => karya_clamp_text(is_string($fieldsIn['judul'] ?? null) ? $fieldsIn['judul'] : $slug, 60),
        'tentang' => karya_clamp_text(is_string($fieldsIn['tentang'] ?? null) ? $fieldsIn['tentang'] : '', 280),
    ];

    $catalog = karya_block_catalog();
    $selectedBlocks = [];
    $count = 0;
    foreach ($blocksIn as $blockId => $values) {
        if (++$count > 3 || !is_string($blockId) || !isset($catalog[$blockId]) || !is_array($values)) {
            continue;
        }
        $selectedBlocks[$blockId] = karya_block_field_values_from_input($catalog[$blockId], $values);
    }

    $html = karya_render_child_page($slug, $fields, $selectedBlocks);
    karya_publish_html($slug, $html);

    $meta['terakhir_ubah'] = gmdate('c');
    $meta['konten'] = ['fields' => $fields, 'blocks' => $selectedBlocks];
    karya_save_meta($slug, $meta);

    karya_json_ok(['url' => '/' . $slug]);
}
