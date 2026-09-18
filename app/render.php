<?php

declare(strict_types=1);

if (!defined('KARYA_APP')) {
    http_response_code(404);
    exit;
}

// Assembles a child's published page from their (already-sanitized) isi.html
// + gaya.css, per docs/halaman-contoh-web/BACA-DULU.txt: <head> loads
// /aset/dasar.css then a <style> block with the child's gaya.css, <body> is
// <div class="halaman"> + the child's isi.html, closed by /aset/blok.js.
//
// <base href="/<slug>/"> makes the child's own relative src="foto/…" resolve
// under their own folder regardless of whether the request URL had a
// trailing slash.
function karya_render_child_page(string $slug, string $nama, string $isiHtml, string $gayaCss): string
{
    $judul = htmlspecialchars($nama !== '' ? $nama : $slug, ENT_QUOTES, 'UTF-8');
    $slugHtml = htmlspecialchars($slug, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!doctype html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$judul} — {$slugHtml}</title>
<base href="/{$slugHtml}/">
<link rel="stylesheet" href="/aset/dasar.css">
<style>{$gayaCss}</style>
</head>
<body>
<div class="halaman">
{$isiHtml}
</div>
<script src="/aset/blok.js"></script>
</body>
</html>
HTML;
}
