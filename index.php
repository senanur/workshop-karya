<?php

declare(strict_types=1);

define('KARYA_APP', true);

require __DIR__ . '/app/config.php';
require __DIR__ . '/app/security.php';
require __DIR__ . '/app/storage.php';
require __DIR__ . '/app/ratelimit.php';
require __DIR__ . '/app/sanitize.php';
require __DIR__ . '/app/foto.php';
require __DIR__ . '/app/render.php';
require __DIR__ . '/app/editor_view.php';

karya_ensure_dirs();

// A top-level const (unlike a function def) isn't hoisted — it must run
// before any routing branch below can call a handler that reads it.
const KARYA_ASET_CONTENT_TYPES = [
    'css' => 'text/css; charset=utf-8',
    'js' => 'text/javascript; charset=utf-8',
    'svg' => 'image/svg+xml',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$path = '/' . trim($path, '/');
$segments = $path === '/' ? [] : explode('/', substr($path, 1));

// --- static assets: /aset/<...> --------------------------------------

if (($method === 'GET' || $method === 'HEAD') && ($segments[0] ?? '') === 'aset' && count($segments) > 1) {
    karya_handle_aset(array_slice($segments, 1), $method === 'HEAD');
}

// --- a child's own photos: /<slug>/foto/<berkas> ----------------------

if (($method === 'GET' || $method === 'HEAD') && count($segments) === 3 && $segments[1] === 'foto') {
    karya_handle_foto($segments[0], $segments[2], $method === 'HEAD');
}

// --- app UI pages -------------------------------------------------------

if ($method === 'GET' && count($segments) === 1 && $segments[0] === 'masuk') {
    karya_send_app_page_headers();
    echo karya_render_masuk_page();
    exit;
}

if ($method === 'GET' && count($segments) === 2 && $segments[1] === 'edit') {
    $slug = $segments[0];
    if (!karya_slug_is_valid($slug)) {
        http_response_code(404);
        exit;
    }
    karya_send_app_page_headers();
    echo karya_render_editor_page($slug);
    exit;
}

// --- JSON API -------------------------------------------------------------

if ($method === 'POST' && $path === '/api/buka') {
    karya_handle_buka();
}

if ($method === 'POST' && $path === '/api/terbit') {
    karya_handle_terbit();
}

if ($method === 'POST' && $path === '/api/foto') {
    karya_handle_unggah_foto();
}

if ($method === 'POST' && $path === '/api/versi') {
    karya_handle_versi();
}

// --- a child's public page: /<slug> ----------------------------------

if (($method === 'GET' || $method === 'HEAD') && count($segments) === 1) {
    karya_handle_show_slug($segments[0], $method === 'HEAD');
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Not found';
exit;

// --- route handlers ---------------------------------------------------

function karya_handle_show_slug(string $slug, bool $headOnly = false): never
{
    if (!karya_slug_is_valid($slug)) {
        http_response_code(404);
        exit;
    }

    karya_send_child_page_headers();

    $indexPath = karya_index_path($slug);
    $path = is_file($indexPath)
        ? $indexPath
        : KARYA_SISTEM_DIR . DIRECTORY_SEPARATOR . 'belum-ada.html'; // valid slug, nothing published yet — still HTTP 200 per spec

    if (!$headOnly) {
        readfile($path);
    }
    exit;
}

function karya_handle_aset(array $relSegments, bool $headOnly): never
{
    foreach ($relSegments as $seg) {
        if ($seg === '' || $seg === '.' || $seg === '..') {
            http_response_code(404);
            exit;
        }
    }

    $rel = implode(DIRECTORY_SEPARATOR, $relSegments);
    $path = KARYA_ASET_DIR . DIRECTORY_SEPARATOR . $rel;
    $real = realpath($path);
    $realBase = realpath(KARYA_ASET_DIR);

    if ($real === false || $realBase === false || !str_starts_with($real, $realBase . DIRECTORY_SEPARATOR)) {
        http_response_code(404);
        exit;
    }

    $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
    $type = KARYA_ASET_CONTENT_TYPES[$ext] ?? null;
    if ($type === null || !is_file($real)) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: ' . $type);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=3600');
    if (!$headOnly) {
        readfile($real);
    }
    exit;
}

function karya_handle_foto(string $slug, string $berkas, bool $headOnly): never
{
    if (!karya_slug_is_valid($slug) || !preg_match('/^[A-Za-z0-9_.-]+$/', $berkas)) {
        http_response_code(404);
        exit;
    }

    $childPath = karya_slug_dir($slug) . DIRECTORY_SEPARATOR . 'foto' . DIRECTORY_SEPARATOR . $berkas;
    $fallbackPath = KARYA_ASET_DIR . DIRECTORY_SEPARATOR . 'foto' . DIRECTORY_SEPARATOR . $berkas;
    $path = is_file($childPath) ? $childPath : $fallbackPath;

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $type = KARYA_ASET_CONTENT_TYPES[$ext] ?? null;
    if ($type === null || !is_file($path)) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: ' . $type);
    header('X-Content-Type-Options: nosniff');
    if (!$headOnly) {
        readfile($path);
    }
    exit;
}

// Verifies the edit code for every authenticated route, or ends the request.
// $status/$pesan differ per route: /api/buka folds "unknown slug" and "wrong
// code" into one 404 so the reply never reveals which slugs exist, while the
// others answer 401 because the editor already knows the slug is real.
// A wrong attempt is only recorded against slugs that actually exist —
// guessing at slugs that were never seeded reveals nothing and shouldn't be
// able to litter the rate-limit directory.
function karya_verifikasi_kode(string $slug, string $kode, int $status, string $pesan): array
{
    if (!karya_ratelimit_check_wrong_attempts($slug)) {
        karya_json_error(429, 'Terlalu banyak percobaan kode salah. Coba lagi beberapa menit lagi.');
    }

    $meta = karya_load_meta($slug);
    if ($meta === null || !isset($meta['kode_hash']) || !password_verify($kode, (string) $meta['kode_hash'])) {
        if ($meta !== null) {
            karya_ratelimit_record_wrong_attempt($slug);
        }
        karya_json_error($status, $pesan);
    }

    return $meta;
}

function karya_handle_buka(): never
{
    $body = karya_json_body();
    $slug = is_string($body['slug'] ?? null) ? strtolower(trim($body['slug'])) : '';
    $kode = is_string($body['kode'] ?? null) ? trim($body['kode']) : '';

    if (!karya_slug_is_valid($slug) || $kode === '') {
        karya_json_error(404, 'Slug atau kode tidak dikenal.');
    }

    karya_verifikasi_kode($slug, $kode, 404, 'Slug atau kode tidak dikenal.');

    karya_json_ok([
        'isi' => karya_load_text(karya_isi_path($slug)) ?? '',
        'gaya' => karya_load_text(karya_gaya_path($slug)) ?? '',
        'templat' => karya_load_templat(),
        'versi' => karya_list_versi($slug),
        'url' => '/' . $slug,
    ]);
}

function karya_handle_versi(): never
{
    $body = karya_json_body();
    $slug = is_string($body['slug'] ?? null) ? strtolower(trim($body['slug'])) : '';
    $kode = is_string($body['kode'] ?? null) ? trim($body['kode']) : '';
    $id = is_string($body['id'] ?? null) ? trim($body['id']) : '';

    if (!karya_slug_is_valid($slug)) {
        karya_json_error(404, 'Slug tidak dikenal.');
    }

    karya_verifikasi_kode($slug, $kode, 401, 'Kode salah.');

    $versi = karya_load_versi($slug, $id);
    if ($versi === null) {
        karya_json_error(404, 'Versi itu tidak ada lagi.');
    }

    karya_json_ok($versi);
}

function karya_handle_unggah_foto(): never
{
    // A body over post_max_size arrives with $_POST and $_FILES both empty,
    // so there is nothing to read back — catch it before anything else.
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        karya_json_error(413, 'Fotonya terlalu besar. Maksimum 1 MB.');
    }

    $slug = is_string($_POST['slug'] ?? null) ? strtolower(trim($_POST['slug'])) : '';
    $kode = is_string($_POST['kode'] ?? null) ? trim($_POST['kode']) : '';

    if (!karya_slug_is_valid($slug)) {
        karya_json_error(404, 'Slug tidak dikenal.');
    }

    if (!karya_ratelimit_check_global()) {
        karya_json_error(429, 'Server sedang sibuk, coba lagi sebentar lagi.');
    }

    $meta = karya_verifikasi_kode($slug, $kode, 401, 'Kode salah.');

    $berkas = $_FILES['berkas'] ?? null;
    if (!is_array($berkas)) {
        karya_json_error(422, 'Tidak ada foto yang dikirim.');
    }

    $hasil = karya_simpan_foto($slug, $berkas);
    if ($hasil['ok'] !== true) {
        karya_json_error($hasil['status'], $hasil['pesan']);
    }

    $meta['jumlah_foto'] = karya_hitung_foto($slug);
    $meta['terakhir_ubah'] = gmdate('c');
    karya_save_meta($slug, $meta);

    karya_json_ok(['src' => $hasil['src']]);
}

