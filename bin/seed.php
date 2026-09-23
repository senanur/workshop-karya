<?php

declare(strict_types=1);

// Real seeding script (PRD v2 §10). Reads a CSV of nama,kelas,sekolah and
// creates one folder per child, plus a printable kartu-<sekolah>.html and a
// kredensial-<sekolah>.csv for the facilitator's own records.
//
// Usage: php bin/seed.php path/to/siswa.csv
//
// A thin CLI wrapper around app/seed.php's karya_seed_proses_csv() — the same
// function backs POST /admin/seed for facilitators without server access
// (index.php's karya_handle_admin_seed()). Keep them sharing this, don't
// re-fork the logic in either direction.
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
require __DIR__ . '/../app/seed.php';

karya_ensure_dirs();

$csvPath = $argv[1] ?? null;
if ($csvPath === null || !is_file($csvPath)) {
    fwrite(STDERR, "Pakai: php bin/seed.php path/ke/siswa.csv\n");
    fwrite(STDERR, "CSV perlu baris header: nama,kelas,sekolah\n");
    exit(1);
}

try {
    $hasil = karya_seed_proses_csv($csvPath);
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

foreach ($hasil['log'] as $baris) {
    echo $baris . "\n";
}
