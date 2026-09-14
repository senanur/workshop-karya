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

define('KARYA_DEBUG', getenv('KARYA_DEBUG') === '1');
define('KARYA_MAX_BODY_BYTES', 256 * 1024);
define('KARYA_DOMAIN_LABEL', 'karya.labpplg.web.id');

// Reserved slugs that must never resolve as a child page — they collide with
// real routes of this app or with other services on the shared lab domain.
define('KARYA_RESERVED_SLUGS', [
    'www', 'api', 'admin', 'mail', 'draw', 'supabase', 'dokploy', 'bikin',
    'karya', 'dinding', 'test', 'static', 'cdn',
]);

error_reporting(E_ALL);
ini_set('display_errors', KARYA_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

function karya_ensure_dirs(): void
{
    foreach ([KARYA_DATA_DIR, KARYA_SISTEM_DIR, KARYA_RATELIMIT_DIR] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
    }

    $belumAda = KARYA_SISTEM_DIR . DIRECTORY_SEPARATOR . 'belum-ada.html';
    if (!file_exists($belumAda)) {
        file_put_contents($belumAda, karya_default_belum_ada_html());
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
