# PRD — Jalur Scratch (unggah dan mainkan karya `.sb3`)

Tanggal: 21 September 2026 (status diperbarui 23 September 2026) · Status:
**M-S1 selesai** (`app/sb3.php`, `/api/unggah`, `/<slug>/karya.sb3`, gerbang
`/masuk` tunggal — komit `5fc9887`). **M-S2 dan M-S3 selesai dari sisi kode,
belum diverifikasi di peramban sungguhan** — lihat status rinci di §10.
Lisensi bundel pemutar (§7/§11.1) sudah diputuskan: **opsi A (BSD-3)**.

Rencana implementasi M-S2/M-S3 ada di
`docs/TASK-M-S2-M-S3-jalur-scratch.md`; berkas itu tetap dipertahankan sebagai
catatan desain meskipun implementasinya sudah ada, karena berisi alasan di
balik tiap keputusan (CSP, alias bundel, kontrak API) yang tidak semuanya
terlihat dari membaca kode saja.

Dokumen pendamping `docs/PRD-karyaweb-v2.md`. PRD v2 tetap berlaku penuh untuk
jalur web dan tidak diubah oleh dokumen ini; yang ditambahkan di sini bersifat
modul baru di sebelahnya. Bila keduanya bertentangan soal jalur Scratch,
dokumen ini yang berlaku; soal jalur web, PRD v2 yang berlaku.

Keputusan dasar (21 Sep 2026): **satu repo, satu deployment, satu namespace
slug, ditambah dimensi `jalur`** — bukan aplikasi terpisah. Alasannya bukan
penghematan kode, melainkan karena seeding, kartu cetak, kode edit, daftar nama
cadangan, dan dinding karya memang harus tunggal. Dua aplikasi di satu domain
akan berbagi namespace slug tanpa ada yang menengahi, dan anak yang ikut dua
jalur akan pulang membawa dua kartu dengan dua kode berbeda.

## 1. Latar belakang dan tujuan

Jalur B pelatihan SMP adalah membuat game platformer di Scratch, 2×45 menit,
berangkat dari berkas `game-platformer-pplg.sb3` (60 KB; sprite Tokoh, Level,
Koin, plus tiga mekanik yang blok topinya sengaja dilepas). Anak menyunting di
aplikasi Scratch, bukan di aplikasi kita.

Masalahnya, jalur B berakhir dengan sebuah berkas di komputer lab. Anak pulang
tanpa membawa apa pun yang bisa ditunjukkan ke orang tua, sementara anak jalur
web pulang membawa alamat dan QR. Tujuan dokumen ini menutup jurang itu: anak
mengunggah `.sb3`-nya, mendapat alamat publik yang bisa langsung **dimainkan**
di peramban, dengan QR dan tombol Bagikan yang bentuknya persis sama.

Ukuran keberhasilan: setiap anak jalur B sudah mengunggah sekali dan melihat
gamenya berjalan di halamannya sendiri sebelum sesi berakhir.

## 2. Alur anak

1. Menyunting game di Scratch (aplikasi luring di komputer lab atau
   `scratch.mit.edu`), lalu **File → Save to your computer** → dapat `.sb3`.
2. Buka `karya.labpplg.web.id/masuk`, ketik nama halaman + kode dari kartu.
3. Karena `meta.json`-nya berjalur `scratch`, `/masuk` mengarahkan ke
   `/<slug>/unggah`, bukan ke editor kode.
4. Pilih atau seret berkas `.sb3` → unggah → halaman langsung memutar hasilnya
   sebagai pratinjau, **sebelum** diterbitkan.
5. Klik **Terbitkan** → panel hasil: alamat, QR, tombol Bagikan — komponen yang
   sama persis dengan M5 jalur web.
6. Orang tua membuka `/<slug>`, menekan bendera hijau, memainkan gamenya.

Anak boleh mengunggah ulang berkali-kali; unggahan terakhir yang terbit.

## 3. Skema alamat

