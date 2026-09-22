<?php

declare(strict_types=1);

if (!defined('KARYA_APP')) {
    http_response_code(404);
    exit;
}

// Halaman pemutar publik (PRD-jalur-scratch §6, TASK M-S2 §3.3). Berbeda dari
// halaman web dan halaman UI aplikasi: CSP-nya (karya_send_pemutar_page_headers)
// menolak skrip inline, jadi seluruh logika halaman ada di dua berkas yang
// disajikan server: /aset/scratch/pemutar.js (bundel engine) dan
// /aset/scratch/pemutar-halaman.js (perekat halaman: menekan bendera hijau,
// memuat <slug>/karya.sb3 lewat fetch, meneruskannya ke Pemutar.mulai()).
//
// Halaman ini tidak pernah menginterpolasi nilai anak ke dalam markup selain
// yang lewat htmlspecialchars() (nama) dan atribut data-* (slug) — dan template
// memakai nowdoc + str_replace(__TOKEN__) supaya `$` di CSS tidak tersangkut
// interpolasi PHP.

function karya_render_pemutar_page(string $slug, array $meta): string
{
    $tpl = <<<'HTML'
<!doctype html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mainkan game — __NAMA_HTML__</title>
<style>
:root{
  --cherry:#A61B2B; --navy:#2F3C7E; --ink:#1E2440; --text:#232326; --muted:#5E5E66;
  --paper:#FFFFFF; --mist:#ECEEF5; --line:#D8DDEA;
  color-scheme:light;
}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;background:var(--mist);color:var(--text);
  font-family:"Segoe UI",Roboto,Arial,sans-serif;display:flex;flex-direction:column}
.bar{display:flex;align-items:center;gap:8px;padding:8px 14px;background:var(--ink);color:#fff;flex-wrap:wrap}
.bar .logo{font-weight:700;letter-spacing:.02em;margin-right:6px}
.bar .sp{flex:1}
button{font:inherit;border:1px solid rgba(255,255,255,.35);background:transparent;color:#fff;
  border-radius:6px;padding:5px 12px;cursor:pointer}
button:hover{background:rgba(255,255,255,.1)}
button:focus-visible{outline:3px solid #F6C9CF;outline-offset:1px}
a.unduh{font:inherit;color:#fff;text-decoration:none;border:1px solid rgba(255,255,255,.35);
  border-radius:6px;padding:5px 12px}
a.unduh:hover{background:rgba(255,255,255,.1)}
main{flex:1;display:flex;flex-direction:column;align-items:center;gap:14px;padding:22px 16px 40px}
.nama{font-size:1.15rem;font-weight:700;color:var(--ink);text-align:center}
.stage-wrap{position:relative;width:480px;max-width:100%;aspect-ratio:4/3;
  background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.12);overflow:hidden}
.stage-wrap canvas{display:block;width:100%;height:100%}
.kontrol{display:flex;gap:8px;flex-wrap:wrap;justify-content:center}
.kontrol button{background:var(--paper);color:var(--ink);border:1px solid var(--line);border-radius:999px;
  padding:.45rem 1rem;font-size:.95rem}
.kontrol button:hover{background:var(--mist)}
.kontrol button.hijau{background:#4CAF50;border-color:#4CAF50;color:#fff;font-weight:700}
.kontrol button.berhenti{background:var(--cherry);border-color:var(--cherry);color:#fff}
.pesan{min-height:1.2em;color:var(--cherry);font-size:.9rem;text-align:center;max-width:480px}
.pesan[hidden]{display:none}
@media (max-width:520px){
  .stage-wrap{width:100%}
  .bar .url{display:none}
}
</style>
</head>
<body>
<div class="bar">
  <span class="logo">labpplg</span>
  <span class="url" id="url">__DOMAIN_HTML__/__SLUG_HTML__</span>
  <span class="sp"></span>
  <a class="unduh" id="unduh" data-slug="__SLUG_HTML__" href="/__SLUG_HTML__/karya.sb3" download="karya.sb3">Unduh berkasnya</a>
</div>
<main>
  <div class="nama">Game-nya __NAMA_HTML__</div>
  <div class="stage-wrap" id="panggungWrap"><canvas id="panggung" width="480" height="360"></canvas></div>
  <div class="kontrol">
    <button class="hijau" id="hijau" type="button">▶ Bendera hijau</button>
    <button class="berhenti" id="berhenti" type="button">■ Berhenti</button>
    <button id="layar-penuh" type="button">⛶ Layar penuh</button>
  </div>
  <p class="pesan" id="pesan" hidden></p>
</main>
<script src="/aset/scratch/pemutar.js"></script>
<script src="/aset/scratch/pemutar-halaman.js" data-slug="__SLUG_HTML__"></script>
</body>
</html>
HTML;

    $nama = htmlspecialchars((string) ($meta['nama'] ?? $slug), ENT_QUOTES, 'UTF-8');

    return str_replace(
        ['__SLUG_HTML__', '__DOMAIN_HTML__', '__NAMA_HTML__'],
        [
            htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars(KARYA_DOMAIN_LABEL, ENT_QUOTES, 'UTF-8'),
            $nama,
        ],
        $tpl
    );
}