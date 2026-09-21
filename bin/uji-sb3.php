<?php

declare(strict_types=1);

// M-S1 acceptance harness (PRD-jalur-scratch §10, M-S1): drives
// app/sb3.php with a synthetic valid .sb3 and a set of negative files,
// printing PASS/FAIL per case. Run: php bin/uji-sb3.php
//
// The real acceptance file, game-platformer-pplg.sb3, lives outside this repo;
// this harness ships a synthetic stand-in so the validator can be exercised anywhere.

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "mulai\n";

$tmpData = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'karya-uji-sb3-' . bin2hex(random_bytes(4));
if (!mkdir($tmpData, 0777, true) && !is_dir($tmpData)) { fwrite(STDERR, "gagal buat tmp\n"); exit(1); }
putenv('KARYA_DATA_DIR=' . $tmpData);

define('KARYA_APP', true);
require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/security.php';
require __DIR__ . '/../app/storage.php';
require __DIR__ . '/../app/sb3.php';

karya_ensure_dirs();

$slug = 'uji';
$slugDir = karya_slug_dir($slug);
mkdir($slugDir, 0777, true);

$okAll = true;
echo "A\n";

function uji(string $nama, bool $harusLolos, callable $buat): void
{
    global $slug, $slugDir, $okAll;
    $file = $slugDir . DIRECTORY_SEPARATOR . $nama . '.sb3';
    $buat($file);
    if (!is_file($file)) { fwrite(STDERR, "uji gagal: $nama tidak dibuat\n"); $okAll = false; return; }
    try {
        $hasil = karya_sb3_validasi_dan_susun($file, $slug);
    } catch (Throwable $t) {
        echo "GAGAL-LEMPAR  $nama  " . $t->getMessage() . "\n";
        $okAll = false; return;
    }
    if ($hasil['ok'] === true) {
        @unlink($hasil['hasil']);
    }
    $lolos = $hasil['ok'] === $harusLolos;
    $okAll = $okAll && $lolos;
    echo ($lolos ? "PASS" : "FAIL") . "  $nama";
    if ($lolos && $hasil['ok'] === true) { echo "  (" . $hasil['bytes'] . " bytes, sha256 " . substr($hasil['sha256'],0,8) . ")"; }
    if (!$lolos) { echo "  -> " . json_encode($hasil, JSON_UNESCAPED_UNICODE); }
    echo "\n";
}

function uji_buat_zip(string $path, array $entri): void
{
    $z = new ZipArchive();
    if ($z->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { throw new RuntimeException('zip open gagal'); }
    foreach ($entri as $nama => $isi) {
        $z->addFromString($nama, $isi);
    }
    $z->close();
}

$projectJson = json_encode([
    'targets' => [[
        'isStage' => true,
        'name' => 'Stage',
        'costumes' => [[
            'assetId' => str_repeat('a',32),
            'name' => 'latar',
            'md5ext' => str_repeat('a',32) . '.svg',
            'dataFormat' => 'svg',
            'rotationCenterX' => 0,
            'rotationCenterY' => 0,
            'bitmapResolution' => 1,
        ]],
        'sounds' => [],
        'blocks' => new stdClass(),
        'variables' => new stdClass(),
        'lists' => new stdClass(),
        'broadcasts' => new stdClass(),
        'comments' => new stdClass(),
        'currentCostume' => 0,
        'costumeCount' => 1,
    ]],
    'meta' => ['semver' => '3.0.0', 'vm' => '0.2.0', 'agent' => 'uji'],
], JSON_UNESCAPED_SLASHES);

$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect x="10" y="10" width="80" height="80" fill="#A61B2B"/></svg>';

echo "B2\n";

// 1. valid: project.json + one svg costume + one wav sound.
uji('sah', true, function ($file) use ($projectJson, $svg): void {
    uji_buat_zip($file, [
        'project.json' => $projectJson,
        str_repeat('a',32) . '.svg' => $svg,
        str_repeat('b',32) . '.wav' => 'RIFF' . str_repeat('x',4) . 'WAVE' . str_repeat('x',8),
    ]);
});

// 2. not a zip at all.
uji('bukan-zip', false, function ($file): void {
    file_put_contents($file, 'ini bukan zip sama sekali');
});

// 3. path traversal entry. 
uji('path-traversal', false, function ($file) use ($projectJson): void {
    uji_buat_zip($file, [
        '../evil.txt' => 'x',
        'project.json' => $projectJson,
    ]);
});

// 4. zip bomb: a highly compressible 200 KB entry far exceeds the 100x ratio.
uji('zip-bomb', false, function ($file) use ($projectJson): void {
    uji_buat_zip($file, [
        'project.json' => $projectJson,
        str_repeat('c',32) . '.png' => str_repeat('A',200 * 1024),
    ]);
});

// 5. project.json references an asset that is not in the zip.
uji('aset-hilang', false, function ($file) use ($projectJson, $svg): void {
    uji_buat_zip($file, [
        'project.json' => $projectJson,
        str_repeat('f',32) . '.svg' => $svg,
    ]);
});

// 6. svg costume containing a script element。
uji('svg-script', false, function ($file) use ($projectJson): void {
    $svgJahat = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
    uji_buat_zip($file, [
        'project.json' => $projectJson,
        str_repeat('a',32) . '.svg' => $svgJahat,
    ]);
});

// 7. png entry that is actually text。
uji('png-palsu', false, function ($file) use ($projectJson, $svg): void {
    uji_buat_zip($file, [
        'project.json' => $projectJson,
        str_repeat('d',32) . '.svg' => $svg,
        str_repeat('e',32) . '.png' => 'saya gambar palsu',
    ]);
});

echo "SKIP  terenkripsi  (diuji manual di PHP  ‌8.4 dengan libzip modern)\n";

// 9. duplicate project.json。
uji('dua-project', false, function ($file) use ($projectJson, $svg): void {
    uji_buat_zip($file, [
        'project.json' => $projectJson,
        'project.json' => $projectJson,
    ]);
});

echo "\n" . ($okAll ? "SEMUA LOLOS" : "ADA YANG GAGAL") . "\n";
exit($okAll ? 0 : 1);