Menambah pada §4 PRD v2, tidak mengubah yang sudah ada:

| Alamat | Fungsi |
|---|---|
| `/<slug>` | halaman pemutar (jalur `scratch`) atau halaman web (jalur `web`) — dipilih dari `meta.jalur` |
| `/<slug>/unggah` | halaman unggah; padanan `/<slug>/edit` untuk jalur Scratch |
| `/<slug>/karya.sb3` | berkas `.sb3` terbitan, untuk dimuat pemutar dan untuk diunduh anak |
| `/api/unggah` | POST multipart: terima, validasi, susun ulang, simpan |
| `/api/terbit-sb3` | POST JSON: tandai unggahan terakhir sebagai terbit |
| `/aset/scratch/…` | bundel pemutar + lisensinya |

`/masuk` tetap satu pintu untuk kedua jalur dan mengarahkan sesuai
`meta.jalur`. `/<slug>/edit` pada slug berjalur `scratch` (dan `/<slug>/unggah`
pada slug berjalur `web`) menjawab 404, bukan mengarahkan — supaya kartu yang
salah cetak ketahuan saat gladi, bukan saat sesi berjalan.

Nama cadangan bertambah: `karya.sb3` tidak mungkin jadi slug karena ada titik,
tapi `unggah` dan `main` ditambahkan ke `KARYA_RESERVED_SLUGS` untuk berjaga.

## 4. Data model

```
<slug>/
  meta.json          + jalur, + sb3_bytes, + sb3_sha256, + sb3_diunggah
  karya.sb3          berkas terbitan, hasil susun ulang server
  draf.sb3           unggahan terakhir yang belum diterbitkan
  versi/<ts>.sb3     2 terbitan terakhir
```

`meta.json` mendapat `jalur` berisi `"web"` atau `"scratch"`. Berkas yang sudah
ada tanpa kolom itu diperlakukan sebagai `"web"`, sehingga seluruh data jalur
web yang sudah ter-seed tidak perlu dimigrasi.

Yang dilayani ke publik adalah `karya.sb3` hasil susun ulang, bukan berkas
mentah yang diunggah anak — sejajar dengan prinsip foto di M5, yang disajikan
adalah hasil re-encode, bukan berkas asli.

Versi tersimpan dibatasi 2 (bukan 5 seperti jalur web) karena satu `.sb3` jauh
lebih besar daripada sepasang `isi.html` + `gaya.css`.

## 5. Validasi `.sb3` — checklist wajib

Ini inti keamanan jalur Scratch dan menggantikan peran `app/sanitize.php`, yang
sama sekali tidak terpakai di sini. `.sb3` adalah zip, jadi ancamannya ancaman
zip, bukan ancaman HTML.

- [ ] Ukuran berkas ≤ 20 MB. Templat kita 60 KB; batas ini memberi ruang untuk
      kostum dan suara tambahan tanpa membuka pintu penyalahgunaan penyimpanan.
- [ ] Dibuka dengan `ZipArchive`; zip terenkripsi ditolak.
- [ ] Jumlah entri ≤ 500. Total ukuran setelah dikembangkan ≤ 60 MB, dan rasio
      kembang tiap entri ≤ 100× — dua pagar zip bomb.
- [ ] Nama tiap entri harus persis `project.json` atau
      `^[0-9a-f]{32}\.(svg|png|jpg|jpeg|bmp|gif|wav|mp3)$`. Tidak ada garis
      miring sama sekali, sehingga path traversal (`../`), jalur absolut, dan
      folder sisa seperti `__MACOSX/` gugur oleh aturan yang sama. Entri lain
      apa pun membuat seluruh berkas ditolak, bukan dibuang diam-diam.
- [ ] `project.json` ≤ 5 MB, JSON yang sah, punya `targets` berupa larik dan
      `meta.semver`. Tiap `assetId`/`md5ext` yang dirujuk harus ada entrinya di
      dalam zip; rujukan ke aset luar ditolak, supaya halaman tidak pernah
      menarik apa pun dari `assets.scratch.mit.edu`.
