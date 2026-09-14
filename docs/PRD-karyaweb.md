# PRD — KaryaWeb (Bagian 5: Aplikasi Halaman Anak)

Status: M1 + M2 sudah diimplementasikan dan diuji manual di phpBro lokal
(`karya.lokal:8080`) — lihat `index.php` + `app/`. M3 (Bagian 6/7/8 lanjutan)
belum dikerjakan. Referensi utama:
`docs/panduan-infra-pelatihan-web-smp-versi-siswa.md` (tidak di-commit ke repo
ini, lihat `.gitignore` — dokumen internal/rujukan, bukan bagian dari aplikasi
yang di-ship).

## 1. Latar belakang & tujuan

Pelatihan 2×45 menit untuk 28 anak SMP. Tiap anak mendapat satu halaman web
pribadi yang bisa diakses di `karya.labpplg.web.id/<slug>` dan diedit lewat
form sederhana (tanpa menulis kode). Tidak ada database, tidak ada akun/login
— hanya filesystem + kode edit 6 karakter per anak.

Tujuan bagian ini (Bagian 5 di panduan sumber): membangun aplikasi web yang
melayani dua hal — menampilkan halaman anak, dan menerima publish dari
editor — dengan pagar keamanan yang ketat karena semua anak berbagi domain
yang sama dengan layanan lab lain.

## 2. Scope

**Dikerjakan sekarang** (Bagian 5 + katalog blok minimal dari Bagian 8, karena
`POST /api/terbit` tidak bisa berfungsi tanpa blok sama sekali):

- Routing: `GET /<slug>`, `GET /bikin`, `POST /api/buka`, `POST /api/terbit`
- Penyimpanan file di `/data/karya/<slug>/{index.html,meta.json}`
- Validasi slug ketat (`^[a-z0-9-]{2,20}$`), tanpa pengecualian
- Editor: form isian + pratinjau + tombol terbit
- Penulisan atomik (`.tmp` + `rename`)
- Kode edit disimpan sebagai hash (bcrypt via `password_hash`), tidak pernah
  plaintext
- Katalog blok server-side, minimal 3 (lihat §9)
- Header keamanan, escaping, rate limit, batas ukuran body — semua sesuai
  checklist di §6

**Belum dikerjakan sekarang** (fase lanjut, di luar scope PR ini):

- Bagian 6 — `docker-compose.yml`, label Traefik, deploy ke server lab
- Bagian 7 — skrip generator CSV → 28 folder + kartu cetak
- Bagian 8 — blok tambahan di luar 3 yang minimal
- Bagian 4 — DNS/Cloudflare Tunnel (bukan wewenang kita, itu milik Pak Ragus)

## 3. Pengguna & alur utama

- **Anak**: buka `/bikin` → masukkan slug + kode dari kartu → isi form kiri,
  lihat pratinjau kanan → pilih blok → klik "Terbitkan" → buka `/<slug>` untuk
  lihat hasil.
- **Anak lain / orang tua**: buka `karya.../<slug>` langsung, tanpa kode,
  read-only.
- **Guru/asisten**: memantau `docker logs` atau log server saat sesi
  berlangsung (di luar scope aplikasi ini, tapi aplikasi harus log error yang
  berguna ke stderr/stdout).

## 4. Data model

```
/data/karya/
  _sistem/belum-ada.html
  <slug>/index.html
  <slug>/meta.json
```

`meta.json`:

```json
{
  "nama": "Andi Prasetyo",
  "sekolah": "SMPN 21",
  "kohort": "2026-09",
  "kode_hash": "$2y$10$...",
  "dibuat": "2026-09-14T02:00:00Z",
  "terakhir_ubah": "2026-09-14T02:00:00Z"
}
```

