<?php

declare(strict_types=1);

// Real seeding script (PRD v2 §10). Reads a CSV of nama,kelas,sekolah and
// creates one folder per child, plus a printable kartu-<sekolah>.html and a
// kredensial-<sekolah>.csv for the facilitator's own records.
//
// Usage: php bin/seed.php path/to/siswa.csv
//
// Safe to re-run: a row that matches an existing child (same nama, kelas,
// sekolah) is skipped rather than creating a near-duplicate slug or a second
// kode_hash nobody has the plaintext for anymore. Only children actually
// created in THIS run appear in this run's card/credential output — a
// re-run for newly-added rows won't reprint cards for kids who already have
// theirs from an earlier run.

define('KARYA_APP', true);

require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/security.php';
require __DIR__ . '/../app/storage.php';
require __DIR__ . '/../app/render.php';
require __DIR__ . '/../app/dinding.php'; // for karya_nama_depan_untuk_tampilan()

karya_ensure_dirs();

$csvPath = $argv[1] ?? null;
if ($csvPath === null || !is_file($csvPath)) {
    fwrite(STDERR, "Pakai: php bin/seed.php path/ke/siswa.csv\n");
    fwrite(STDERR, "CSV perlu baris header: nama,kelas,sekolah\n");
    exit(1);
}

$fh = fopen($csvPath, 'r');
if ($fh === false) {
    fwrite(STDERR, "Tidak bisa membuka {$csvPath}\n");
    exit(1);
}

$header = fgetcsv($fh, escape: '');
if (!is_array($header)) {
    fwrite(STDERR, "CSV kosong.\n");
    exit(1);
}

$kolom = array_flip(array_map(static fn ($h) => strtolower(trim((string) $h)), $header));
foreach (['nama', 'kelas', 'sekolah'] as $wajib) {
    if (!isset($kolom[$wajib])) {
        fwrite(STDERR, "Kolom '{$wajib}' tidak ada di header CSV. Header yang ditemukan: " . implode(', ', $header) . "\n");
        exit(1);
    }
}

// --- pass 1: index everyone who already exists, by identity (not slug) ---

/** @var array<string, string> $sudahAda "nama|kelas|sekolah_slug" -> existing slug */
$sudahAda = [];
/** @var array<string, true> $slugTerpakai every slug already on disk, so new candidates never collide */
$slugTerpakai = [];
foreach ((array) scandir(KARYA_DATA_DIR) as $nama) {
    if (!is_string($nama) || str_starts_with($nama, '_') || !karya_slug_is_valid($nama)) {
        continue;
    }
    $slugTerpakai[$nama] = true;
    $meta = karya_load_meta($nama);
    if ($meta === null) {
        continue;
    }
    $kunci = karya_seed_kunci_identitas(
        (string) ($meta['nama'] ?? ''),
        (string) ($meta['kelas'] ?? ''),
        (string) ($meta['sekolah_slug'] ?? '')
    );
    $sudahAda[$kunci] = $nama;
}

// --- pass 2: walk the CSV, create what's new, collect what's new for output ---

$templat = karya_load_templat();
$dibuat = []; // sekolahSlug => list of ['nama'=>, 'kelas'=>, 'slug'=>, 'kode'=>, 'sekolah'=>]
$dilewati = 0;
$baris = 1;

