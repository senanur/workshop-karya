# Tugas implementasi — M-S2 (pemutar) dan M-S3 (alur unggah anak)

**Status (23 September 2026): diimplementasikan oleh opencode
(`deepseek-v4-flash-0731`) menurut dokumen ini, lalu diperiksa dan diuji di
level rute/HTTP (server PHP lokal + `curl`) — bukan di peramban sungguhan.**
Lihat status rinci per milestone di `docs/PRD-jalur-scratch.md` §10. Dokumen
ini tetap dipertahankan sebagai catatan desain — alasan di balik CSP, alias
bundel esbuild, dan kontrak API tidak semuanya terlihat dari membaca kode
saja — bukan lagi sebagai instruksi kerja yang belum dikerjakan.

Tanggal: 23 September 2026. Untuk siapa pun (manusia atau agen) yang
mengerjakan M-S2/M-S3 dari `docs/PRD-jalur-scratch.md`. Dokumen ini
merangkum apa yang **sudah ada di repo** dan apa yang **harus dibangun**,
supaya tidak perlu menerka ulang dari PRD lengkap. Bila ada yang bertentangan,
`docs/PRD-jalur-scratch.md` tetap yang berlaku (dokumen ini adalah ringkasan
kerja, bukan pengganti PRD).

## 0. Baca dulu

- `docs/PRD-jalur-scratch.md` §2 (alur anak), §3 (skema alamat), §4 (data
  model), §6 (halaman pemutar, termasuk CSP-nya), §7 (lisensi bundel — sudah
  diputuskan, lihat §2 di bawah), §9 (daftar berkas yang berubah), §10
  (definisi lolos per milestone).
- `docs/PRD-karyaweb-v2.md` §9 dan §11 — pola CSP dan keamanan yang dipakai
  jalur web, supaya pemutar konsisten gayanya.
- Kode yang **sudah selesai untuk M-S1** dan tidak boleh diubah perilakunya:
  `app/sb3.php` (validasi + susun ulang `.sb3`), `app/storage.php` (fungsi
  `karya_sb3_path`, `karya_draf_sb3_path`, `karya_save_versi_sb3`,
  `karya_sb3_versi_ids`, `karya_meta_jalur`), route `POST /api/unggah`
  (`karya_handle_unggah_sb3`), `POST /api/terbit-sb3`
  (`karya_handle_terbit_sb3`), `GET /<slug>/karya.sb3`, dan `POST /masuk`
  (`karya_handle_masuk`, sudah mengarahkan ke `/<slug>/unggah` untuk jalur
  `scratch`) — semuanya di `index.php`.

## 1. Lingkup

Bangun **M-S2** (halaman pemutar publik) dan **M-S3** (halaman unggah anak +
pratinjau + terbit + penanda di dinding karya + kartu cetak). **Tidak**
termasuk M-S4 (deploy, gladi) — itu langkah terpisah setelah ini lolos uji
lokal.

Definisi lolos (kutip `docs/PRD-jalur-scratch.md` §10, jangan dilonggarkan):

- **M-S2**: bundel di `aset/scratch/`, halaman `/<slug>` untuk jalur
  `scratch`, CSP §6 diverifikasi di peramban sungguhan (termasuk pembuktian
  `'unsafe-eval'` **tidak** diperlukan). Lolos bila
  `game-platformer-pplg.sb3` (lihat berkas templat yang sudah dipakai untuk
  uji M-S1, atau `bin/uji-sb3.php` untuk contoh) berjalan penuh di pemutar:
  tokoh berlari, melompat, koin menambah skor, jatuh mengembalikan ke titik
  mulai.
- **M-S3**: `/<slug>/unggah`, pratinjau sebelum terbit, panel QR + Bagikan
  (identik komponennya dengan M5 jalur web), penanda jalur di dinding karya,
  `bin/seed.php` berkolom `jalur` dan kartu cetak yang berbeda per jalur.

## 2. Keputusan yang sudah diambil (tidak perlu ditanyakan ulang)

**Lisensi bundel pemutar: opsi A.** Pakai versi BSD-3-Clause terakhir sebelum
tiap paket berpindah ke AGPL-3.0-only (25 Nov 2024):

