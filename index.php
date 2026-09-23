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
require __DIR__ . '/app/dinding.php';
require __DIR__ . '/app/editor_view.php';
require __DIR__ . '/app/pemutar_view.php';
require __DIR__ . '/app/unggah_view.php';
require __DIR__ . '/app/sb3.php';
require __DIR__ . '/app/seed.php';

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
    // A slug on the Scratch track has no web editor (§3 PRD-jalur-scratch): it
    // answers 404 rather than redirecting, so a misprinted card (whose slug
    // ended up on the wrong jalur) is caught at the drill, not mid-session.

    $metaEdit = karya_load_meta($slug);
    if ($metaEdit !== null && karya_meta_jalur($metaEdit) !== 'web') {
        http_response_code(404);
        exit;
    }
    karya_send_app_page_headers();
    echo karya_render_editor_page($slug);
    exit;
}

// /<slug>/unggah is the Scratch-track counterpart of /<slug>/edit (§3
// PRD-jalur-scratch). A web-track slug answers 404 rather than redirecting,
// exactly like /<slug>/edit rejects a Scratch slug — so a misprinted card is
// caught at the drill, not mid-session.
if ($method === 'GET' && count($segments) === 2 && $segments[1] === 'unggah') {
    $slug = $segments[0];
    if (!karya_slug_is_valid($slug)) {
        http_response_code(404);
        exit;
    }
    $metaUnggah = karya_load_meta($slug);
    if ($metaUnggah === null || karya_meta_jalur($metaUnggah) !== 'scratch') {
        http_response_code(404);
        exit;
    }
    karya_send_app_page_headers();
    echo karya_render_unggah_page($slug);
    exit;
}

// /masuk is a single gate for both tracks (§3): the form POSTs its slug+kode
// here,and the server sends back where to go — /<slug>/edit for the web track,or
// /<slug>/unggah for the Scratch track. A wrong slug/kode folds into one 404
// reply,same as /api/buka,sothe response never reveals which slugs exist.