Catatan: seeding awal 28 folder (Bagian 7) di luar scope sekarang; untuk
pengembangan/testing, folder + `meta.json` dibuat manual atau lewat
`POST /api/terbit` pertama kali (lihat §8, open question soal "siapa yang
membuat folder baru").

## 5. Kontrak routing / API

| Route | Metode | Body | Balasan |
|---|---|---|---|
| `/<slug>` | GET | — | `index.html` anak, atau `_sistem/belum-ada.html` (status 200) kalau slug belum ada |
| `/bikin` | GET | — | halaman editor (form kiri, pratinjau kanan) |
| `/api/buka` | POST | `{slug, kode}` | 200: `{fields, blocks}` tersimpan · 401: kode salah · 404: slug tak dikenal (tanpa membocorkan mana yang salah antara slug/kode) |
| `/api/terbit` | POST | `{slug, kode, fields, blocks}` | 200: `{url}` · 401/404/422/429 sesuai kasus |

Semua respons API: JSON. Semua error: pesan generik ke klien (tidak
membocorkan detail internal), detail asli ke log server.

## 6. Keamanan — checklist wajib (non-negotiable)

Diambil langsung dari dokumen sumber; ini bukan saran, ini syarat lolos
review sebelum dipakai anak-anak:

- [ ] Validasi slug regex `^[a-z0-9-]{2,20}$`, ditolak sebelum menyentuh
      filesystem. Uji: akses `/../../etc/passwd` harus ditolak, bukan
      menampilkan apa pun.
- [ ] Tidak ada input pengguna yang masuk ke HTML tanpa di-escape. Klien tidak
      pernah mengirim HTML/CSS/JS mentah — hanya teks isian dan id blok.
- [ ] Blok adalah template PHP di server, bukan string dari klien.
- [ ] Penulisan file selalu lewat `.tmp` → `rename()`, tidak pernah menimpa
      langsung.
- [ ] Kode edit disimpan sebagai bcrypt hash di `meta.json`, tidak pernah
      plaintext.
- [ ] Header pada respons halaman anak: `X-Content-Type-Options: nosniff`,
      `Referrer-Policy: no-referrer`,
      `Content-Security-Policy: default-src 'self'; script-src 'none'`.
      (Ini untuk halaman `/<slug>` yang di-render dari data anak — bukan untuk
      `/bikin`, yang boleh punya JS milik aplikasi sendiri untuk pratinjau.)
- [ ] Rate limit: 1 publish / 3 detik per slug, 60 / menit global.
- [ ] Body request maksimal 256 KB.

## 7. Kebutuhan non-fungsional

- **Beban**: sampai 28 anak aktif dalam jendela ~20 menit, kemungkinan publish
  nyaris bersamaan di menit-menit akhir. Tidak boleh ada file korup atau
  publish anak A menimpa anak B — ditangani oleh atomic rename per-slug.
- **Runtime lokal**: phpBro (nginx 1.28.3 + PHP-CGI 8.4.23) di Windows, site
  `karya.lokal:8080`, docroot = root repo ini. Model eksekusi: nginx +
  PHP-CGI klasik, request-per-proses, **tidak ada proses PHP yang
  listen sendiri di suatu port**.
- **Runtime produksi (nanti, Bagian 6)**: container Docker di belakang
  Traefik, kontrak asli menyebut "listen di port 3000" — model container
  standalone. Ini berbeda dari model lokal di atas; lihat Open Question #1.
- **Tanpa database** — seluruh state ada di filesystem (`/data/karya`, atau
  padanannya saat dev, lihat Open Question #4).

## 8. Open questions — keputusan dan status

1. **Model eksekusi PHP lokal vs kontrak "port 3000".** ✅ Diputuskan +
   diverifikasi empiris: nginx phpBro untuk `karya.lokal` sudah fallback
   path yang tidak cocok file nyata ke `index.php` (dites langsung dengan
   `curl` ke path acak — 200, bukan 404 nginx). Jadi satu front controller
   (`index.php`) di root menangani semua route (`/<slug>`, `/bikin`,
   `/api/buka`, `/api/terbit`) lewat `REQUEST_URI`, tanpa perlu ubah config
   nginx. Kontrak "listen di port 3000" (Bagian 6) tetap relevan untuk
   Docker prod nanti — bisa dipenuhi dengan `php -S 0.0.0.0:3000 index.php`
   atau nginx+php-fpm di dalam image; front controller yang sama dipakai di
   kedua model, jadi tidak ada perubahan kode saat dockerize.
2. **Rate limiting tanpa DB/Redis.** ✅ Diimplementasikan — file lock per
   slug (`_ratelimit/slug-<slug>.lock`) + counter global sliding-window
   (`_ratelimit/global.log`), keduanya pakai `flock()`. Diuji: publish kedua
   dalam <3 detik pada slug yang sama → 429.
3. **Isi 3 blok pertama.** ✅ Draf dipilih dan diuji lewat browser: "3 Hal
   yang Aku Suka" (`tiga-hal`), "Lagu Favorit" (`lagu-favorit`), "Media
   Sosial" (`sosial`, Instagram/TikTok username saja). Lihat `app/blocks.php`.
   Masih bisa diganti/ditambah — ini bukan keputusan final desain, hanya
   cukup untuk M2 berfungsi end-to-end.
4. **Lokasi folder data saat dev.** ✅ Diputuskan: default ke folder saudara
   di luar docroot, `<sibling>/workshop-spmb-data/karya` (bukan
   `<repo>/data/karya`, supaya tidak pernah web-accessible terlepas dari
   konfigurasi nginx apa pun), override via env `KARYA_DATA_DIR`.
5. **Siapa yang membuat folder `<slug>` baru.** ✅ Diputuskan: `/api/terbit`
   *dan* `/api/buka` (supaya alur editor UI konsisten) mengizinkan slug baru
   dev/testing — hanya jika kode berformat valid (6 karakter, tanpa
   `0 O 1 l I`). Ini murni kenyamanan sebelum skrip seeding Bagian 7 ada;
   **residual risk**: karena itu, `/api/terbit` ke slug yang belum ada bisa
   dibedakan dari kode salah di slug yang sudah ada (422 vs 401) — celah kecil
   yang membocorkan status "slug sudah diklaim atau belum". Diterima untuk
   fase dev; sebelum sesi nyata, matikan auto-create ini (atau pastikan semua
   slug sudah di-seed lebih dulu lewat Bagian 7) supaya perilakunya kembali
   strictly 404 untuk slug tak dikenal.

## 9. Milestone

- **M1** — routing dasar + penyimpanan file + halaman editor jalan di phpBro
  lokal, tanpa blok (fields saja). ✅ Selesai.
- **M2** — 3 blok pertama + seluruh checklist keamanan §6 lolos self-review.
  ✅ Selesai.
- **M3a (Bagian 6 — dockerize)** — ✅ `Dockerfile`, `.dockerignore`,
  `docker-compose.yml` ditulis. Dua penyimpangan sengaja dari template
  dokumen sumber:
  - `build: .` di compose, bukan image `ghcr.io/<akun>/karyaweb:latest` —
    belum ada pipeline CI yang publish ke registry, dan Dokploy bisa build
    langsung dari repo yang sudah di-push ke GitHub/Bitbucket.
  - `HEALTHCHECK` eksplisit di Dockerfile (pakai `php -r` + `file_get_contents`,
    tanpa perlu install curl/wget di image alpine) — langsung mengantisipasi
    jebakan yang disebut di Bagian 10 §3 dokumen sumber ("healthcheck gagal →
    Traefik diam-diam tidak membuat rute, tanpa pesan error").

  Diuji lokal (bukan lewat Docker — **Docker tidak terpasang di mesin dev
  ini**, jadi build image itu sendiri belum pernah dijalankan/divalidasi):
  perintah persis dari `CMD` (`php -S 0.0.0.0:<port> index.php` dengan
  `KARYA_DATA_DIR` dan `PHP_CLI_SERVER_WORKERS=4`) dijalankan langsung via PHP
  CLI lokal (PHP 8.3, bukan 8.4 — tidak ada biner 8.4 di PATH mesin ini) dan
  publish/view/path-traversal semua lolos. Dua hal yang **belum** bisa
  diverifikasi dari mesin dev ini:
  - `PHP_CLI_SERVER_WORKERS` butuh `fork()`, tidak tersedia di Windows ("forking
    is not supported on this platform" muncul di log lokal) — jadi konkurensi
    multi-worker baru benar-benar teruji begitu container Linux-nya jalan.
  - Build image itu sendiri (`docker build`) belum pernah dicoba sama sekali.

  **Belum dikerjakan**: apply/deploy nyata ke Dokploy (`dokploy.labpplg.web.id`)
  — sesi ini tidak punya akses live ke server lab (tidak ada kredensial
  Dokploy, tunnel SSH `ssh.labpplg.web.id` via cloudflared belum dicoba/
  diverifikasi). Langkah apply di panel Dokploy dilakukan manual oleh
  pengguna.
- **M3b (fase lanjut, di luar PR ini)** — skrip seed CSV (Bagian 7), blok
  tambahan (Bagian 8).

## 10. Referensi

- `docs/panduan-infra-pelatihan-web-smp-versi-siswa.md` — Bagian 2 (arsitektur),
  Bagian 3 (tiga larangan keras), Bagian 5–8 (spek detail), tidak di-commit
  (lihat `.gitignore`).