- [ ] Tipe tiap entri aset diperiksa dari isinya sendiri (`getimagesize`, atau
      pengecekan magic bytes untuk wav/mp3), bukan dari ekstensinya. Aset SVG
      diperlakukan sebagai teks yang harus lolos pemeriksaan: tanpa `<script>`,
      tanpa atribut `on*`, tanpa `<foreignObject>`, tanpa rujukan eksternal.
      **Ini satu-satunya titik di jalur Scratch tempat markup dari anak masih
      mungkin masuk**, karena kostum Scratch memang SVG, jadi pemeriksaannya
      tidak boleh dilewati.
- [ ] **Susun ulang, jangan simpan mentah.** Setelah semua entri lolos, server
      membuat zip baru berisi hanya entri yang lolos, ditulis lewat `.tmp` lalu
      `rename()`. Berkas unggahan asli dibuang. Dengan begitu tidak ada bidang
      zip aneh, komentar zip, atau data tersembunyi yang ikut terbit.
- [ ] Batas unggah: 1 per 10 detik per slug, 20 per menit global. Pembatas
      percobaan kode salah §6 PRD v2 berlaku apa adanya.
- [ ] `/<slug>/karya.sb3` dilayani dengan
      `Content-Type: application/octet-stream`, `nosniff`, dan
      `Content-Disposition: attachment` — supaya tidak pernah dirender sebagai
      dokumen oleh peramban.

## 6. Halaman pemutar

`/<slug>` merender halaman statis berisi panggung 480×360, tombol bendera hijau
dan berhenti, tombol layar penuh, nama anak, dan tautan "Unduh berkasnya".
Pemutar memuat `/<slug>/karya.sb3` sebagai `ArrayBuffer` lalu menyerahkannya ke
VM; seluruh aset sudah ada di dalam berkas itu, jadi tidak ada satu pun
permintaan keluar dari jaringan lab.

**Tombol sentuh (ditambahkan 23 September 2026, dari uji peramban di HP).**
`scratch-vm` tidak mendengarkan DOM sendiri — papan ketik dan mouse sungguhan
didorong masuk lewat `vm.postIOData()` (lihat perbaikan M-S2 di §10), yang
berarti di HP tanpa papan ketik game tidak bisa dikendalikan sama sekali.
Halaman pemutar menambahkan D-pad tetap (panah atas/bawah/kiri/kanan + tombol
spasi) yang hanya tampil lewat CSS `@media (pointer:coarse)` — di PC berpointer
halus (mouse) tombolnya tersembunyi karena papan ketik sungguhan sudah cukup.
Setiap tombol memanggil `Pemutar.tombol(key, isDown)` (diekspos dari
`bin/pemutar/src/glue.js`), yang lewat jalur `vm.postIOData()` yang sama persis
dengan papan ketik sungguhan — bukan `KeyboardEvent` tiruan. Tombolnya **tetap
panah+spasi**, bukan hasil pindai `project.json` per game — lihat §13 untuk
gagasan tombol kustom yang sengaja belum dikerjakan.

CSP halaman pemutar berbeda dari CSP halaman web di §9 PRD v2 dan perlu
diverifikasi empiris sebelum dianggap final:

```
default-src 'none'; script-src 'self'; style-src 'self' 'unsafe-inline';
img-src 'self' blob: data:; media-src 'self' blob: data:;
connect-src 'self'; worker-src 'self' blob:; base-uri 'self';
form-action 'none'; frame-ancestors 'self'
```

`blob:` dan `data:` pada `img-src`/`media-src` diperlukan karena kostum dan
suara dimuat dari memori, bukan dari URL. Yang **harus dibuktikan tidak
diperlukan** adalah `'unsafe-eval'`: VM Scratch adalah interpreter, secara
prinsip tidak perlu `eval`, tetapi ini wajib diuji langsung di peramban sebelum
M-S2 dinyatakan lolos. Kalau ternyata perlu, itu keputusan yang harus dibahas
ulang, bukan dilonggarkan diam-diam.