if ($method === 'POST' && $path === '/masuk') {
    karya_handle_masuk();
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

if ($method === 'POST' && $path === '/api/unggah') {
    karya_handle_unggah_sb3();
}

if ($method === 'POST' && $path === '/api/terbit-sb3') {
    karya_handle_terbit_sb3();
}

// --- facilitator-only: import a roster CSV without server/SSH access ------
// (app/seed.php's karya_seed_proses_csv(), token-gated — see its handler
// below for why this exists and how the token is checked.)

if ($method === 'POST' && $path === '/admin/seed') {
    karya_handle_admin_seed();
}

// --- a child's published Scratch file: /<slug>/karya.sb3 ------------------

if (($method === 'GET' || $method === 'HEAD') && count($segments) === 2 && $segments[1] === 'karya.sb3') {
    $slugKarya = $segments[0];
    $headOnly = $method === 'HEAD';
    if (!karya_slug_is_valid($slugKarya)) {
        http_response_code(404);
        exit;
    }
    $metaSb3 = karya_load_meta($slugKarya);
    if ($metaSb3 === null || karya_meta_jalur($metaSb3) !== 'scratch'
        || (($metaSb3['disembunyikan'] ?? false) === true)) {
        http_response_code(404);
        exit;
    }
    $sb3Path = karya_sb3_path($slugKarya);
    if (!is_file($sb3Path)) {
        http_response_code(404);
        exit;
    }
    // The file is served as an opaque attachment, never as a document —
    // a browser that somehow received it must download it, not render it.
    header('Content-Type: application/octet-stream');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex');
    header('Content-Disposition: attachment; filename="karya.sb3"');
    if (!$headOnly) {
        readfile($sb3Path);
    }
    exit;
}

// --- school wall: /smp-<nama-sekolah> ----------------------------------

if (($method === 'GET' || $method === 'HEAD') && count($segments) === 1 && str_starts_with($segments[0], 'smp-')) {
    karya_handle_dinding($segments[0], $method === 'HEAD');
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

    $meta = karya_load_meta($slug);
    if ($meta !== null && karya_meta_jalur($meta) === 'scratch') {
        karya_handle_show_pemutar($slug, $meta, $headOnly);
    }

    karya_send_child_page_headers();

    $indexPath = karya_index_path($slug);
    // disembunyikan (a facilitator switch, §9) reverts a page to belum-ada.html
    // without touching its data — everything the child wrote stays on disk,
    // ready to reappear the moment the switch flips back.
    $tersembunyi = $meta !== null && ($meta['disembunyikan'] ?? false) === true;
    $path = (!$tersembunyi && is_file($indexPath))
        ? $indexPath
        : KARYA_SISTEM_DIR . DIRECTORY_SEPARATOR . 'belum-ada.html'; // valid slug, nothing published (or hidden) — still HTTP 200 per spec

    if (!$headOnly) {
        readfile($path);
    }
    exit;
}

// Jalur Scratch (PRD-jalur-scratch §6): /<slug> serves the player page. A
// hidden child (facilitator switch) or one who hasn't published yet answers
// with belum-ada.html at HTTP 200, same as the web track — hiding must never
// leak whether a slug exists.
function karya_handle_show_pemutar(string $slug, array $meta, bool $headOnly): never
{
    if (($meta['disembunyikan'] ?? false) === true || !is_file(karya_sb3_path($slug))) {
        $path = KARYA_SISTEM_DIR . DIRECTORY_SEPARATOR . 'belum-ada.html';
        karya_send_child_page_headers();
        if (!$headOnly) {
            readfile($path);
        }
        exit;
    }

    karya_send_pemutar_page_headers();
    if (!$headOnly) {
        echo karya_render_pemutar_page($slug, $meta);
    }
    exit;
}

function karya_handle_dinding(string $sekolahSlug, bool $headOnly): never
{
    if (!karya_sekolah_slug_is_valid($sekolahSlug)) {
        http_response_code(404);
        exit;
    }

    // Same headers as a child page (§9: noindex applies to "halaman anak dan
    // dinding karya" alike) even though this page carries no child markup at
    // all — every value on it is either a slug, a validated color, or text
    // that went through htmlspecialchars().
    karya_send_child_page_headers();

    if (!$headOnly) {
        echo karya_render_dinding($sekolahSlug);
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

// /masuk POST (Jalur Scratch §3): the single gate for both tracks verifies
// the code,and sends back the destination page per meta.jalur — so one card and
// one /masuk form serve both tracks,and a misprinted card is caught by which
// jalur the slug actually ended up on,not by a guess.

function karya_handle_masuk(): never
{
    $body = karya_json_body();
    $slug = is_string($body['slug'] ?? null) ? strtolower(trim($body['slug'])) : '';
    $kode = is_string($body['kode'] ?? null) ? trim($body['kode']) : '';

    if (!karya_slug_is_valid($slug) || $kode === '') {
        karya_json_error(404, 'Slug atau kode tidak dikenal.');
    }

    $meta = karya_verifikasi_kode($slug, $kode, 404, 'Slug atau kode tidak dikenal.');
    $tujuan = karya_meta_jalur($meta) === 'scratch'
        ? '/' . $slug . '/unggah'
        : '/' . $slug . '/edit';

    karya_json_ok(['tujuan' => $tujuan]);
}

function karya_handle_unggah_sb3(): never
{
    // A body over post_max_size arrives with $_POST and $_FILES both empty —
    // same as /api/foto — catch it before anything else.
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        karya_json_error(413, 'Berkasnya terlalu besar. Maksimum 20 MB.');
    }

    $slug = is_string($_POST['slug'] ?? null) ? strtolower(trim($_POST['slug'])) : '';
    $kode = is_string($_POST['kode'] ?? null) ? trim($_POST['kode']) : '';

    if (!karya_slug_is_valid($slug)) {
        karya_json_error(404, 'Slug tidak dikenal.');
    }

    if (!karya_ratelimit_check_unggah_global()) {

        karya_json_error(429, 'Server sedang sibuk, coba lagi sebentar lagi.');
    }
    if (!karya_ratelimit_check_slug($slug, 10.0)) {
        karya_json_error(429, 'Tunggu beberapa detik sebelum mengunggah lagi.');
    }

    $meta = karya_verifikasi_kode($slug, $kode, 401, 'Kode salah.');
    if (karya_meta_jalur($meta) !== 'scratch') {
        karya_json_error(422, 'Halaman ini bukan jalur Scratch.');
    }

    $berkas = $_FILES['berkas'] ?? null;
    if (!is_array($berkas)) {
        karya_json_error(422, 'Tidak ada berkas yang dikirim.');
    }

    $galat = $berkas['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($galat === UPLOAD_ERR_INI_SIZE || $galat === UPLOAD_ERR_FORM_SIZE) {

        karya_json_error(413, 'Berkasnya terlalu besar. Maksimum 20 MB.');
    }
    if ($galat !== UPLOAD_ERR_OK) {

        karya_json_error(422, 'Berkasnya gagal terkirim. Coba lagi.');
    }
    $tmp = (string) ($berkas['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        karya_json_error(422, 'Berkasnya gagal terkirim. Coba lagi.');
    }
    if ((int) ($berkas['size'] ?? 0) > KARYA_MAX_SB3_BYTES) {

        karya_json_error(413, 'Berkasnya terlalu besar. Maksimum 20 MB.');
    }

    $hasil = karya_sb3_validasi_dan_susun($tmp, $slug);
    if ($hasil['ok'] !== true) {
        @unlink($hasil['hasil'] ?? '');
        karya_json_error($hasil['status'], $hasil['pesan']);
    }

    $draf = karya_draf_sb3_path($slug);
    // rename() is atomic on the same filesystem (both sit in the child's dir),so
    // a concurrent reader never sees a half-written draf.sb3. On Windows an
    // existing target blocks rename,so unlink first (prod is Linux anyway).

    @unlink($draf);
    rename($hasil['hasil'], $draf);

    $meta['jalur'] = 'scratch';
    $meta['sb3_bytes'] = $hasil['bytes'];
    $meta['sb3_sha256'] = $hasil['sha256'];
    $meta['sb3_diunggah'] = gmdate('c');
    $meta['terakhir_ubah'] = gmdate('c');
    karya_save_meta($slug, $meta);

    karya_json_ok([
        'sha256' => $hasil['sha256'],
        'bytes' => $hasil['bytes'],
    ]);
}

function karya_handle_terbit_sb3(): never
{
    $body = karya_json_body();
    $slug = is_string($body['slug'] ?? null) ? strtolower(trim($body['slug'])) : '';
    $kode = is_string($body['kode'] ?? null) ? trim($body['kode']) : '';

    if (!karya_slug_is_valid($slug)) {
        karya_json_error(404, 'Slug tidak dikenal.');
    }

    if (!karya_ratelimit_check_unggah_global()) {


        karya_json_error(429, 'Server sedang sibuk, coba lagi sebentar lagi.');
    }
    if (!karya_ratelimit_check_slug($slug, 10.0)) {

        karya_json_error(429, 'Tunggu beberapa detik sebelum menerbitkan lagi.');
    }

    $meta = karya_verifikasi_kode($slug, $kode, 401, 'Kode salah.');
    if (karya_meta_jalur($meta) !== 'scratch') {
        karya_json_error(422, 'Halaman ini bukan jalur Scratch.');
    }

    $draf = karya_draf_sb3_path($slug);
    if (!is_file($draf)) {
        karya_json_error(422, 'Belum ada unggahan untuk diterbitkan.');
    }

    $data = file_get_contents($draf);
    if ($data === false) {
        karya_json_error(422, 'Unggahannya tidak bisa dibaca. Coba lagi.');
    }

    karya_save_binary_atomic(karya_sb3_path($slug), $data);
    karya_save_versi_sb3($slug, $data);

    $meta['terakhir_ubah'] = gmdate('c');
    $meta['sudah_terbit'] = true;
    karya_save_meta($slug, $meta);

    karya_json_ok([
        'url' => '/' . $slug,
        'versi' => karya_sb3_versi_ids($slug),
    ]);
}

// POST /admin/seed — lets a facilitator import a roster CSV over HTTP,
// without server/SSH access or fighting docker exec's shell (the runtime
// image is php:8.4-cli-alpine, which has no bash — only sh). Gated by
// KARYA_ADMIN_TOKEN, an environment variable that is never committed; unset
// means the feature is off, not "on with an empty password" — this route
// then answers exactly like any other unknown path, so its existence isn't
// even observable from outside until someone deliberately configures it.
//
// multipart/form-data: field "csv", header "Authorization: Bearer <token>".
// Response is a .zip: ringkasan.txt plus every kartu-*.html and
// kredensial-*.csv this run produced — one round trip, unlike the CLI form
// (bin/seed.php) where the _keluaran/ output needs a second fetch off the
// server.
function karya_handle_admin_seed(): never
{
    $token = (string) getenv('KARYA_ADMIN_TOKEN');
    if ($token === '') {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Not found';
        exit;
    }

    if (!karya_ratelimit_check_admin_wrong_attempts()) {
        karya_json_error(429, 'Terlalu banyak percobaan token salah. Coba lagi nanti.');
    }

    $authHeader = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $diberikan = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : '';

    if ($diberikan === '' || !hash_equals($token, $diberikan)) {
        karya_ratelimit_record_admin_wrong_attempt();
        karya_json_error(401, 'Token salah atau tidak diberikan.');
    }

    $berkas = $_FILES['csv'] ?? null;
    if (!is_array($berkas)) {
        karya_json_error(422, 'Tidak ada berkas CSV yang dikirim (field "csv").');
    }

    $galat = $berkas['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($galat === UPLOAD_ERR_INI_SIZE || $galat === UPLOAD_ERR_FORM_SIZE) {
        karya_json_error(413, 'Berkas CSV terlalu besar.');
    }
    if ($galat !== UPLOAD_ERR_OK) {
        karya_json_error(422, 'Berkas CSV gagal terkirim. Coba lagi.');
    }
    $tmp = (string) ($berkas['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        karya_json_error(422, 'Berkas CSV gagal terkirim. Coba lagi.');
    }
    if ((int) ($berkas['size'] ?? 0) > KARYA_MAX_SEED_CSV_BYTES) {
        karya_json_error(413, 'Berkas CSV terlalu besar.');
    }

    try {
        $hasil = karya_seed_proses_csv($tmp);
    } catch (RuntimeException $e) {
        karya_json_error(422, $e->getMessage());
    }

    $zipPath = KARYA_RATELIMIT_DIR . DIRECTORY_SEPARATOR . '.seed-' . bin2hex(random_bytes(4)) . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        karya_json_error(500, 'Gagal menyusun hasil. Coba lagi.');
    }
    $zip->addFromString('ringkasan.txt', implode("\n", $hasil['log']) . "\n");
    foreach ($hasil['keluaran'] as $sekolahSlug => $berkasSekolah) {
        $zip->addFile($berkasSekolah['kartu'], "kartu-{$sekolahSlug}.html");
        $zip->addFile($berkasSekolah['kredensial'], "kredensial-{$sekolahSlug}.csv");
    }
    $zip->close();

    header('Content-Type: application/zip');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="hasil-seed-' . gmdate('Ymd-His') . '.zip"');
    header('Content-Length: ' . (string) filesize($zipPath));
    readfile($zipPath);
    @unlink($zipPath);
    exit;
}
