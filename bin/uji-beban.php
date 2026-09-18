<?php

declare(strict_types=1);

// Load test for PRD v2 §12/§14: "uji beban 30 terbit serentak". Seeds N
// throwaway slugs directly on disk (bypassing HTTP, so this doesn't burn
// into the wrong-code/publish rate limiters), fires N concurrent
// POST /api/terbit requests, reports latency stats, then deletes the temp
// slugs it made.
//
// Meant to run ON THE SERVER against http://127.0.0.1:3000 (or whatever
// PHP_CLI_SERVER_WORKERS is actually serving) — that's what tests the real
// concurrency model per §12, not a request going back out through
// Cloudflare/Traefik.
//
// Usage: php bin/uji-beban.php [jumlah=30] [base_url=http://127.0.0.1:3000]

define('KARYA_APP', true);

require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/security.php';
require __DIR__ . '/../app/storage.php';

karya_ensure_dirs();

$jumlah = (int) ($argv[1] ?? 30);
$baseUrl = rtrim((string) ($argv[2] ?? 'http://127.0.0.1:3000'), '/');
$prefix = 'ujibeban' . substr(bin2hex(random_bytes(3)), 0, 4);

if ($jumlah < 1 || $jumlah > 200) {
    fwrite(STDERR, "jumlah harus 1-200\n");
    exit(1);
}

echo "Menyiapkan {$jumlah} slug sementara ({$prefix}1..{$prefix}{$jumlah}) di {$baseUrl}\n";

$slugs = [];
$kode = 'AB23CD';
$kodeHash = password_hash($kode, PASSWORD_BCRYPT);
$now = gmdate('c');

for ($i = 1; $i <= $jumlah; $i++) {
    $slug = $prefix . $i;
    if (!karya_slug_is_valid($slug)) {
        fwrite(STDERR, "slug {$slug} tidak valid, batal — coba jumlah yang lebih kecil\n");
        exit(1);
    }
    karya_save_meta($slug, [
        'nama' => "Uji Beban {$i}",
        'sekolah' => 'Uji Beban',
        'sekolah_slug' => 'smp-uji-beban',
        'kohort' => 'uji-beban',
        'kode_hash' => $kodeHash,
        'dibuat' => $now,
        'terakhir_ubah' => $now,
        'sudah_terbit' => false,
        'disembunyikan' => false,
        'jumlah_foto' => 0,
    ]);
    $slugs[] = $slug;
}

// Same shape of payload a real publish sends: a handful of the actual
// catalog blocks, not a trivial one-line body, so this measures something
// close to a real Terbitkan click's sanitizer + assembler cost.
$isi = <<<'HTML'
<header><h1>Uji Beban</h1></header>
<blockquote class="kutipan">Uji beban 30 terbit serentak.<cite>— PRD v2 §12</cite></blockquote>
<div class="galeri"><img src="foto/contoh-1.svg" alt="a"><img src="foto/contoh-2.svg" alt="b"><img src="foto/contoh-3.svg" alt="c"></div>
<h2>Lima teratas</h2><ol class="top5"><li>Satu</li><li>Dua</li><li>Tiga</li><li>Empat</li><li>Lima</li></ol>
<div class="lencana"><span>A</span><span>B</span></div>
<footer>selesai</footer>
HTML;
$gaya = ':root{--warna-utama:#123456;--warna-latar:#fff;--warna-teks:#222;--huruf-judul:Georgia;--bentuk-foto:50%;--lebar:720px;}';

// --- fire all N POST /api/terbit requests concurrently ---

$mh = curl_multi_init();
$handles = [];
foreach ($slugs as $slug) {
    $ch = curl_init("{$baseUrl}/api/terbit");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['slug' => $slug, 'kode' => $kode, 'isi' => $isi, 'gaya' => $gaya], JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    curl_multi_add_handle($mh, $ch);
    $handles[$slug] = $ch;
}

$mulai = microtime(true);
$aktif = null;
do {
    $status = curl_multi_exec($mh, $aktif);
    if ($aktif) {
        curl_multi_select($mh);
    }
} while ($aktif && $status === CURLM_OK);
$totalWaktu = microtime(true) - $mulai;

// --- collect results ---

$waktuPerRequest = [];
$statusCount = [];
$galat = [];
foreach ($handles as $slug => $ch) {
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $waktu = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    $errno = curl_errno($ch);
    if ($errno !== 0) {
        $galat[] = "{$slug}: " . curl_error($ch);
    } else {
        $waktuPerRequest[] = $waktu;
        $statusCount[$status] = ($statusCount[$status] ?? 0) + 1;
    }
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}
curl_multi_close($mh);

sort($waktuPerRequest);
$n = count($waktuPerRequest);

// --- cleanup: this was never real data ---

foreach ($slugs as $slug) {
    karya_hapus_rekursif(karya_slug_dir($slug));
}

// --- report ---

echo "\n=== Hasil uji beban: {$jumlah} POST /api/terbit serentak ===\n";
echo "Total waktu (semua selesai): " . round($totalWaktu * 1000) . " ms\n";
echo "Status balasan: " . json_encode($statusCount) . "\n";
if ($galat !== []) {
    echo "Galat koneksi (" . count($galat) . "):\n  " . implode("\n  ", $galat) . "\n";
}
if ($n > 0) {
    $p95Index = (int) max(0, ceil($n * 0.95) - 1);
    printf(
        "Waktu per permintaan: min=%dms  median=%dms  p95=%dms  max=%dms\n",
        round($waktuPerRequest[0] * 1000),
        round($waktuPerRequest[intdiv($n, 2)] * 1000),
        round($waktuPerRequest[$p95Index] * 1000),
        round($waktuPerRequest[$n - 1] * 1000)
    );
}

$berhasil = $statusCount[200] ?? 0;
echo "\n{$berhasil}/{$jumlah} berhasil (200).";
echo $berhasil === $jumlah ? " Semua berhasil.\n" : " " . ($jumlah - $berhasil) . " TIDAK berhasil — lihat status di atas.\n";

function karya_hapus_rekursif(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach ((array) scandir($dir) as $entri) {
        if ($entri === '.' || $entri === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $entri;
        is_dir($path) ? karya_hapus_rekursif($path) : unlink($path);
    }
    rmdir($dir);
}
