<?php

declare(strict_types=1);

if (!defined('KARYA_APP')) {
    http_response_code(404);
    exit;
}

// Data lives outside the docroot by default so it's never directly web-servable,
// regardless of how the local nginx site (phpBro) or the prod container is configured.
$defaultDataDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'workshop-spmb-data' . DIRECTORY_SEPARATOR . 'karya';
define('KARYA_DATA_DIR', rtrim((string) (getenv('KARYA_DATA_DIR') ?: $defaultDataDir), '/\\'));
define('KARYA_SISTEM_DIR', KARYA_DATA_DIR . DIRECTORY_SEPARATOR . '_sistem');
define('KARYA_RATELIMIT_DIR', KARYA_DATA_DIR . DIRECTORY_SEPARATOR . '_ratelimit');
define('KARYA_TEMPLAT_DIR', KARYA_SISTEM_DIR . DIRECTORY_SEPARATOR . 'templat');

// Static assets served under /aset/... (dasar.css, blok.js, contoh foto).
// Part of the app's own code (versioned in git), not child data.
define('KARYA_ASET_DIR', __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'aset');

define('KARYA_DEBUG', getenv('KARYA_DEBUG') === '1');
define('KARYA_MAX_BODY_BYTES', 256 * 1024);
define('KARYA_MAX_ISI_BYTES', 64 * 1024);
define('KARYA_MAX_GAYA_BYTES', 16 * 1024);
define('KARYA_MAX_FOTO_BYTES', 1024 * 1024);
define('KARYA_MAX_FOTO_PER_ANAK', 8);
// Guards against decompression bombs: a 1 MB file can still declare enormous
// dimensions, and GD allocates ~4 bytes per pixel before we ever resize it.
define('KARYA_MAX_FOTO_PIXELS', 40_000_000);
define('KARYA_FOTO_MAX_SISI', 800);
define('KARYA_MAX_VERSI', 5);
define('KARYA_DOMAIN_LABEL', 'karya.labpplg.web.id');

// Reserved slugs that must never resolve as a child page — they collide with
// real routes of this app or with other services on the shared lab domain.
// v1's list is kept as-is (even 'bikin', whose route is gone in v2) plus the
// v2 route names. Anything starting with 'smp-' is rejected separately
// (karya_slug_is_valid), since that whole namespace is the school walls.
define('KARYA_RESERVED_SLUGS', [
    'www', 'api', 'admin', 'mail', 'draw', 'supabase', 'dokploy', 'bikin',
    'karya', 'dinding', 'test', 'static', 'cdn',
    'masuk', 'edit', 'aset', 'foto',
]);

error_reporting(E_ALL);
ini_set('display_errors', KARYA_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

function karya_ensure_dirs(): void
{
    foreach ([KARYA_DATA_DIR, KARYA_SISTEM_DIR, KARYA_RATELIMIT_DIR, KARYA_TEMPLAT_DIR] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
    }

    $belumAda = KARYA_SISTEM_DIR . DIRECTORY_SEPARATOR . 'belum-ada.html';
    if (!file_exists($belumAda)) {
        file_put_contents($belumAda, karya_default_belum_ada_html());
    }

    // The generic "Kembalikan ke contoh" target for every child. Seeding
    // (bin/seed.php, M6) copies this into each child's own isi.html/gaya.css
    // with their name filled in; this copy in _sistem/templat/ never changes
    // per child, it's just what a reset falls back to.
    $templatIsi = KARYA_TEMPLAT_DIR . DIRECTORY_SEPARATOR . 'isi.html';
    $templatGaya = KARYA_TEMPLAT_DIR . DIRECTORY_SEPARATOR . 'gaya.css';
    $seedDir = KARYA_ASET_DIR . DIRECTORY_SEPARATOR . 'templat-awal';
    if (!file_exists($templatIsi) && is_file($seedDir . DIRECTORY_SEPARATOR . 'isi.html')) {
        copy($seedDir . DIRECTORY_SEPARATOR . 'isi.html', $templatIsi);
    }
    if (!file_exists($templatGaya) && is_file($seedDir . DIRECTORY_SEPARATOR . 'gaya.css')) {
        copy($seedDir . DIRECTORY_SEPARATOR . 'gaya.css', $templatGaya);
    }
}

function karya_default_belum_ada_html(): string
{
    return <<<HTML
<!doctype html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Halaman belum dibuat</title>
<style>
  body { font-family: system-ui, sans-serif; background:#f4f4f8; color:#333;
         display:flex; min-height:100vh; align-items:center; justify-content:center;
         margin:0; text-align:center; padding:2rem; box-sizing:border-box; }
  .card { background:#fff; border-radius:16px; padding:2rem; max-width:360px;
          box-shadow:0 4px 20px rgba(0,0,0,.08); }
  h1 { font-size:1.25rem; margin:0 0 .5rem; }
  p { margin:0; color:#666; }
</style>
</head>
<body>
  <div class="card">
    <h1>Halaman ini masih kosong</h1>
    <p>Pemiliknya belum menerbitkan halamannya. Coba lagi nanti.</p>
  </div>
</body>
</html>
HTML;
}