## 7. Bundel pemutar dan lisensinya

Yang dibundel hanya `scratch-vm`, `scratch-render`, `scratch-svg-renderer`,
`scratch-storage`, dan `scratch-audio` — **bukan** `scratch-gui`. Kita hanya
memutar, tidak menyediakan editor, sehingga seluruh antarmuka Scratch tidak
ikut. (`scratch-audio` ditambahkan 23 September 2026 setelah uji peramban
menunjukkan game berjalan bisu dan tanpa kostum raster — lihat §10 M-S2.)

Temuan lisensi, diperiksa 21 Sep 2026 di registry npm (`scratch-audio`
ditambah 23 Sep 2026 dengan cara yang sama):

| Paket | Versi terbaru | Lisensi sekarang | Versi BSD-3 terakhir |
|---|---|---|---|
| `scratch-vm` | 5.0.300 | AGPL-3.0-only | 4.8.115 |
| `scratch-render` | 2.2.84 | AGPL-3.0-only | 1.2.126 |
| `scratch-svg-renderer` | 3.1.19 | AGPL-3.0-only | 2.5.46 |
| `scratch-storage` | 6.2.1 | AGPL-3.0-only | 3.0.39 |
| `scratch-audio` | 2.0.268 | AGPL-3.0-only | 1.0.332 |

`scratch-audio` relicense ke AGPL terjadi pada lompatan versi mayor
`1.0.332` → `2.0.0`, dipublikasikan 25 November 2024 — tanggal yang sama
persis dengan keempat paket lain, jadi ini tetap satu relicense terkoordinasi,
bukan jadwal terpisah.

Seluruh paket berpindah dari BSD-3-Clause ke AGPL-3.0-only pada rilis mayor
25 November 2024. Konsekuensinya nyata: menyajikan bundel AGPL ke peramban
pengunjung adalah penyaluran, dan pasal 13 AGPL mewajibkan menawarkan
Corresponding Source kepada orang yang berinteraksi dengannya lewat jaringan.
Menggabungkan kode perekat kita ke dalam bundel yang sama membuat perekat itu
ikut terikat.

Dua pilihan, dan ini keputusan Bapak, bukan keputusan saya:

**A. Pakai versi BSD-3 terakhir** (`scratch-vm@4.8.115` dan pasangannya).
Tanpa kewajiban copyleft sama sekali, repo tetap seperti sekarang. Risikonya
versi itu tidak lagi mendapat perbaikan sejak akhir 2024, dan `.sb3` yang
dibuat dengan editor Scratch yang jauh lebih baru berpotensi memakai blok yang
belum dikenal. Untuk kasus kita risiko itu kecil: semua berkas berangkat dari
templat kita sendiri yang hanya memakai blok klasik, dan format `project.json`
3.0.0 sudah stabil sejak 2018.

**B. Pakai versi terkini yang AGPL.** Dapat perbaikan terbaru, konsekuensinya
sumber harus ditawarkan ke pengunjung. Secara praktis: taruh bundel beserta
`LICENSE` di `aset/scratch/`, pisahkan kode perekat kita ke berkas tersendiri
di folder yang sama dan lisensikan AGPL juga, lalu pasang tautan sumber di kaki
halaman pemutar. Untuk repo sekolah yang memang bisa dipublikasikan, ini tidak
memberatkan — hanya perlu disadari sejak awal, bukan ditemukan belakangan.

Rekomendasi saya **A untuk sesi pertama**, karena memindahkan satu keputusan
lisensi ke luar jalur kritis menjelang hari-H, dan B sebagai langkah sadar
setelahnya bila ingin mengikuti hulu.

