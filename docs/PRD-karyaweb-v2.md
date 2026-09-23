# PRD v2 — KaryaWeb (editor kode HTML/CSS untuk pelatihan SMP)

Tanggal: 21 September 2026 · Status: **M4 selesai dan diverifikasi di
produksi**; **M5 dan M6 selesai dari sisi kode, belum diverifikasi di
produksi**.

M4 (router, `/masuk`, `/<slug>/edit`, `/api/buka` + `/api/terbit`, sanitasi
HTML/CSS, perakit halaman, CSP baru — lihat §14) diuji langsung di
`karya.labpplg.web.id` dengan payload serangan nyata (script, event handler,
`javascript:`/`data:` src-href, CSS `@import`/`url()`/komentar tak tertutup)
dan seluruhnya tersanitasi sesuai §9.

M5 (`app/foto.php`, versi tersimpan, panel terbit dengan QR + Bagikan) dan M6
(`bin/seed.php` + kartu cetak, `app/dinding.php`, saklar `disembunyikan` di
`index.php`) sudah diimplementasikan dan sudah lewat perbaikan lanjutan
(ekstensi `gd` di `Dockerfile`, `bin/` disalin ke image, perbaikan deprecation
`fgetcsv()` PHP 8.4 di `bin/seed.php`). **Belum dijalankan:** deploy build ini
ke `karya.labpplg.web.id`, uji manual §9 untuk jalur foto/versi/dinding/saklar
di produksi, `bin/uji-beban.php` (30 terbit serentak) di server sungguhan, dan
gladi dengan siswa PPLG. Sampai itu selesai, M6 belum dianggap tuntas per
kriteria lolos di §14.

Dokumen ini menggantikan `docs/PRD-karyaweb.md` (v1, 14 Sep 2026) sebagai acuan
pengembangan. PRD v1 **tetap disimpan apa adanya** sebagai catatan dari apa yang
sudah dibangun dan diuji (M1, M2, M3a); kode yang ada di repo saat ini masih
mencerminkan v1. Bila kedua dokumen bertentangan, v2 yang berlaku.

Jalur Scratch (Jalur B pelatihan: anak mengunggah `.sb3` dan mendapat
halaman yang bisa dimainkan) dibahas di dokumen pendamping
`docs/PRD-jalur-scratch.md`, 21 Sep 2026. Dokumen itu menambah modul di
sebelah jalur web dan tidak mengubah apa pun di sini; yang tersentuh hanya
`index.php`, `app/config.php`, `app/storage.php`, `app/dinding.php`,
`bin/seed.php`, dan `Dockerfile`, seluruhnya bersifat penambahan.

Sumber keputusan v2:

- Modul "Modul Pelatihan Halaman Profil Web untuk SMP" (Jalur A), keputusan
  desain 17 Sep 2026: anak menyunting HTML/CSS langsung, bukan mengisi form.
- Skema alamat 18 Sep 2026 (`/masuk`, `/<slug>/edit`, `/smp-*`).
- Berkas contoh di `[0] SPMB 2728/Claude outputs/halaman-contoh-web/`
  (`isi.html`, `gaya.css`, `dasar.css`, `blok.js`, `editor-demo.html`,
  `BACA-DULU.txt`). Berkas-berkas ini adalah titik awal implementasi, bukan
  sekadar ilustrasi.

## 1. Latar belakang dan tujuan

Pelatihan 2×45 menit untuk ±28 anak SMP per sesi, bagian dari sosialisasi PPLG
SMK TI Airlangga. Percobaan tahun lalu gagal karena anak hanya mengetik ulang
kode. PRD v1 menjawabnya dengan form tanpa kode; setelah modul disusun,
diputuskan pendekatan itu terlalu jauh dari "membuat web": anak tidak pernah
melihat HTML.

v2 mengambil jalan tengah. Anak menyunting kode sungguhan, tetapi di dalam
pagar: halamannya sudah jadi dan sudah bernama sebelum ia datang, hanya dua
berkas pendek yang terlihat (`Isi` ±35 baris HTML, `Gaya` 6 variabel CSS),
pratinjau berubah seketika, dan salah ketik tidak pernah membuat layar kosong.