| Paket | Versi yang dipakai |
|---|---|
| `scratch-vm` | `4.8.115` |
| `scratch-render` | `1.2.126` |
| `scratch-svg-renderer` | `2.5.46` |
| `scratch-storage` | `3.0.39` |

Tidak ada `scratch-gui` sama sekali (hanya memutar, bukan editor). Tidak ada
kewajiban menawarkan Corresponding Source — tidak perlu `LICENSE` publik atau
tautan sumber di kaki halaman pemutar (itu hanya perlu untuk opsi B, yang
tidak dipilih).

## 3. M-S2 — Halaman pemutar

### 3.1 Bangun bundel sekali, commit hasilnya

Ikuti `docs/PRD-jalur-scratch.md` §8: bundel dibangun **di mesin
pengembangan**, bukan di `Dockerfile`. Buat:

- `bin/pemutar/package.json` — dependensi persis di §2 di atas, plus bundler
  (mis. esbuild atau webpack — pilih yang paling ringan untuk menekuk
  `scratch-vm` versi lama ini menjadi satu bundle IIFE/UMD, karena versi
  4.8.115 belum tentu sudah ESM-ready).
- `bin/pemutar/build.mjs` (atau setara) — skrip yang menghasilkan
  `aset/scratch/pemutar.js`: menginisialisasi `VirtualMachine` dari
  `scratch-vm`, menyambungkannya ke `RenderWebGL` dari `scratch-render` pada
  sebuah `<canvas>`, memuat proyek dari `ArrayBuffer` (`vm.loadProject`),
  lalu mengekspos fungsi glue yang dipanggil halaman pemutar: minimal
  `mulai(canvas, arrayBuffer)`, `bendera_hijau()`, `berhenti()`,
  `layar_penuh()`. Nama fungsi glue bebas asal konsisten dengan yang dipanggil
  di §3.3 di bawah.
- Commit `aset/scratch/pemutar.js` hasil build ke git (berkas besar,
  disengaja — lihat §8 PRD). `/aset/<...>` sudah bisa melayani berkas ini
  tanpa perubahan router (`KARYA_ASET_CONTENT_TYPES` di `index.php` sudah
  memetakan `js`); tidak perlu ubah apa pun di situ.
- Jangan sentuh `Dockerfile` untuk menambah tahap Node — itu keputusan sadar
  di §8, bukan kelalaian.

Jika lingkungan eksekusi tidak punya akses `npm install`/jaringan untuk
benar-benar membangun bundle: buat scaffolding-nya selengkap mungkin (
`package.json`, skrip build, glue code) dan catat di
`docs/PRD-jalur-scratch.md` §11 bahwa bundle belum di-build+commit sungguhan,
jangan mengarang isi `aset/scratch/pemutar.js`.

### 3.2 Header CSP baru — `app/security.php`

Tambah fungsi baru (pola sama seperti `karya_send_child_page_headers()` dan
`karya_send_app_page_headers()` yang sudah ada di berkas yang sama), persis
CSP dari §6 PRD:

```php
function karya_send_pemutar_page_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex');
    header(
        "Content-Security-Policy: default-src 'none'; script-src 'self'; "
        . "style-src 'self' 'unsafe-inline'; img-src 'self' blob: data:; "
        . "media-src 'self' blob: data:; connect-src 'self'; "
        . "worker-src 'self' blob:; base-uri 'self'; form-action 'none'; "
        . "frame-ancestors 'self'"
    );
    header('Content-Type: text/html; charset=utf-8');
}
```

Verifikasi empiris di peramban sungguhan bahwa `'unsafe-eval'` memang tidak
diperlukan (buka console, cari pelanggaran CSP saat bendera hijau ditekan).
Kalau ternyata perlu, itu keputusan yang harus dibahas dengan pengguna, bukan
ditambahkan diam-diam.

### 3.3 View baru — `app/pemutar_view.php`

Berkas baru, pola sama seperti `app/editor_view.php` (nowdoc `<<<'HTML'` +
`str_replace()` untuk token dinamis, karena `$` dipakai bebas di JS-nya).
Fungsi: `karya_render_pemutar_page(string $slug, array $meta): string`.

