<?php

declare(strict_types=1);

if (!defined('KARYA_APP')) {
    http_response_code(404);
    exit;
}

// Photo intake for /api/foto (PRD v2 §9). Nothing the browser claims is
// trusted: the file is identified by its own bytes, re-encoded through GD so
// no EXIF (including GPS coordinates) survives, renamed by the server, and
// resized here even though the editor already shrinks it client-side.

const KARYA_FOTO_TIPE = [
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_PNG => 'png',
    IMAGETYPE_WEBP => 'webp',
];

/**
 * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int} $berkas
 * @return array{ok: true, src: string}|array{ok: false, status: int, pesan: string}
 */
function karya_simpan_foto(string $slug, array $berkas): array
{
    $galat = $berkas['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($galat === UPLOAD_ERR_INI_SIZE || $galat === UPLOAD_ERR_FORM_SIZE) {
        return karya_foto_galat(413, 'Fotonya terlalu besar. Maksimum 1 MB.');
    }
    if ($galat !== UPLOAD_ERR_OK) {
        return karya_foto_galat(422, 'Fotonya gagal terkirim. Coba lagi.');
    }

    $tmp = (string) ($berkas['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return karya_foto_galat(422, 'Fotonya gagal terkirim. Coba lagi.');
    }

    if ((int) ($berkas['size'] ?? 0) > KARYA_MAX_FOTO_BYTES) {
        return karya_foto_galat(413, 'Fotonya terlalu besar. Maksimum 1 MB.');
    }

    if (karya_hitung_foto($slug) >= KARYA_MAX_FOTO_PER_ANAK) {
        return karya_foto_galat(429, 'Sudah ada ' . KARYA_MAX_FOTO_PER_ANAK . ' foto. Hapus salah satu dulu lewat fasilitator.');
    }

    // The file's own bytes decide what it is — never the browser's Content-Type
    // or the filename extension.
    $info = @getimagesize($tmp);
    if (!is_array($info) || !isset($info[2], $info[0], $info[1])) {
        return karya_foto_galat(415, 'Berkasnya bukan gambar JPG, PNG, atau WebP.');
    }

    $tipe = (int) $info[2];
    if (!isset(KARYA_FOTO_TIPE[$tipe])) {
        return karya_foto_galat(415, 'Berkasnya bukan gambar JPG, PNG, atau WebP.');
    }

    if (((int) $info[0]) * ((int) $info[1]) > KARYA_MAX_FOTO_PIXELS) {
        return karya_foto_galat(413, 'Ukuran gambarnya terlalu besar.');
    }

    $gambar = match ($tipe) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
        IMAGETYPE_PNG => @imagecreatefrompng($tmp),
        IMAGETYPE_WEBP => @imagecreatefromwebp($tmp),
    };
    if (!$gambar instanceof GdImage) {
        return karya_foto_galat(415, 'Gambarnya tidak bisa dibaca.');
    }

    $gambar = karya_foto_perkecil($gambar, $tipe === IMAGETYPE_PNG);

    // PNG stays PNG so drawings with transparency don't get a black
    // background; everything else becomes JPEG. Either way GD writes a fresh
    // file from pixels only, so EXIF/GPS cannot survive.
    $ext = $tipe === IMAGETYPE_PNG ? 'png' : 'jpg';
    $nama = bin2hex(random_bytes(6)) . '.' . $ext;

    ob_start();
    if ($tipe === IMAGETYPE_PNG) {
        // imagepng() drops the alpha channel unless savealpha is set here, at
        // encode time — setting it only on the resize path silently flattened
        // transparency for images small enough to skip resizing.
        imagealphablending($gambar, false);
        imagesavealpha($gambar, true);
        $ditulis = imagepng($gambar, null, 6);
    } else {
        $ditulis = imagejpeg($gambar, null, 82);
    }
    $data = (string) ob_get_clean();
    imagedestroy($gambar);

    if (!$ditulis || $data === '') {
        return karya_foto_galat(422, 'Fotonya gagal disimpan. Coba lagi.');
    }

    karya_save_text_atomic(karya_foto_dir($slug) . DIRECTORY_SEPARATOR . $nama, $data);

    return ['ok' => true, 'src' => 'foto/' . $nama];
}

function karya_hitung_foto(string $slug): int
{
    $dir = karya_foto_dir($slug);
    if (!is_dir($dir)) {
        return 0;
    }

    $n = 0;
    foreach ((array) scandir($dir) as $nama) {
        if (is_string($nama) && preg_match('/\.(jpg|png|webp)$/i', $nama)) {
            $n++;
        }
    }

    return $n;
}

function karya_foto_perkecil(GdImage $asal, bool $jagaAlpha): GdImage
{
    $lebar = imagesx($asal);
    $tinggi = imagesy($asal);
    $sisiTerpanjang = max($lebar, $tinggi);

    if ($sisiTerpanjang <= KARYA_FOTO_MAX_SISI) {
        return $asal;
    }

    $skala = KARYA_FOTO_MAX_SISI / $sisiTerpanjang;
    $lebarBaru = max(1, (int) round($lebar * $skala));
    $tinggiBaru = max(1, (int) round($tinggi * $skala));

    $baru = imagecreatetruecolor($lebarBaru, $tinggiBaru);
    if ($jagaAlpha) {
        imagealphablending($baru, false);
        imagesavealpha($baru, true);
    }
    imagecopyresampled($baru, $asal, 0, 0, 0, 0, $lebarBaru, $tinggiBaru, $lebar, $tinggi);
    imagedestroy($asal);

    return $baru;
}

/** @return array{ok: false, status: int, pesan: string} */
function karya_foto_galat(int $status, string $pesan): array
{
    return ['ok' => false, 'status' => $status, 'pesan' => $pesan];
}