Tujuan aplikasi: melayani halaman publik tiap anak, menyediakan editor kode di
browser, dan menerbitkan hasilnya dengan aman di domain yang dipakai bersama
layanan lab lain.

Ukuran keberhasilan sesi (dari rundown modul): semua anak sudah menekan
**Terbitkan** sekali sebelum menit ke-25, dan tiap anak pulang membawa alamat
halaman yang bisa dibuka orang tua.

## 2. Yang dipertahankan dari v1

Bagian ini tidak berubah dan kodenya dipakai ulang:

- Tanpa database, tanpa akun. State ada di filesystem, penulisan atomik
  (`.tmp` lalu `rename()`) — `app/storage.php`.
- Validasi slug `^[a-z0-9-]{2,20}$` sebelum menyentuh filesystem, kode edit 6
  karakter tanpa `0 O 1 l I`, disimpan sebagai hash bcrypt — `app/security.php`.
- Rate limit berbasis `flock()`: 1 terbit / 3 detik per slug, 60 / menit global
  — `app/ratelimit.php`.
- Satu front controller `index.php`; dev di phpBro (`karya.lokal:8080`),
  produksi `php -S 0.0.0.0:3000 index.php` dalam container di belakang Traefik
  (`Dockerfile`, `docker-compose.yml`). Catatan `PHP-404-extended-path-bug.md`
  tetap relevan untuk dev lokal.
- Folder data di luar docroot, override lewat `KARYA_DATA_DIR`.
- Pesan error generik ke klien, detail ke log server.

## 3. Pengguna dan alur utama

- **Anak (di lab, komputer/laptop).** Buka `/masuk`, ketik nama halaman dan
  kode dari kartu → masuk ke `/<slug>/edit` → sunting tab Isi dan Gaya sambil
  melihat pratinjau → Terbitkan → dapat alamat, QR, dan tombol Bagikan.
- **Anak (di rumah, HP).** Alur sama; editor beralih ke mode satu kolom dengan
  tab Kode/Pratinjau. HP diposisikan untuk melihat hasil dan perbaikan kecil,
  bukan perangkat utama.
- **Orang tua / teman.** Membuka `/<slug>` atau dinding karya `/smp-…` tanpa
  kode, hanya baca.
- **Fasilitator.** Menyiapkan halaman dari CSV sebelum hari-H, mencetak kartu,
  memantau log saat sesi, dan bisa menarik satu halaman bila isinya bermasalah
  (lihat §9).

## 4. Skema alamat

| Alamat | Fungsi |
|---|---|
| `karya.labpplg.web.id/<slug>` | halaman publik anak (dicetak di kartu + QR) |
| `…/<slug>/edit` | editor; tanpa kode yang sah hanya menampilkan pintu masuk |
| `…/<slug>/foto/<berkas>` | foto milik anak itu |
| `…/masuk` | pintu masuk: nama halaman + kode edit |
| `…/smp-<nama-sekolah>` | dinding karya per sekolah |
| `…/aset/…` | `dasar.css`, `blok.js`, foto contoh, pustaka QR |
| `…/api/…` | API editor (§6) |

Nama cadangan yang tidak boleh menjadi slug anak: daftar v1 (`www, api, admin,
mail, draw, supabase, dokploy, bikin, karya, dinding, test, static, cdn`)
ditambah `masuk, edit, aset, foto` dan semua yang berawalan `smp-`. Rute
`/bikin` dari v1 dihapus; healthcheck di `Dockerfile` dipindah ke `/masuk`.

Halaman publik dirakit dengan `<base href="/<slug>/">` supaya `src="foto/…"`
yang ditulis anak mengarah ke foldernya sendiri walau alamatnya tanpa garis
miring di akhir. Bila `foto/<berkas>` tidak ada di folder anak, server mencari
di `aset/foto/` (untuk gambar contoh bawaan templat).

## 5. Data model