**Keputusan (23 September 2026): opsi A.** Bundel dibangun dari
`scratch-vm@4.8.115`, `scratch-render@1.2.126`, `scratch-svg-renderer@2.5.46`,
`scratch-storage@3.0.39`, `scratch-audio@1.0.332` — versi BSD-3-Clause
terakhir sebelum masing-masing berpindah ke AGPL-3.0-only pada 25 November
2024. Tidak ada kewajiban copyleft; tidak perlu `LICENSE` publik atau tautan
sumber di kaki halaman pemutar. Opsi B tetap terbuka sebagai langkah sadar di
kemudian hari.

## 8. Cara membangun bundel

Bundel dibangun **sekali di mesin pengembangan** lalu di-commit sebagai
`aset/scratch/pemutar.js`, bukan dibangun di dalam image Docker. `package.json`
dan skrip bangunnya ikut di-commit di `bin/pemutar/` supaya hasilnya bisa
diulang siapa pun.

Alasannya: menambahkan tahap Node ke `Dockerfile` berarti setiap build image —
termasuk build jalur web yang tidak ada urusannya dengan Scratch — menanggung
pemasangan npm. Dokploy membangun langsung dari repo, jadi bundel yang sudah
ada di repo langsung terpakai. Harganya: satu berkas besar masuk git, dan
pembaruan bundel dikerjakan manual. Untuk aplikasi yang di-deploy sekali per
kohort, itu pertukaran yang sepadan.

## 9. Perubahan pada kode yang sudah ada

| Berkas | Perubahan |
|---|---|
| `app/config.php` | tambah `KARYA_MAX_SB3_BYTES`, batas entri/rasio zip, `KARYA_MAX_VERSI_SB3`, dua nama cadangan |
| `index.php` | router bercabang pada `meta.jalur`; rute `/<slug>/unggah`, `/<slug>/karya.sb3`, `/api/unggah`, `/api/terbit-sb3` |
| `app/storage.php` | tambah jalur berkas `.sb3` dan versinya; `karya_save_text_atomic()` dapat padanan biner |
| `app/dinding.php` | kartu anak diberi penanda kecil jalur (halaman / game); satu dinding memuat keduanya |
| `bin/seed.php` | kolom CSV opsional `jalur` (kosong berarti `web`); templat kartu cetak berbeda per jalur — jalur Scratch mencetak alamat `/masuk` dan pengingat menyimpan `.sb3` ke komputer |
| `app/security.php` | tambah `karya_send_pemutar_page_headers()` (CSP §6 khusus halaman pemutar) — fungsi-fungsi lain di berkas ini tidak berubah |
| `Dockerfile` | tambah ekstensi `zip`; `php.ini` untuk `upload_max_filesize=25M` dan `post_max_size=26M`, karena `KARYA_MAX_BODY_BYTES` hanya mengatur badan JSON dan tidak berlaku untuk multipart |
| baru | `app/sb3.php` (validasi + susun ulang), `app/pemutar_view.php`, `app/unggah_view.php`, `aset/scratch/` (bundel + glue halaman), `bin/pemutar/` (skrip bangun bundel) |

Tidak berubah: `app/sanitize.php`, `app/foto.php`, `app/editor_view.php`,
`app/render.php`, `app/ratelimit.php`, `docker-compose.yml`. Jalur web tidak
tersentuh sama sekali.

## 10. Milestone

- **M-S1 — validasi.** `app/sb3.php` + `/api/unggah` + `/<slug>/karya.sb3`.
  Lolos bila: `game-platformer-pplg.sb3` diterima utuh dan bisa diunduh
  kembali; dan seluruh berkas uji negatif ditolak — zip dengan entri `../`,
  zip bomb, `project.json` yang merujuk aset tak ada, SVG berisi `<script>`,
  zip terenkripsi, berkas non-zip yang dinamai `.sb3`.