while (($row = fgetcsv($fh, escape: '')) !== false) {
    $baris++;
    $nama = trim((string) ($row[$kolom['nama']] ?? ''));
    $kelas = trim((string) ($row[$kolom['kelas']] ?? ''));
    $sekolah = trim((string) ($row[$kolom['sekolah']] ?? ''));
    // Jalur Scratch (PRD-jalur-scratch §4.5): kolom opsional "jalur"; kosong
    // atau bukan "scratch" berarti jalur web (default, karya_meta_jalur() juga
    // berperilaku begitu untuk meta yang tidak punya kolom ini).
    $jalur = strtolower(trim((string) ($row[$kolom['jalur'] ?? -1] ?? '')));
    $jalur = $jalur === 'scratch' ? 'scratch' : 'web';

    if ($nama === '' || $sekolah === '') {
        fwrite(STDERR, "Baris {$baris}: nama atau sekolah kosong, dilewati.\n");
        continue;
    }

    $sekolahSlug = karya_seed_sekolah_slug($sekolah);
    $kunci = karya_seed_kunci_identitas($nama, $kelas, $sekolahSlug);

    if (isset($sudahAda[$kunci])) {
        echo "dilewati: {$nama} sudah ada sebagai /{$sudahAda[$kunci]}\n";
        $dilewati++;
        continue;
    }

    $slug = karya_seed_slug_unik($nama, $slugTerpakai);
    $slugTerpakai[$slug] = true;
    $sudahAda[$kunci] = $slug; // so a duplicate row later in the SAME csv also gets skipped

    $namaDepan = karya_nama_depan_untuk_tampilan($nama);
    $kode = karya_seed_kode_acak();
    $now = gmdate('c');

    karya_save_meta($slug, [
        'nama' => $nama,
        'kelas' => $kelas,
        'sekolah' => $sekolah,
        'sekolah_slug' => $sekolahSlug,
        'kohort' => gmdate('Y-m'),
        'kode_hash' => password_hash($kode, PASSWORD_BCRYPT),
        'dibuat' => $now,
        'terakhir_ubah' => $now,
        'sudah_terbit' => false,
        'disembunyikan' => false,
        'jumlah_foto' => 0,
        'jalur' => $jalur,
    ]);

    // Jalur web di-seed dengan halaman contoh yang siap dilihat (isi.html,
    // gaya.css, index.html). Jalur Scratch TIDAK — gamenya baru ada setelah
    // anak mengunggah .sb3 lalu menerbitkannya, dan halaman pemutarnya dilayani
    // dari meta.jalur + karya.sb3, bukan dari templat web.
    if ($jalur === 'web') {
        $isiAwal = karya_seed_personalisasi_isi($templat['isi'], $nama, $namaDepan, $kelas, $sekolah);
        karya_save_text_atomic(karya_isi_path($slug), $isiAwal);
        karya_save_text_atomic(karya_gaya_path($slug), $templat['gaya']);
        // Assembled and viewable via its QR immediately, but sudah_terbit stays
        // false — the wall (§11) only shows pages the child chose to publish
        // themselves, and this doesn't count as that choice.
        karya_publish_html($slug, karya_render_child_page($slug, $nama, $isiAwal, $templat['gaya']));
    }

    $dibuat[$sekolahSlug][] = ['nama' => $nama, 'kelas' => $kelas, 'slug' => $slug, 'kode' => $kode, 'sekolah' => $sekolah, 'jalur' => $jalur];
    echo "dibuat: {$nama} -> /{$slug}  kode={$kode}  sekolah={$sekolahSlug}  jalur={$jalur}\n";
}

fclose($fh);

// --- output: one kartu + kredensial file per school touched this run ---

if ($dibuat === []) {
    echo "\nTidak ada anak baru ({$dilewati} dilewati karena sudah ada). Tidak ada kartu dibuat.\n";
    exit(0);
}

$runDir = KARYA_DATA_DIR . DIRECTORY_SEPARATOR . '_keluaran' . DIRECTORY_SEPARATOR . gmdate('Ymd-His');
mkdir($runDir, 0770, true);

foreach ($dibuat as $sekolahSlug => $anak) {
    $kartuPath = $runDir . DIRECTORY_SEPARATOR . "kartu-{$sekolahSlug}.html";
    karya_save_text_atomic($kartuPath, karya_seed_render_kartu($anak));

    $kredensialPath = $runDir . DIRECTORY_SEPARATOR . "kredensial-{$sekolahSlug}.csv";
    $csv = "nama,kelas,slug,kode,alamat\n";
    foreach ($anak as $a) {
        $csv .= implode(',', array_map('karya_seed_csv_escape', [
            $a['nama'], $a['kelas'], $a['slug'], $a['kode'], KARYA_DOMAIN_LABEL . '/' . $a['slug'],
        ])) . "\n";
    }
    karya_save_text_atomic($kredensialPath, $csv);

    echo "\n" . count($anak) . " anak dari {$sekolahSlug}:\n  {$kartuPath}\n  {$kredensialPath}\n";
}