function karya_handle_terbit(): never
{
    $body = karya_json_body();
    $slug = is_string($body['slug'] ?? null) ? strtolower(trim($body['slug'])) : '';
    $kode = is_string($body['kode'] ?? null) ? trim($body['kode']) : '';
    $isiIn = is_string($body['isi'] ?? null) ? $body['isi'] : '';
    $gayaIn = is_string($body['gaya'] ?? null) ? $body['gaya'] : '';

    if (!karya_slug_is_valid($slug)) {
        karya_json_error(404, 'Slug tidak dikenal.');
    }

    if (!karya_ratelimit_check_global()) {
        karya_json_error(429, 'Server sedang sibuk, coba lagi sebentar lagi.');
    }
    if (!karya_ratelimit_check_slug($slug)) {
        karya_json_error(429, 'Tunggu beberapa detik sebelum menerbitkan lagi.');
    }

    if (strlen($isiIn) > KARYA_MAX_ISI_BYTES || strlen($gayaIn) > KARYA_MAX_GAYA_BYTES) {
        karya_json_error(422, 'Kodenya kepanjangan.');
    }
    if (trim($isiIn) === '') {
        karya_json_error(422, 'Tab Isi tidak boleh kosong.');
    }

    $meta = karya_verifikasi_kode($slug, $kode, 401, 'Kode salah.');

    $isiSan = karya_sanitize_html($isiIn);
    $gayaSan = karya_sanitize_css($gayaIn);
    $peringatan = array_values(array_merge($isiSan['peringatan'], $gayaSan['peringatan']));

    $html = karya_render_child_page($slug, (string) ($meta['nama'] ?? $slug), $isiSan['html'], $gayaSan['css']);

    karya_save_text_atomic(karya_isi_path($slug), $isiSan['html']);
    karya_save_text_atomic(karya_gaya_path($slug), $gayaSan['css']);
    karya_publish_html($slug, $html);
    karya_save_versi($slug, $isiSan['html'], $gayaSan['css']);

    $meta['terakhir_ubah'] = gmdate('c');
    $meta['sudah_terbit'] = true;
    karya_save_meta($slug, $meta);

    karya_json_ok([
        'url' => '/' . $slug,
        'peringatan' => $peringatan,
        'versi' => karya_list_versi($slug),
    ]);
}