- **M-S2 — pemutar.** Bundel di `aset/scratch/`, halaman `/<slug>`, CSP §6
  diverifikasi di peramban sungguhan (termasuk pembuktian bahwa
  `'unsafe-eval'` tidak diperlukan). Lolos bila game berjalan penuh: tokoh
  berlari, melompat, koin menambah skor, jatuh mengembalikan ke titik mulai —
  dan versi demo mekanik juga berjalan.

  **Selesai dari sisi kode (23 September 2026), belum lolos kriteria di
  atas.** `bin/pemutar/` membangun `aset/scratch/pemutar.js` (5,7 MB) dari
  versi BSD-3 yang diputuskan di §7 — bundel itu sudah benar-benar dibangun
  dan di-commit, bukan kerangka kosong. `app/pemutar_view.php` +
  `karya_send_pemutar_page_headers()` diverifikasi lewat server PHP lokal:
  rute `/<slug>` merender halaman pemutar dengan header CSP **persis** teks
  di §6, `/aset/scratch/pemutar.js` tersaji dengan `Content-Type` yang benar,
  dan `/<slug>/karya.sb3` tersaji dengan `Content-Disposition: attachment`.
  **Belum diverifikasi:** game sungguhan berjalan di kanvas WebGL (bendera
  hijau, lompat, koin, jatuh) dan pembuktian empiris bahwa `'unsafe-eval'`
  memang tidak diperlukan — keduanya tidak bisa dicek lewat `curl`/PHP CLI,
  perlu peramban sungguhan.

  **Bug ditemukan + diperbaiki (23 September 2026, dari uji peramban
  pertama).** Game memuat (`vm.loadProject()` berhasil) tapi tampil tanpa
  kostum raster dan tanpa suara — konsol menunjukkan `Error loading bitmap
  image: No V2 Bitmap adapter present.` dan `No audio engine present; cannot
  load sound asset`. Sebabnya `bin/pemutar/src/glue.js` memanggil
  `vm.attachStorage()` dan `vm.attachRenderer()` tapi tidak pernah
  `vm.attachV2BitmapAdapter()` maupun `vm.attachAudioEngine()` — dua langkah
  yang scratch-gui selalu lakukan tapi TASK-M-S2-M-S3 tidak menyebutkannya
  secara eksplisit, jadi terlewat. Diperbaiki dengan menambah `scratch-audio`
  ke bundel (§7 di atas) dan memanggil `vm.attachV2BitmapAdapter(new
  BitmapAdapter())` (dari `scratch-svg-renderer`, sudah ada di bundel) +
  `vm.attachAudioEngine(new AudioEngine())` di `glue.js`. Diverifikasi ulang
  dengan me-reproduksi `game.sb3` sungguhan langsung lewat kode sumber
  `scratch-vm`/`scratch-storage` di Node (bukan lewat peramban): sebelum
  perbaikan pesan "No V2 Bitmap adapter present" muncul persis seperti di
  konsol peramban; sesudahnya pesan itu hilang. `scratch-audio` tidak bisa
  diuji lewat Node sama sekali (perlu Web Audio API sungguhan), jadi bagian
  suara masih menunggu konfirmasi di peramban.

  **Bug kedua ditemukan + diperbaiki (23 September 2026, uji peramban
  putaran kedua, setelah kostum/suara di atas beres).** Game tampil dan
  bendera hijau jalan, tapi tombol panah kiri/kanan tidak menggerakkan
  apa pun — tidak ada galat di konsol sama sekali, karena secara teknis
  tidak ada yang salah: `scratch-vm` tidak pernah mendengarkan DOM sendiri.
  Setiap tekanan tombol/klik harus didorong masuk lewat `vm.postIOData()`,
  persis seperti yang dilakukan `scratch-gui`, dan `glue.js` tidak
  melakukannya sama sekali — celah yang sama sifatnya dengan bitmap
  adapter/audio engine di atas (tidak disebutkan eksplisit di TASK, jadi
  terlewat). Diperbaiki dengan menambah `pasangPapanKetik()` (keydown/keyup
  di `document`, dijaga `sedangMengetik()` supaya tombol panah yang diketik
  di kolom kode/nama pada halaman unggah tidak dibajak game) dan
  `pasangMouse()` (mousemove/mousedown pada kanvas, mouseup di `window`) di
  `glue.js`, memanggil `vm.postIOData('keyboard', …)` /
  `vm.postIOData('mouse', …)` dengan bentuk data yang dicocokkan langsung ke
  `src/io/keyboard.js` dan `src/io/mouse.js` di `scratch-vm`. Kedua fungsi ini
  tidak bisa diuji lewat Node (perlu DOM/peramban sungguhan), jadi masih
  menunggu konfirmasi bahwa tokoh benar-benar bisa dikendalikan.

  Peringatan konsol lain yang dilaporkan saat uji ini **bukan bug**: "The
  AudioContext was not allowed to start" adalah kebijakan autoplay peramban
  yang normal (`scratch-audio` sudah menangani ini sendiri lewat paket
  `startaudiocontext`, pulih begitu ada interaksi pertama seperti klik Bendera
  hijau); "Unchecked runtime.lastError: Could not establish connection" adalah
  noise dari ekstensi peramban yang terpasang, tidak ada satu pun kode kita
  yang memanggil `chrome.runtime`; "Canvas2D:… willReadFrequently" adalah
  saran performa dari pustaka Scratch sendiri, bukan galat.

  **Temuan ketiga + diperbaiki (23 September 2026, uji di HP setelah PC
  lolos).** Game berjalan penuh di PC tapi tidak bisa dikendalikan sama
  sekali di HP — bukan bug, tapi keterbatasan yang diketahui sejak awal:
  layar sentuh tidak punya papan ketik. Ditambahkan D-pad tetap (panah +
  spasi) yang hanya tampil di perangkat berlayar sentuh — detail teknisnya
  di §6. Keputusan sadar: **D-pad tetap panah+spasi, bukan tombol yang
  dipindai dari `project.json` per game** — lihat §13 untuk gagasan itu,
  sengaja ditunda sebagai opsional.
