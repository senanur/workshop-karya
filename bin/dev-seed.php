<?php

declare(strict_types=1);

// Dev-only helper: creates ONE test slug so M4 (router, editor, sanitasi) can
// be exercised locally without waiting for the real CSV seeder (M6,
// bin/seed.php). Not meant for production use — no CSV, no printed cards.
//
// Usage: php bin/dev-seed.php [slug] [kode]
//   php bin/dev-seed.php            -> slug "nadia", kode "AB23CD"
//   php bin/dev-seed.php budi Z9K3M4

define('KARYA_APP', true);

require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/security.php';
require __DIR__ . '/../app/storage.php';

karya_ensure_dirs();

$slug = $argv[1] ?? 'nadia';
$kode = $argv[2] ?? 'AB23CD';

if (!karya_slug_is_valid($slug)) {
    fwrite(STDERR, "Slug '{$slug}' tidak valid atau termasuk nama cadangan.\n");
    exit(1);
}

if (!karya_kode_is_valid($kode)) {
    fwrite(STDERR, "Kode '{$kode}' tidak valid (6 karakter, tanpa 0 O 1 l I).\n");
    exit(1);
}

$now = gmdate('c');
karya_save_meta($slug, [
    'nama' => ucfirst($slug),
    'sekolah_slug' => null,
    'kode_hash' => password_hash($kode, PASSWORD_BCRYPT),
    'dibuat' => $now,
    'terakhir_ubah' => $now,
    'sudah_terbit' => false,
    'disembunyikan' => false,
    'jumlah_foto' => 0,
]);

echo "Dibuat: slug={$slug} kode={$kode}\n";
echo "Buka: /{$slug}/edit  (atau /masuk lalu isi nama halaman + kode ini)\n";