Isi halaman (§6 PRD): panggung `480×360`, tombol bendera hijau + berhenti,
tombol layar penuh, nama anak (dari `$meta['nama']`, di-`htmlspecialchars`),
tautan "Unduh berkasnya" ke `/<slug>/karya.sb3`. Skrip memuat
`/aset/scratch/pemutar.js`, lalu `fetch('/<slug>/karya.sb3').then(r =>
r.arrayBuffer())` dan menyerahkannya ke fungsi `mulai()` dari §3.1.

### 3.4 Router — `index.php`

`karya_handle_show_slug()` saat ini melayani jalur web tanpa syarat. Cabangkan
di jalur:

```php
function karya_handle_show_slug(string $slug, bool $headOnly = false): never
{
    if (!karya_slug_is_valid($slug)) {
        http_response_code(404);
        exit;
    }

    $meta = karya_load_meta($slug);
    if ($meta !== null && karya_meta_jalur($meta) === 'scratch') {
        karya_handle_show_pemutar($slug, $meta, $headOnly);
    }

    // ...logika web yang sudah ada, tidak berubah...
}
```

Fungsi baru `karya_handle_show_pemutar()`: cek `disembunyikan` (sama seperti
jalur web — mengembalikan `belum-ada.html` tanpa menyentuh data), cek
`is_file(karya_sb3_path($slug))` (belum terbit → `belum-ada.html` juga, HTTP
200 sesuai spesifikasi yang sudah berlaku untuk jalur web), lalu panggil
`karya_send_pemutar_page_headers()` + `echo karya_render_pemutar_page(...)`.

## 4. M-S3 — Alur unggah anak

### 4.1 Router — `GET /<slug>/unggah`

Tambahkan di `index.php`, persis sejajar dengan blok `GET /<slug>/edit` yang
sudah ada (baris ~57–75): valid slug, meta harus berjalur `scratch` (kalau
`null` atau `'web'` → 404, bukan redirect — alasan yang sama seperti
`/<slug>/edit` menolak jalur `scratch`), lalu
`karya_send_app_page_headers()` + `echo karya_render_unggah_page($slug)`.

### 4.2 View baru — `app/unggah_view.php`

Fungsi `karya_render_unggah_page(string $slug): string`, pola nowdoc yang
sama. Ambil kode edit dari `sessionStorage.getItem('karya-kode-'+slug)` —
persis pola yang sudah dipakai `/masuk` (lihat `karya_render_masuk_page()` di
`app/editor_view.php`) dan editor jalur web.

Alur di JS (tidak perlu route baru untuk pratinjau — lihat §4.3):

1. `<input type="file" accept=".sb3">` atau area drag-and-drop.
2. Begitu berkas dipilih: **langsung baca lokal** lewat `FileReader`/
   `file.arrayBuffer()` dan serahkan ke `mulai()` dari bundel pemutar
   (`/aset/scratch/pemutar.js`, dimuat di halaman ini juga) untuk pratinjau
   instan — tidak menunggu server.
3. **Bersamaan**, `POST /api/unggah` (multipart/form-data) dengan field
   `slug`, `kode`, `berkas` (nama field ini wajib persis `berkas` — lihat
   `karya_handle_unggah_sb3()` di `index.php`, membaca `$_FILES['berkas']`).
   Respons sukses: `{sha256, bytes}`. Respons galat: `{error}` dengan status
   413/415/422/429/401/404 — tampilkan `error` apa adanya, sudah ramah anak
   (bahasa Indonesia, dari `karya_sb3_galat()`).
4. Kalau `/api/unggah` gagal: sembunyikan/nonaktifkan tombol **Terbitkan**
   dan tampilkan pesan galat. Pratinjau lokal dari langkah 2 tetap boleh
   tampil (itu hanya pratinjau di peramban anak sendiri), tapi jangan biarkan
   anak menekan Terbitkan atas unggahan yang server tolak.
5. Kalau `/api/unggah` sukses: aktifkan tombol **Terbitkan** →
   `POST /api/terbit-sb3` dengan `{slug, kode}` (JSON, sama pola dengan
   `/api/buka` dan `/api/terbit` jalur web). Respons: `{url, versi}`.