- **M-S3 — alur anak.** `/<slug>/unggah`, pratinjau sebelum terbit,
  `/api/terbit-sb3`, panel QR + Bagikan, penanda jalur di dinding karya,
  `bin/seed.php` berkolom `jalur` dan kartu cetaknya.

  **Selesai dari sisi kode (23 September 2026), belum lolos kriteria di
  atas.** `app/unggah_view.php` ada dan lolos uji rute (`/<slug>/unggah`
  merender untuk jalur `scratch`, 404 untuk jalur `web` — sama seperti
  `/<slug>/edit` sebaliknya). Penanda jalur di dinding karya dan kolom
  `jalur` di `bin/seed.php` sudah ada dan terverifikasi tampil benar.
  **Belum diverifikasi:** alur penuh di peramban sungguhan — pilih/seret
  `.sb3`, pratinjau lokal benar-benar memutar, `/api/unggah` menerima dan
  mengizinkan Terbitkan, `/api/terbit-sb3` menerbitkan, panel QR+Bagikan
  tampil dan berfungsi.
- **M-S4 — deploy dan gladi.** Build dan deploy ke Dokploy, unggah dari
  komputer lab sungguhan lewat jaringan lab, gladi dengan 3–5 siswa PPLG
  memakai `.sb3` buatan mereka sendiri, bukan berkas templat. Belum dimulai —
  menunggu M-S2/M-S3 lolos verifikasi peramban di atas.

M-S1 dan M-S2 bisa dikerjakan paralel dengan sisa M5/M6 jalur web karena tidak
menyentuh berkas yang sama, kecuali `index.php` dan `app/config.php`.

## 11. Pertanyaan terbuka

1. ~~**Lisensi bundel** — pilihan A atau B di §7.~~ Diputuskan 23 September
   2026: opsi A. Lihat §7.
2. **Batas 20 MB** cukup atau tidak, bergantung apakah anak diizinkan merekam
   suara sendiri di Scratch. Perlu dicek saat gladi.