```
/data/karya/
  _sistem/belum-ada.html
  _sistem/templat/{isi.html,gaya.css}   templat awal + sumber "Kembalikan ke contoh"
  _ratelimit/…
  <slug>/
    meta.json
    isi.html        sumber tab Isi, sudah disanitasi
    gaya.css        sumber tab Gaya, sudah disanitasi
    index.html      rakitan siap saji (dibuat ulang setiap terbit)
    foto/           unggahan anak
    versi/          N terbitan terakhir: <timestamp>-isi.html, <timestamp>-gaya.css
```

`meta.json` v2 menambah: `sekolah_slug` (mis. `smp-negeri-4`), `sudah_terbit`
(bool, menentukan tampil atau tidaknya di dinding), `disembunyikan` (bool,
saklar fasilitator), dan `jumlah_foto`. Kolom v1 tetap.

Yang disajikan ke publik adalah `index.html` hasil rakitan, bukan berkas
sumber. Rakitan mengikuti `BACA-DULU.txt`: `<head>` memuat `/aset/dasar.css`
lalu `<style>` berisi gaya anak; `<body>` berisi `<div class="halaman">` + isi
anak, ditutup `<script src="/aset/blok.js">`.

Versi tersimpan: simpan 5 terbitan terakhir per anak. Draf yang belum terbit
disimpan di `localStorage` browser (seperti `editor-demo.html`), bukan di server.

## 6. Kontrak API

Semua POST berbadan JSON kecuali unggah foto; semua balasan JSON. Kode edit
dikirim di setiap permintaan (disimpan editor di `sessionStorage`), jadi server
tetap tanpa sesi.

| Route | Metode | Body | Balasan |
|---|---|---|---|
| `/api/buka` | POST | `{slug, kode}` | 200 `{isi, gaya, templat:{isi,gaya}, versi:[…], url}` · 401/404 digabung jadi satu pesan · 429 |
| `/api/terbit` | POST | `{slug, kode, isi, gaya}` | 200 `{url, peringatan:[…]}` · 401 · 422 (terlalu besar / kosong) · 429 |
| `/api/foto` | POST multipart | `slug, kode, berkas` | 200 `{src:"foto/<nama>"}` · 401 · 413 · 415 · 429 |
| `/api/versi` | POST | `{slug, kode, id}` | 200 `{isi, gaya}` dari versi itu (dimuat ke editor, belum terbit) |

Perubahan perilaku dari v1:

- Slug yang belum ada selalu 404 untuk semua rute API. **Auto-create slug dari
  v1 (§8.5) dihapus**; semua halaman berasal dari skrip seeding (§10).