echo "\nSelesai: " . array_sum(array_map('count', $dibuat)) . " anak dibuat, {$dilewati} dilewati (sudah ada).\n";

// --- helpers -------------------------------------------------------------

function karya_seed_kunci_identitas(string $nama, string $kelas, string $sekolahSlug): string
{
    return strtolower(trim($nama)) . '|' . strtolower(trim($kelas)) . '|' . $sekolahSlug;
}

function karya_seed_slug_dasar(string $namaLengkap): string
{
    $depan = karya_nama_depan_untuk_tampilan($namaLengkap);
    $calon = preg_replace('/[^a-z0-9]+/', '', strtolower($depan)) ?? '';

    if (strlen($calon) < 2) {
        // First name too short or non-alphanumeric (e.g. a single initial) —
        // fall back to the whole name rather than produce an invalid slug.
        $calon = preg_replace('/[^a-z0-9]+/', '', strtolower($namaLengkap)) ?? '';
    }

    return strlen($calon) >= 2 ? substr($calon, 0, 20) : 'anak';
}

/** @param array<string, true> $terpakai */
function karya_seed_slug_unik(string $namaLengkap, array $terpakai): string
{
    $dasar = karya_seed_slug_dasar($namaLengkap);
    $calon = $dasar;
    $n = 2;

    while (isset($terpakai[$calon]) || !karya_slug_is_valid($calon)) {
        $akhiran = (string) $n;
        $calon = substr($dasar, 0, max(1, 20 - strlen($akhiran))) . $akhiran;
        $n++;
    }

    return $calon;
}

function karya_seed_sekolah_slug(string $sekolahNama): string
{
    $s = strtolower($sekolahNama);
    $s = trim(preg_replace('/[^a-z0-9]+/', '-', $s) ?? '', '-');
    if ($s === '') {
        $s = 'sekolah';
    }
    if (!str_starts_with($s, 'smp')) {
        $s = 'smp-' . $s;
    } else {
        $s = preg_replace('/^smp-?/', 'smp-', $s) ?? $s;
    }
    $s = rtrim(substr($s, 0, 45), '-');

    return karya_sekolah_slug_is_valid($s) ? $s : 'smp-sekolah';
}

function karya_seed_kode_acak(): string
{
    static $charset = null;
    if ($charset === null) {
        $charset = '';
        foreach (str_split('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz') as $c) {
            if (preg_match('/^[2-9A-HJ-KM-NP-Za-km-np-z]$/', $c)) {
                $charset .= $c;
            }
        }
    }

    $kode = '';
    for ($i = 0; $i < 6; $i++) {
        $kode .= $charset[random_int(0, strlen($charset) - 1)];
    }

    return $kode;
}

// The starter template is Nadia's own worked example — personalizing it
// means replacing her specific text, not templating with placeholders that
// don't exist in the source file.
function karya_seed_personalisasi_isi(string $templat, string $nama, string $namaDepan, string $kelas, string $sekolah): string
{
    $nama = htmlspecialchars($nama, ENT_QUOTES, 'UTF-8');
    $namaDepan = htmlspecialchars($namaDepan, ENT_QUOTES, 'UTF-8');
    $subtitle = htmlspecialchars("Siswa kelas {$kelas} — {$sekolah}", ENT_QUOTES, 'UTF-8');

    $templat = str_replace('Siswa kelas IX — SMP Negeri 4 Samarinda', $subtitle, $templat);
    $templat = str_replace('Nadia Aulia', $nama, $templat);

    return str_replace('Nadia', $namaDepan, $templat); // "Foto Nadia" alt, "Aku Nadia"
}

/**
 * @param list<array{nama: string, kelas: string, slug: string, kode: string, sekolah: string, jalur: string}> $anak
 */