3. **Menyunting lanjut dari rumah.** Anak mengunduh `.sb3` dari halamannya,
   menyunting di rumah, lalu mengunggah ulang. Perlu dipastikan kode edit
   memang masih berlaku dari luar lab — terkait pertanyaan terbuka §15.3
   PRD v2 tentang masa berlaku kode edit.
4. **Suara dan kostum bawaan Scratch.** Anak kemungkinan memakai aset dari
   pustaka Scratch, yang ikut tersimpan di dalam `.sb3` dan otomatis ikut kita
   sajikan. Perlu dipastikan sekali bahwa ketentuan pemakaian pustaka Scratch
   mengizinkan penyajian ulang seperti ini di luar `scratch.mit.edu`. Saya
   belum memeriksanya.
5. **Anak yang ikut dua jalur.** Satu slug hanya punya satu jalur. Kalau ada
   anak yang ikut keduanya, apakah ia mendapat dua slug (`nadia` dan
   `nadia-game`) atau satu halaman yang memuat keduanya?
6. **Performa pemutar di komputer lab.** `scratch-render` memakai WebGL;
   spesifikasi komputer lab dan peramban yang terpasang di sana perlu dicek
   sebelum M-S4, bukan pada hari-H.

## 12. Referensi

- `docs/PRD-karyaweb-v2.md` — jalur web; §4, §6, §9, §10, §11 adalah pasangan
  langsung dari §3, §5, §5, §9, §9 dokumen ini.
- `docs/panduan-infra-pelatihan-web-smp-versi-siswa.md` — Bagian 3, 4, 6, 9,
  10, 11 tetap berlaku; Bagian 5, 7, 8 dibaca dengan penyesuaian.
- Dokumen project `claude/file-contoh-scratch-pelatihan-smp.md` — isi berkas
  templat, hasil verifikasi, dan penyesuaian tata letak level.
- Berkas templat: `[0] SPMB 2728/Claude outputs/game-platformer-pplg.sb3` dan
  `game-platformer-pplg-demo-mekanik.sb3`.

## 13. Backlog (opsional, belum dikerjakan)

Gagasan yang sengaja **ditunda**, bukan bagian dari kriteria lolos M-S2/M-S3
manapun. Dicatat di sini supaya tidak hilang, bukan supaya dikerjakan
sekarang.

- **Tombol sentuh kustom per game, dipindai dari `project.json`.** D-pad di
  §6 sekarang tetap: panah + spasi, untuk semua game, apa pun isinya. Kalau
  seorang anak menambahkan mekanik yang dikendalikan tombol lain (mis. `W`
  untuk lompat, atau tombol angka), D-pad tetap ini tidak akan
  menampilkannya, dan anak itu — atau siapa pun yang main lewat HP — tidak
  bisa mengendalikan mekanik tersebut sama sekali.

  Gagasannya: `project.json` sudah diuraikan di server saat unggah
  (`app/sb3.php`, untuk validasi §5). Blok `event_whenkeypressed` di dalamnya
  punya field yang menyebut tombol persis mana yang dipakai game itu. Kalau
  daftar tombol itu diekstrak saat unggah dan disimpan di `meta.json` (mis.
  `meta.tombol = ['left arrow', 'right arrow', 'space']`), halaman pemutar
  bisa merender **persis tombol yang game itu pakai** — bukan D-pad generik —
  dan anak bisa mengatur ulang tata letaknya sendiri (drag posisi, ukuran)
  kalau itu juga mau didukung.

  Kenapa ditunda: kompleksitas nyata (menguraikan pohon blok bukan sekadar
  membaca satu field, memetakan nama tombol Scratch ke label/ikon yang masuk
  akal untuk anak SMP, UI pengaturan tata letak yang butuh disimpan per game)
  untuk manfaat yang belum tentu perlu — D-pad tetap sudah menutupi kasus
  yang sejauh ini muncul (panah + spasi). Kerjakan ini kalau, setelah gladi
  sungguhan, ternyata banyak anak memang memakai tombol di luar itu.
