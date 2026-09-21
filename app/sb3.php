<?php

declare(strict_types=1);

if (!defined('KARYA_APP')) {
    http_response_code(404);
    exit;
}

// Jalur Scratch (PRD-jalur-scratch §5): a .sb3 is a zip, so the whole guardrail
// layer is zip guards, not HTML sanitization — app/sanitize.php is not touched
// here at all. Everything the browser claims is trusted:the file's own bytes
// decide what each entry is,and the output is a NEW zip built by us from the
// passing entries only — never the upload as-is, so no stray zip fields, comments,
// or hidden data survive into what's served to the public. app/foto.php does the
// same job for photos (re-encode, not store as-is); this is that principle's
// counterpart for .sb3 files.

const KARYA_SB3_ENTRI_REGEX = '/^[0-9a-f]{32}\.(svg|png|jpg|jpeg|bmp|gif|wav|mp3)$/';
const KARYA_SB3_GAMBAR_TIPE = [
    'png' => IMAGETYPE_PNG,
    'jpg' => IMAGETYPE_JPEG,
    'jpeg' => IMAGETYPE_JPEG,
    'gif' => IMAGETYPE_GIF,
    'bmp' => IMAGETYPE_BMP,
    'webp' => IMAGETYPE_WEBP,
];

// meta.jalur defaults to "web" for every existing file, so the whole web track
// already on disk needs no migration (PRD-jalur-scratch §4).
function karya_meta_jalur(array $meta): string
{
    $jalur = $meta['jalur'] ?? 'web';

    return is_string($jalur) ? $jalur : 'web';
}

/**
  * Validates an uploaded .sb3 and rebuilds it as a fresh zip containing only the
  * entries that passed. Nothing is returned on failure; on success the returned
  * array carries the path of the reassembled temp file (same directory as the
  * target draf.sb3, so a later rename() is atomic), plus sha256 and byte size.

  * @return array{ok: true, hasil: string, sha256: string, bytes: int}
  *         |array{ok: false, status: int, pesan: string}
  */
