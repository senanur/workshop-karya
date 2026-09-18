<?php

declare(strict_types=1);

if (!defined('KARYA_APP')) {
    http_response_code(404);
    exit;
}

// /smp-<nama-sekolah> (PRD v2 §11): a read-only wall of every child from that
// school who has published and isn't hidden. Server-rendered from meta.json
// only — no JavaScript, so it works even on the oldest phone a parent has.

/**
 * @return list<array{slug: string, nama_depan: string, warna: string}>
 */
function karya_dinding_anak(string $sekolahSlug): array
{
    $anak = [];

    foreach ((array) scandir(KARYA_DATA_DIR) as $nama) {
        if (!is_string($nama) || str_starts_with($nama, '_') || !karya_slug_is_valid($nama)) {
            continue;
        }

        $meta = karya_load_meta($nama);
        if ($meta === null) {
            continue;
        }
        if (($meta['sekolah_slug'] ?? null) !== $sekolahSlug) {
            continue;
        }
        if (($meta['sudah_terbit'] ?? false) !== true) {
            continue;
        }
        if (($meta['disembunyikan'] ?? false) === true) {
            continue;
        }

        $anak[] = [
            'slug' => $nama,
            'nama_depan' => karya_nama_depan_untuk_tampilan((string) ($meta['nama'] ?? $nama)),
            'warna' => karya_warna_utama_anak($nama),
        ];
    }

    usort($anak, static fn (array $a, array $b): int => strcasecmp($a['nama_depan'], $b['nama_depan']));

    return $anak;
}

function karya_nama_depan_untuk_tampilan(string $namaLengkap): string
{
    $potongan = preg_split('/\s+/', trim($namaLengkap));
    $depan = is_array($potongan) && $potongan !== [] ? $potongan[0] : $namaLengkap;

    return $depan !== '' ? $depan : $namaLengkap;
}

// Reads --warna-utama back out of the child's own (already-sanitized)
// gaya.css so the wall can show each card in their chosen color. The result
// is validated against a narrow allow-list before it's ever interpolated
// into a style attribute — CSS already passed karya_sanitize_css on the way
// in, but this function doesn't assume that; it checks again on its own.
function karya_warna_utama_anak(string $slug): string
{
    $default = '#A61B2B';
    $css = karya_load_text(karya_gaya_path($slug)) ?? '';

    if (!preg_match('/--warna-utama\s*:\s*([^;]+);/', $css, $m)) {
        return $default;
    }

    $warna = trim($m[1]);
    $hexValid = (bool) preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $warna);
    $namaValid = (bool) preg_match('/^[a-zA-Z]+$/', $warna);

    return ($hexValid || $namaValid) ? $warna : $default;
}

function karya_nama_sekolah_untuk_tampilan(string $sekolahSlug, array $anak): string
{
    foreach ($anak as $a) {
        $meta = karya_load_meta($a['slug']);
        if ($meta !== null && is_string($meta['sekolah'] ?? null) && $meta['sekolah'] !== '') {
            return $meta['sekolah'];
        }
    }

    // No published child yet, or "sekolah" wasn't recorded for some reason —
    // fall back to turning the slug back into something readable.
    $kata = array_filter(explode('-', substr($sekolahSlug, 4))); // drop leading "smp-"

    return 'SMP ' . implode(' ', array_map('ucfirst', $kata));
}

function karya_render_dinding(string $sekolahSlug): string
{
    $anak = karya_dinding_anak($sekolahSlug);
    $namaSekolah = htmlspecialchars(karya_nama_sekolah_untuk_tampilan($sekolahSlug, $anak), ENT_QUOTES, 'UTF-8');

    $kartu = '';
    foreach ($anak as $a) {
        $namaHtml = htmlspecialchars($a['nama_depan'], ENT_QUOTES, 'UTF-8');
        $slugHtml = htmlspecialchars($a['slug'], ENT_QUOTES, 'UTF-8');
        $warnaHtml = htmlspecialchars($a['warna'], ENT_QUOTES, 'UTF-8');
        $kartu .= <<<HTML
<a class="kartu" href="/{$slugHtml}" style="--warna:{$warnaHtml}">
  <span class="titik"></span>
  <span class="nama">{$namaHtml}</span>
</a>

HTML;
    }

    $isi = $anak === []
        ? '<p class="kosong">Belum ada yang menerbitkan halaman dari sekolah ini.</p>'
        : '<div class="grid">' . $kartu . '</div>';

    return <<<HTML
<!doctype html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dinding Karya — {$namaSekolah}</title>
<style>
  :root{color-scheme:light}
  *{box-sizing:border-box}
  body{margin:0;font-family:"Segoe UI",Roboto,Arial,sans-serif;background:#ECEEF5;color:#232326;padding:2rem 1.25rem}
  main{max-width:720px;margin:0 auto}
  h1{font-size:1.4rem;margin:0 0 .25rem;color:#A61B2B}
  .sub{color:#5E5E66;margin:0 0 1.75rem}
  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px}
  .kartu{display:flex;flex-direction:column;align-items:center;gap:.5rem;padding:1.25rem .75rem;
    background:#fff;border-radius:14px;text-decoration:none;color:#232326;
    box-shadow:0 2px 10px rgba(0,0,0,.06);border-top:4px solid var(--warna)}
  .titik{width:14px;height:14px;border-radius:50%;background:var(--warna)}
  .nama{font-weight:600;text-align:center;word-break:break-word}
  .kosong{color:#5E5E66}
</style>
</head>
<body>
<main>
  <h1>Dinding Karya</h1>
  <p class="sub">{$namaSekolah}</p>
  {$isi}
</main>
</body>
</html>
HTML;
}