function karya_seed_render_kartu(array $anak): string
{
    $qrJs = karya_load_text(KARYA_ASET_DIR . DIRECTORY_SEPARATOR . 'qrcode.js') ?? '';
    $domain = htmlspecialchars(KARYA_DOMAIN_LABEL, ENT_QUOTES, 'UTF-8');

    $kartu = '';
    foreach ($anak as $a) {
        $namaHtml = htmlspecialchars($a['nama'], ENT_QUOTES, 'UTF-8');
        $kelasHtml = htmlspecialchars($a['kelas'], ENT_QUOTES, 'UTF-8');
        $slugHtml = htmlspecialchars($a['slug'], ENT_QUOTES, 'UTF-8');
        $kodeHtml = htmlspecialchars($a['kode'], ENT_QUOTES, 'UTF-8');
        $alamatJs = htmlspecialchars('https://' . KARYA_DOMAIN_LABEL . '/' . $a['slug'], ENT_QUOTES, 'UTF-8');
        // Jalur Scratch (PRD-jalur-scratch §4.5): kartu sama (alamat /masuk,
        // satu pintu untuk kedua jalur) plus satu pengingat agar anak menyimpan
        // .sb3-nya sebelum keluar dari Scratch.
        $reminder = ($a['jalur'] ?? 'web') === 'scratch'
            ? '<div class="ingat">Ingat: simpan .sb3-mu ke komputer sebelum keluar dari Scratch.</div>'
            : '';
        $kartu .= <<<HTML
<div class="kartu">
  <div class="qr" data-alamat="{$alamatJs}"></div>
  <div class="info">
    <div class="nama">{$namaHtml}</div>
    <div class="kelas">{$kelasHtml}</div>
    <div class="baris"><span>Halamanmu</span><b>{$domain}/{$slugHtml}</b></div>
    <div class="baris"><span>Masuk lewat</span><b>{$domain}/masuk</b></div>
    <div class="baris kode"><span>Kode edit</span><b>{$kodeHtml}</b></div>
    {$reminder}
  </div>
</div>

HTML;
    }

    return <<<HTML
<!doctype html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kartu — {$domain}</title>
<style>
  @page { size: A4; margin: 10mm; }
  *{box-sizing:border-box}
  body{margin:0;font-family:"Segoe UI",Roboto,Arial,sans-serif;font-size:11pt}
  .lembar{display:grid;grid-template-columns:1fr 1fr;gap:4mm}
  .kartu{display:flex;gap:3mm;border:1px dashed #999;border-radius:4mm;padding:4mm;
    break-inside:avoid;align-items:center}
  .qr{width:22mm;height:22mm;flex:none}
  .qr svg{width:100%;height:100%}
  .info{min-width:0}
  .nama{font-weight:700;font-size:12pt}
  .kelas{color:#555;font-size:9pt;margin-bottom:1mm}
  .baris{display:flex;justify-content:space-between;gap:2mm;font-size:8pt;color:#333}
  .baris span{color:#777}
  .baris b{font-family:Consolas,monospace;word-break:break-all;text-align:right}
  .kode b{font-size:11pt;letter-spacing:.05em}
  .ingat{margin-top:1mm;font-size:7pt;color:#A61B2B}
</style>
</head>
<body>
<div class="lembar">
{$kartu}
</div>
<script>{$qrJs}</script>
<script>
document.querySelectorAll('.qr').forEach(function(el){
  var qr = qrcode(0, 'M');
  qr.addData(el.getAttribute('data-alamat'));
  qr.make();
  var n = qr.getModuleCount(), NS = 'http://www.w3.org/2000/svg';
  var svg = document.createElementNS(NS, 'svg');
  svg.setAttribute('viewBox', '0 0 ' + n + ' ' + n);
  for (var r = 0; r < n; r++) {
    for (var c = 0; c < n; c++) {
      if (!qr.isDark(r, c)) continue;
      var rect = document.createElementNS(NS, 'rect');
      rect.setAttribute('x', c); rect.setAttribute('y', r);
      rect.setAttribute('width', 1); rect.setAttribute('height', 1);
      svg.appendChild(rect);
    }
  }
  el.replaceChildren(svg);
});
</script>
</body>
</html>
HTML;
}

function karya_seed_csv_escape(string $value): string
{
    if (preg_match('/[",\n]/', $value)) {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    return $value;
}