- `peringatan` berisi daftar hal yang dibuang sanitasi ("baris `<script>`
  dihapus"), ditampilkan ke anak sebagai bahan belajar, bukan sebagai error.
- Batas body: 64 KB untuk `isi`, 16 KB untuk `gaya`, 1 MB untuk satu foto.
- Percobaan kode salah dibatasi: 10 kali / 5 menit per slug, lalu 429.

## 7. Editor — kebutuhan fungsional

Basis: `editor-demo.html`. Yang sudah ada di demo dan tinggal disambungkan ke
server ditandai (demo).

1. Dua tab kode: **Isi** dan **Gaya**. CSS dasar dan skrip blok tidak tampil. (demo)
2. Pratinjau langsung ≤ 0,5 detik setelah ketikan berhenti, di `<iframe sandbox>`. (demo)
3. Pengaman salah ketik: auto-close tag; bila tag tidak seimbang, muncul bilah
   kuning dan pratinjau tetap menampilkan versi benar terakhir. Pratinjau tidak
   boleh kosong. (demo)
4. **Sisipkan blok**: menempel di bawah penanda `<!-- ===== BLOK TAMBAHAN … -->`
   dan menyorot nilai pertama yang perlu diganti. (demo)
5. **Unggah foto**: diperkecil ke ≤ 800 px di browser, dikirim ke `/api/foto`,
   `src` yang sedang disorot diganti dengan hasilnya. (demo: baru sisi klien)
6. **Kembalikan ke contoh** per tab, dari `templat` balasan `/api/buka`. (demo)
7. Simpan draf otomatis di `localStorage`, per slug. (demo)
8. **Terbitkan** → panel hasil: alamat, QR, tombol **Bagikan** (Web Share API,
   cadangan salin tautan), daftar peringatan sanitasi bila ada.
9. Daftar **versi tersimpan** (5 terakhir) yang bisa dimuat kembali.
10. Mode HP: satu kolom, tab Kode/Pratinjau. (demo)
11. Penyorotan sintaks ringan. Pustaka apa pun di-vendor ke `/aset/`, tidak
    dari CDN. Prioritas paling rendah; boleh menyusul setelah sesi pertama.

## 8. Katalog blok

Tujuh blok, definisi tampilannya di `dasar.css`, perilakunya di `blok.js`:

A Kutipan · B Galeri 3 foto · C Lima teratas · D Lencana ·
E Tombol rahasia (`data-pesan`) · F Hitung mundur (`data-tanggal`, `data-label`) ·
G Hujan emoji (`data-emoji`, `data-jumlah` ≤ 60).

Berbeda dari v1, blok bukan lagi templat PHP yang dipilih lewat id. Blok adalah
**cuplikan HTML yang ditempel ke kode anak**, lalu anak mengubah isinya. Anak
tidak menulis JavaScript; ia hanya mengubah atribut `data-…`.

Tiga blok v1 (`tiga-hal`, `lagu-favorit`, `sosial`) dihapus. Tombol WA, peta,
dan tautan media sosial **sengaja tidak disediakan** karena alasan privasi.

## 9. Keamanan — checklist wajib

v1 aman karena klien tidak pernah mengirim markup. v2 menerima HTML dan CSS
dari anak, jadi garis pertahanannya pindah ke sanitasi server + CSP. Checklist
ini syarat sebelum dipakai anak-anak.

**Tetap dari v1**

- [ ] Validasi slug sebelum menyentuh filesystem; `/../../etc/passwd` ditolak.
- [ ] Penulisan atomik; kode edit hanya sebagai hash bcrypt.
- [ ] Rate limit terbit per slug dan global.
- [ ] `X-Content-Type-Options: nosniff`, `Referrer-Policy: no-referrer`.

**Baru di v2**

- [ ] **Sanitasi HTML berbasis daftar izin**, bukan daftar buang. Isi diurai
      dengan `Dom\HTMLDocument` (PHP 8.4) lalu ditulis ulang. Tag yang
      diizinkan: `header section footer div span p h1 h2 h3 ul ol li img a b i
      strong em small br hr blockquote button`. Atribut yang diizinkan:
      `class id alt title src href` dan `data-*`. Selain itu dibuang, termasuk
      `script iframe object embed form input style link meta base` dan semua
      atribut `on*` dan `style`.
- [ ] `src` hanya boleh jalur relatif `foto/<nama-berkas>`; selain itu diganti
      gambar contoh. `href` hanya `https://`; tautan diberi
      `rel="noopener nofollow"`. `javascript:`, `data:`, dan `//host` ditolak.
- [ ] **Sanitasi CSS** tab Gaya: buang `@import`, `url(…)`, `expression`,
      `</style`, dan komentar yang tidak tertutup. Deklarasi lain dibiarkan
      supaya anak yang penasaran tetap bisa bereksperimen.
- [ ] **CSP halaman publik**:
      `default-src 'none'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self'; base-uri 'self'; form-action 'none'; frame-ancestors 'self'`.
      `script-src 'self'` menggantikan `'none'` dari v1 karena `blok.js` harus
      jalan; skrip sebaris dari anak tetap tidak bisa dieksekusi walau lolos
      sanitasi.
- [ ] `blok.js` hanya memakai `textContent` / `createElement` untuk nilai dari
      atribut `data-…`, tidak pernah `innerHTML`.
- [ ] **Foto**: periksa isi berkas dengan `getimagesize` (JPEG/PNG/WebP saja),
      **encode ulang dengan GD** untuk membuang EXIF (termasuk koordinat GPS),
      nama berkas dibuat server, maksimum 8 foto dan 1 MB per foto per anak.
      `Dockerfile` perlu menambah ekstensi `gd`.
- [ ] Pratinjau editor berjalan di `<iframe sandbox="allow-scripts">` tanpa
      `allow-same-origin`, sehingga kode yang sedang diketik tidak bisa membaca
      `sessionStorage` berisi kode edit.
- [ ] Pembatasan percobaan kode salah (§6).
- [ ] `X-Robots-Tag: noindex` di semua halaman anak dan dinding karya.
- [ ] Saklar `disembunyikan` di `meta.json`: halaman kembali menampilkan
      `belum-ada.html` dan hilang dari dinding tanpa menghapus datanya.

## 10. Seeding sebelum hari-H (Bagian 7 panduan, diperbarui)

Skrip CLI membaca CSV `nama,kelas,sekolah` dan menghasilkan:

1. Folder tiap anak berisi `meta.json`, `isi.html` dan `gaya.css` dari templat
   dengan **nama anak sudah terisi** di `<h1>`, serta `index.html` rakitannya.
   `sudah_terbit` masih `false`.
2. `kartu-<sekolah>.html` siap cetak, 8 kartu per A4: alamat halaman, QR,
   alamat `/masuk`, kode edit.
3. `kredensial-<sekolah>.csv` untuk arsip fasilitator.

Aturan slug tetap: nama depan huruf kecil, kembar menjadi `andi2`, daftar nama
cadangan §4 ditolak otomatis. Skrip aman dijalankan ulang: anak yang sudah ada
dilewati, tidak ditimpa.

**`POST /admin/seed` (ditambahkan 23 September 2026).** Logika di atas
sekarang ada di `app/seed.php` (`karya_seed_proses_csv()`), dipakai bersama
oleh `bin/seed.php` (CLI) dan rute HTTP ini — supaya fasilitator yang tidak
punya akses SSH/`docker exec` ke server tetap bisa mengimpor CSV. Alasannya
konkret: image produksi `php:8.4-cli-alpine` tidak punya `bash`, cuma `sh`,
dan banyak panel (termasuk terminal bawaan Dokploy) mengasumsikan `bash` —
lihat `docs/panduan-infra-pelatihan-web-smp-versi-siswa.md` Bagian 10 untuk
ceritanya.

Dijaga token, bukan akun — sejalan dengan filosofi aplikasi ini yang memang
sengaja tanpa sistem akun:

- Token dari environment variable `KARYA_ADMIN_TOKEN` (diatur lewat panel
  Environment Dokploy, **tidak pernah** di-commit). Kosong berarti rute ini
  mati total — menjawab 404 seperti alamat yang tidak dikenal, bukan "aktif
  tanpa kata sandi".
- Dibandingkan dengan `hash_equals()`, dan percobaan token salah dibatasi
  5 kali/15 menit secara global (`karya_ratelimit_check_admin_wrong_attempts()`
  di `app/ratelimit.php`) — lebih ketat dari batas kode edit anak (10/5 menit
  per slug) karena token ini bisa membuat anak di sekolah mana pun, bukan
  cuma mengambil alih satu slug.
- `multipart/form-data`, field `csv` (maksimum `KARYA_MAX_SEED_CSV_BYTES` =
  2 MB), header `Authorization: Bearer <token>`.
- Responsnya satu `.zip` berisi `ringkasan.txt` (log yang sama persis dengan
  keluaran CLI) plus `kartu-<sekolah>.html` dan `kredensial-<sekolah>.csv`
  untuk tiap sekolah yang tersentuh di jalankan itu — satu kali bolak-balik,
  tidak perlu `scp` kedua untuk mengambil folder `_keluaran/`.

Contoh pemakaian:

```bash
curl -X POST https://karya.labpplg.web.id/admin/seed \
  -H "Authorization: Bearer <token>" \
  -F "csv=@siswa.csv" \
  -o hasil-seed.zip
```

Diuji lokal (bukan di produksi): token kosong/salah, rate limit token salah,
CSV valid campuran jalur `web`+`scratch`, jalankan ulang CSV yang sama
(idempoten, sama seperti CLI), dan regresi `bin/seed.php` serta
`bin/uji-sb3.php` tidak berubah perilakunya. Belum diuji: dari `curl`
sungguhan di produksi dengan `KARYA_ADMIN_TOKEN` yang sungguhan diset di
Dokploy.

## 11. Dinding karya

`/smp-<nama-sekolah>` menampilkan kartu tiap anak dari sekolah itu yang
`sudah_terbit` dan tidak `disembunyikan`: nama depan, warna utama pilihannya,
tautan ke halamannya. Dirender server dari `meta.json`, tanpa JavaScript.
Dipakai di fase 5 rundown (menit 65–85).

## 12. Kebutuhan non-fungsional

- **Beban:** 28 anak menerbitkan hampir bersamaan di sekitar menit ke-25 dan
  menit ke-85, ditambah unggah foto di menit 25–45. Sanitasi + rakit satu
  halaman harus selesai < 200 ms.
- **Jaringan lab:** tidak ada aset dari luar. Fon, pustaka QR, dan penyorot
  sintaks semuanya dari `/aset/`.
- **Perangkat:** utama komputer/laptop lab; HP untuk melihat dan perbaikan
  kecil. (Berkas paparan `.pptx` masih menulis "cukup browser — bisa lewat HP
  maupun komputer" dan perlu disesuaikan.)
- **Konkurensi:** `PHP_CLI_SERVER_WORKERS` baru teruji sungguhan di container
  Linux; uji beban 30 terbit serentak masuk ke M6.
- **Backup:** `/data/karya` disalin sesudah tiap sesi (Bagian 9 panduan).

## 13. Dampak ke kode yang ada

| Berkas | Nasib di v2 |
|---|---|
| `app/storage.php`, `app/ratelimit.php` | dipakai, ditambah fungsi versi + foto dan pembatas kode salah |
| `app/security.php` | dipakai; CSP diganti; `karya_clamp_text` tidak lagi dipakai di jalur terbit |
| `app/config.php` | dipakai; daftar nama cadangan diperluas; tambah direktori templat |
| `index.php` | router ditulis ulang untuk skema alamat §4; auto-create dihapus |
| `app/editor_view.php` | **diganti** editor kode (dari `editor-demo.html`) |
| `app/blocks.php` | **dihapus**; katalog pindah ke cuplikan HTML + `dasar.css` + `blok.js` |
| `app/render.php` | **ditulis ulang** jadi perakit halaman |
| baru: `app/sanitize.php` (M4, selesai), `aset/` (M4, selesai), `app/foto.php` (M5), `app/dinding.php` (M6), `bin/seed.php` (M6 — `bin/dev-seed.php` yang ada sekarang cuma alat dev sekali pakai, bukan ini) | — |
| `Dockerfile` | salin `aset/` dan healthcheck ke `/masuk` sudah dikerjakan (M4); tambah ekstensi `gd` masih menyusul di M5 bersama `app/foto.php` |
| `docker-compose.yml` | **berubah**, bukan seperti dugaan awal: label Traefik manual (`traefik.http.routers.karyaweb...`) dihapus M4 setelah deploy pertama ke Dokploy 502 — labelnya bentrok dengan router yang di-generate otomatis oleh Dokploy dari domain resource (host+port yang dikonfigurasi lewat UI/API Dokploy, bukan lewat label compose). Routing sekarang sepenuhnya lewat domain resource Dokploy, sama seperti aplikasi lain di instance yang sama. |

## 14. Milestone

- **M4 — inti v2. ✅ Selesai, 18 September 2026.** Router baru, `/masuk`,
  `/<slug>/edit` dengan editor kode, `/api/buka` + `/api/terbit`, sanitasi
  HTML/CSS, perakit halaman, CSP baru. Lolos bila halaman contoh bisa
  disunting dan terbit, dan seluruh uji sanitasi (§9) lolos — diverifikasi
  langsung di `karya.labpplg.web.id` (bukan cuma lokal), termasuk dua bug
  nyata di sanitizer yang cuma muncul di PHP 8.4 sungguhan (`Dom\HTMLDocument`
  tidak tersedia di PHP lokal 8.3): `createEmpty()->body` ternyata bisa
  `null`, dan `LIBXML_NOWARNING` bukan flag yang valid untuk
  `createFromString()`. Item checklist §9 yang belum berlaku: re-encode
  foto (menunggu `/api/foto`, M5) dan saklar `disembunyikan` (field ada di
  `meta.json` tapi belum dibaca di mana pun, menunggu dinding karya, M6).
- **M5 — foto, versi, hasil terbit. Kode selesai, belum diverifikasi di
  produksi.** `/api/foto` (`app/foto.php`: `getimagesize` untuk deteksi tipe
  dari isi berkas, re-encode lewat GD yang membuang EXIF/GPS, batas 8 foto
  dan 1 MB per anak), versi tersimpan, panel QR + Bagikan ada di kode. Belum
  dikerjakan: uji manual seluruh 7 blok di halaman publik yang sudah di-deploy,
  dan verifikasi jalur foto di server sungguhan (container Linux dengan
  ekstensi `gd`, bukan cuma lokal).
- **M6 — seeding, dinding, deploy. Kode selesai, deploy dan uji belum
  dikerjakan.** `bin/seed.php` + kartu cetak, `app/dinding.php`, saklar
  `disembunyikan` (dibaca di `index.php`, mengembalikan `belum-ada.html` tanpa
  menghapus data) — semua ada di kode dan sudah lewat dua perbaikan lanjutan
  (`gd` + `bin/` di `Dockerfile`, fix deprecation `fgetcsv()` PHP 8.4). Masih
  perlu: `docker build` dan deploy build ini ke Dokploy, `bin/uji-beban.php`
  (30 terbit serentak) dijalankan di server sungguhan, dan gladi dengan 3–5
  siswa PPLG sebagai peserta — kriteria lolos M6 belum terpenuhi sampai
  ketiganya selesai.
- **M7 — sesudah sesi pertama.** Penyorotan sintaks, perbaikan dari temuan
  lapangan.

M3b di v1 (seeding + blok tambahan) melebur ke M5 dan M6.

## 15. Pertanyaan terbuka

1. **Tautan keluar.** Apakah `<a href="https://…">` diizinkan sama sekali?
   Draf ini mengizinkan; alternatif paling ketat adalah membuang semua `href`
   karena katalog memang tidak menyediakan blok tautan.
2. **Dinding karya publik atau tidak.** Alamatnya bisa ditebak dari nama
   sekolah. Pilihan: tetap terbuka dengan `noindex` (draf ini), atau diberi
   akhiran acak pada alamat.
3. **Masa berlaku kode edit.** Berlaku selamanya supaya anak bisa lanjut dari
   rumah, atau dimatikan sesudah N minggu?
4. **Masa simpan halaman.** Berapa lama halaman kohort dipertahankan, dan siapa
   yang memutuskan penghapusannya.
5. **Persetujuan foto.** Anak akan mengunggah foto diri. Perlu diputuskan
   apakah pemberitahuan ke sekolah/orang tua cukup lewat kartu, atau templat
   bawaan memakai avatar dan foto diri bersifat opsional.
6. **Izin tulis volume.** Proses PHP di container perlu hak tulis ke
   `/data/karya` di host; UID/GID-nya belum ditetapkan.

## 16. Referensi

- `docs/PRD-karyaweb.md` — PRD v1, catatan implementasi M1–M3a.
- `docs/panduan-infra-pelatihan-web-smp-versi-siswa.md` — masih berlaku untuk
  Bagian 3 (tiga larangan), 4, 6, 9, 10, 11. Bagian 5, 7, 8 dibaca dengan
  penyesuaian dokumen ini. **Larangan 2 ("blok adalah templat server, anak
  hanya mengirim id") tidak lagi berlaku harfiah**; penggantinya adalah §9.
- Modul Jalur A dan folder `halaman-contoh-web/`.