6. Setelah terbit: tampilkan panel hasil — alamat, QR, tombol Bagikan.
   **Pakai ulang, jangan tulis ulang**, pola dari
   `app/editor_view.php` (cari komentar `panel hasil terbit: alamat, QR,
   bagikan`, fungsi `gambarQr()`, dan skrip `/aset/qrcode.js` yang sudah
   dipakai jalur web) — komponennya harus identik secara visual dengan M5.
7. Anak boleh mengunggah ulang berkali-kali (ulangi dari langkah 1); unggahan
   terakhir yang terbit begitu Terbitkan ditekan lagi.

### 4.3 Kenapa tidak perlu route pratinjau server-side

Berkas yang dipilih anak di `<input type=file>` sudah ada penuh di memori
peramban. Memutarnya langsung dari `File`/`ArrayBuffer` itu juga yang
memberi pratinjau **sebelum** validasi server selesai, tanpa bolak-balik
jaringan. Validasi server (`/api/unggah`) tetap wajib jalan di belakang layar
karena itulah yang menentukan boleh-tidaknya Terbitkan — jangan pernah
mengizinkan Terbitkan hanya berdasar pratinjau lokal berhasil.

### 4.4 Penanda jalur — `app/dinding.php`

`karya_dinding_anak()` saat ini mengembalikan `slug`, `nama_depan`, `warna`.
Tambahkan `jalur` (dari `karya_meta_jalur($meta)`) ke larik itu, lalu di
`karya_render_dinding()` tambahkan penanda kecil di tiap `.kartu` — cukup teks
atau emoji sederhana (mis. "🌐 halaman" / "🎮 game") supaya satu dinding
memuat kedua jalur tanpa ambigu, sesuai §9 PRD. Jangan ubah query/filter
lain di fungsi ini.

### 4.5 `bin/seed.php` — kolom `jalur` dan kartu cetak

`bin/seed.php` saat ini tidak punya konsep `jalur` sama sekali (sudah dicek —
tidak ada satu pun kemunculan kata itu di berkas ini). Tambahkan:

- Kolom CSV opsional `jalur` — kosong berarti `'web'` (default,
  `karya_meta_jalur()` sudah berperilaku begitu, jadi baris CSV lama tanpa
  kolom ini tidak perlu diubah).
- Saat menyiapkan `meta.json` untuk baris berjalur `scratch`: **jangan**
  membuat `isi.html`/`gaya.css` dari templat web (itu untuk jalur web saja).
- Templat kartu cetak berbeda per jalur: jalur `scratch` mencetak alamat
  `/masuk` (sama seperti web — satu pintu untuk keduanya, §3 PRD) dan
  tambahan pengingat teks "simpan `.sb3`-mu ke komputer sebelum keluar dari
  Scratch". Baca dulu struktur kartu yang sudah ada di berkas ini sebelum
  menambah cabang baru, supaya tata letak cetak (ukuran kertas, dsb.) tidak
  ikut berubah untuk kartu jalur web yang sudah teruji.

## 5. Yang TIDAK boleh disentuh

`app/sanitize.php`, `app/foto.php`, `app/editor_view.php`, `app/render.php`,
`app/ratelimit.php`, `docker-compose.yml`, dan seluruh perilaku M-S1 yang
disebut di §0. Jalur web (M4/M5/M6) tidak boleh berubah perilakunya sama
sekali — kalau sebuah perubahan "kebetulan" menyentuh jalur web, itu tanda
untuk berhenti dan mengecek ulang cakupan, bukan diteruskan.

## 6. Setelah selesai

- Uji manual per definisi lolos §1 di atas, di peramban sungguhan (bukan
  cuma `curl`) — CSP dan WebGL tidak teruji lewat command line.
- Perbarui status header `docs/PRD-jalur-scratch.md` (bagian atas berkas)
  dan §10 di sana untuk mencerminkan M-S2/M-S3 selesai, dengan cara yang
  sama seperti status M-S1 dicatat di sana sekarang.
- M-S4 (deploy ke Dokploy + gladi siswa) baru dikerjakan setelah ini, bukan
  bagian dari tugas ini.