function karya_sb3_validasi_dan_susun(string $tmpUpload, string $slug): array
{
    if (filesize($tmpUpload) > KARYA_MAX_SB3_BYTES) {

        return karya_sb3_galat(413, 'Berkasnya terlalu besar. Maksimum 20 MB.');
    }

    $zip = new ZipArchive();
    if ($zip->open($tmpUpload) !== true) {
        return karya_sb3_galat(415, 'Berkasnya bukan .sb3 yang sah.');
    }

    $tmpOut = karya_slug_dir($slug) . DIRECTORY_SEPARATOR . '.susun-' . bin2hex(random_bytes(4)) . '.tmp';
    $zipBaru = null;
    $sukses = false;
    try {
        $n = (int) $zip->numFiles;
        if ($n > KARYA_MAX_SB3_ENTRI) {

            return karya_sb3_galat(422, 'Ada terlalu banyak berkas di dalamnya.');
        }

        $indexProject = null;
        $totalKembang = 0;
        $entriNama = [];
        $entriDasar = [];

        for ($i = 0; $i < $n; $i++) {
            $stat = $zip->statIndex($i);
            $statLengkap = $stat !== false && isset($stat['name'], $stat['comp_size'], $stat['size']);
            if (!$statLengkap) {

                return karya_sb3_galat(415, 'Berkasnya rusak atau terenkripsi.');
            }

            $nama = (string) $stat['name'];
            $terkompresi = (int) $stat['comp_size'];
            $asli = (int) $stat['size'];

            if ($nama === 'project.json') {
                if ($indexProject !== null) {
                    return karya_sb3_galat(422, 'Ada lebih dari satu project.json.');
                }
                $indexProject = $i;
            } elseif (!preg_match(KARYA_SB3_ENTRI_REGEX, $nama)) {

                return karya_sb3_galat(415, 'Ada nama berkas yang tidak sah di dalamnya.');
            }

            // Expansion-ratio cap per entry (a zip bomb compressed 1 MB can
            // only legally expand ~100×, or we refuse) plus a hard per-entry cap
            // so a single getFromIndex() below can never balloon PHP's memory.



            if ($asli > KARYA_MAX_SB3_ENTRI_UKURAN || ($terkompresi > 0 && $asli > $terkompresi * KARYA_MAX_SB3_RASIO_ENTRI)) {

                return karya_sb3_galat(422, 'Berkasnya terlalu padat atau mencurigakan.');
            }

            $totalKembang += $asli;
            if ($totalKembang > KARYA_MAX_SB3_UKURAN_KEMBANG) {

                return karya_sb3_galat(422, 'Berkasnya terlalu padat atau mencurigakan.');
            }

            if ($nama !== 'project.json') {
                $entriNama[$nama] = true;
                $dasar = preg_replace('/\.(svg|png|jpg|jpeg|bmp|gif|wav|mp3)$/', '', $nama) ?? $nama;
                $entriDasar[$dasar] = true;
            }
        }

        if ($indexProject === null) {
            return karya_sb3_galat(422, 'project.json tidak ada di dalamnya.');
        }

        $projectJson = $zip->getFromIndex($indexProject);
        if ($projectJson === false || strlen($projectJson) > KARYA_MAX_PROJECT_JSON_BYTES) {

            return karya_sb3_galat(422, 'project.json tidak sah atau kepanjangan.');
        }

        $data = json_decode($projectJson, true);
        if (!is_array($data) || !isset($data['targets']) || !is_array($data['targets'])
            || !isset($data['meta']['semver']) || !is_string($data['meta']['semver']) || trim($data['meta']['semver']) === '') {

            return karya_sb3_galat(422, 'project.json tidak sah.');
        }

        // Every asset a block references must actually be a zip entry — so the
        // player never has to reach out to assets.scratch.mit.edu.cker

        $rujukanMd5ext = [];
        $rujukanAssetId = [];
        karya_sb3_pindai_rujukan($data, $rujukanMd5ext, $rujukanAssetId);
        foreach ($rujukanMd5ext as $berkas) {
            $ada = isset($entriNama[$berkas]);
            if (!$ada) {

                return karya_sb3_galat(422, 'Ada aset yang dirujuk tapi tidak ada di dalamnya.');
            }
        }
        foreach ($rujukanAssetId as $id) {
            $ada = isset($entriDasar[$id]);
            if (!$ada) {

                return karya_sb3_galat(422, 'Ada aset yang dirujuk tapi tidak ada di dalamnya.');
            }
        }

        $zipBaru = new ZipArchive();
        if ($zipBaru->open($tmpOut, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {

            return karya_sb3_galat(422, 'Berkasnya gagal disusun ulang. Coba lagi.');
        }

        // project.json first, then every other entry,in their original relative
        // order — Scratch reads entries by name, so ordering doesn't affect
        // the game,but keeping it deterministic makes the output reproducible.

        $zipBaru->addFromString('project.json', $projectJson);
        unset($projectJson, $data);

        for ($i = 0; $i < $n; $i++) {
            if ($i === $indexProject) {
                continue;
            }
            $stat = $zip->statIndex($i);
            if ($stat === false || !isset($stat['name'])) {
                continue;
            }
            $nama = (string) $stat['name'];

            $isi = $zip->getFromIndex($i);
            if ($isi === false) {
                return karya_sb3_galat(415, 'Ada entri yang tidak bisa dibaca — kemungkinan berkasnya terenkripsi.');
            }

            $ekstensi = strtolower(pathinfo($nama, PATHINFO_EXTENSION));
            $hasilValidasi = match ($ekstensi) {
                'svg' => karya_sb3_validasi_svg($isi),
                'png', 'jpg', 'jpeg', 'gif', 'bmp', 'webp' => karya_sb3_validasi_gambar($isi, $ekstensi),
                'wav' => karya_sb3_validasi_wav($isi),
                'mp3' => karya_sb3_validasi_mp3($isi),
                default => ['ok' => false, 'pesan' => 'Tipe berkas tidak dikenal.'],
            };

            if ($hasilValidasi['ok'] !== true) {
                return karya_sb3_galat(
                    (int) ($hasilValidasi['status'] ?? 415),
                    (string) ($hasilValidasi['pesan'] ?? 'Ada isi yang tidak sah di dalamnya.')
                );
            }

            $zipBaru->addFromString($nama, $isi);
            unset($isi);
        }

        if (!$zipBaru->close()) {

            return karya_sb3_galat(422, 'Berkasnya gagal disusun ulang. Coba lagi.');
        }
        $zipBaru = null;

        if (!is_file($tmpOut)) {
            return karya_sb3_galat(422, 'Berkasnya gagal disusun ulang. Coba lagi.');
        }

        $sukses = true;

        return [
            'ok' => true,
            'hasil' => $tmpOut,
            'sha256' => hash_file('sha256', $tmpOut),
            'bytes' => (int) filesize($tmpOut),
        ];
    } finally {
        if (!$sukses && is_file($tmpOut)) {
            @unlink($tmpOut);
        }
        if ($zipBaru instanceof ZipArchive) {
            $zipBaru->close();
        }
        $zip->close();
    }
}

/** @param list<string> $rujukanMd5ext @param list<string> $rujukanAssetId */
function karya_sb3_pindai_rujukan(array $data, array &$rujukanMd5ext, array &$rujukanAssetId): void
{
    foreach ($data as $kunci => $nilai) {
        if ($kunci === 'md5ext' && is_string($nilai) && $nilai !== '') {
            $rujukanMd5ext[] = $nilai;
            continue;
        }
        if ($kunci === 'assetId' && is_string($nilai) && $nilai !== '') {
            $rujukanAssetId[] = $nilai;
            continue;
        }
        if (is_array($nilai)) {
            karya_sb3_pindai_rujukan($nilai, $rujukanMd5ext, $rujukanAssetId);
        }
    }
}

/** @return array{ok: bool, status?: int, pesan?: string} */
function karya_sb3_validasi_gambar(string $isi, string $ekstensi): array
{
    $info = @getimagesizefromstring($isi);
    if (!is_array($info) || !isset($info[2])) {
        return ['ok' => false, 'status' => 415, 'pesan' => 'Ada gambar yang tidak sah di dalamnya.'];
    }

    $diharapkan = KARYA_SB3_GAMBAR_TIPE[$ekstensi] ?? null;
    if ($diharapkan === null || (int) $info[2] !== $diharapkan) {

        return ['ok' => false, 'status' => 415, 'pesan' => 'Ada gambar yang tidak sah di dalamnya.'];
    }

    return ['ok' => true];
}

/** @return array{ok: bool, status?: int, pesan?: string} */
function karya_sb3_validasi_wav(string $isi): array
{
    $ok = strlen($isi) >= 12 && substr($isi, 0,  4) === 'RIFF' && substr($isi, 8,  4) === 'WAVE';

    return $ok ? ['ok' => true] : ['ok' => false, 'status' => 415, 'pesan' => 'Ada suara yang tidak sah di dalamnya.'];
}

/** @return array{ok: bool, status?: int, pesan?: string} */
function karya_sb3_validasi_mp3(string $isi): array
{
    $ok = strlen($isi) >= 3 && (substr($isi, 0, 3) === 'ID3'
        || (ord($isi[0]) === 0xFF && (ord($isi[1]) & 0xE0) === 0xE0));

    return $ok ? ['ok' => true] : ['ok' => false, 'status' => 415, 'pesan' => 'Ada suara yang tidak sah di dalamnya.'];
}

// SVG costumes are the one place markup from a child can still reach the page,
// so they get their own strict XML-level check (PRD-jalur-scratch §5):
// parsed with LIBXML_NONET (no external entity loading, no DOCTYPE, no
// script/foreignObject/style, no on* attributes, no href/xlink:href,and no
// attribute value other than the two standard namespace URIs may contain "http".

function karya_sb3_validasi_svg(string $isi): array
{
    $dom = new DOMDocument();
    $sebelumnya = libxml_use_internal_errors(true);
    $ok = $dom->loadXML($isi, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($sebelumnya);

    if (!$ok || $dom->doctype !== null) {
        return ['ok' => false, 'status' => 415, 'pesan' => 'Ada SVG yang tidak aman di dalamnya.'];
    }

    $terlarang = ['script', 'foreignobject', 'style', 'iframe', 'object', 'embed', 'form', 'link', 'meta'];
    foreach ($dom->getElementsByTagName('*') as $el) {
        $tag = strtolower($el->nodeName);
        if (in_array($tag, $terlarang, true)) {
            return ['ok' => false, 'status' => 415, 'pesan' => 'Ada SVG yang tidak aman di dalamnya.'];
        }

        foreach ($el->attributes as $attr) {
            $namaAttr = strtolower($attr->nodeName);
            $nilaiAttr = (string) $attr->nodeValue;

            if (str_starts_with($namaAttr, 'on')) {
                return ['ok' => false, 'status' => 415, 'pesan' => 'Ada SVG yang tidak aman di dalamnya.'];
            }
            if (in_array($namaAttr, ['href', 'xlink:href'], true)) {
                return ['ok' => false, 'status' => 415, 'pesan' => 'Ada SVG yang tidak aman di dalamnya.'];
            }
            if (preg_match('/(url\s*\(|javascript:|data:|@import|<\/)/i', $nilaiAttr)) {
                return ['ok' => false, 'status' => 415, 'pesan' => 'Ada SVG yang tidak aman di dalamnya.'];
            }
            if (stripos($nilaiAttr, 'http') !== false

                && $nilaiAttr !== 'http://www.w3.org/2000/svg'


                && $nilaiAttr !== 'http://www.w3.org/1999/xlink') {

                return ['ok' => false, 'status' => 415, 'pesan' => 'Ada SVG yang tidak aman di dalamnya.'];
            }
        }
    }

    return ['ok' => true];
}

/** @return array{ok: false, status: int, pesan: string} */
function karya_sb3_galat(int $status, string $pesan): array
{
    return ['ok' => false, 'status' => $status, 'pesan' => $pesan];
}