# PRD — KaryaWeb (Bagian 5: Aplikasi Halaman Anak)

Status: draft, belum diimplementasikan. Referensi utama:
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

## 8. Open questions — perlu diputuskan sebelum/​saat mulai coding

1. **Model eksekusi PHP lokal vs kontrak "port 3000".** Site phpBro
   `karya.lokal` saat ini — apakah nginx-nya sudah (atau bisa) diatur supaya
   semua path (`/andi`, `/bikin`, `/api/terbit`, dst.) di-*rewrite* ke satu
   front controller (`index.php`)? Atau tiap route harus jadi file `.php`
   fisik (`/bikin/index.php`, `/api/terbit.php`, dst.) karena kita tidak
   mengatur konfigurasi nginx phpBro secara langsung? Ini menentukan struktur
   folder aplikasi dari awal.
2. **Rate limiting tanpa DB/Redis.** Perlu file-based (lock file + timestamp
   per slug, counter global dengan `flock`) supaya aman dari race condition
   antar proses PHP-CGI yang terpisah-pisah (tidak ada shared memory antar
   request seperti di model Node long-running).
3. **Isi 3 blok pertama.** Kandidat dari dokumen sumber: mode gelap, latar
   gradasi, efek hover, kartu lagu favorit, daftar "3 hal yang aku suka",
   tombol sosial media. Perlu pilih 3, atau saya usulkan default dan
   tunjukkan untuk direview.
4. **Lokasi folder data saat dev.** `/data/karya` di dokumen sumber adalah
   path absolut Linux untuk server produksi. Untuk dev Windows lewat phpBro,
   usul: env var (`KARYA_DATA_DIR`) dengan default relatif
   `<repo>/data/karya`, supaya kode yang sama jalan di kedua tempat.
5. **Siapa yang membuat folder `<slug>` baru.** Alur asli (Bagian 7)
   mengasumsikan 28 folder sudah disiapkan sebelum sesi lewat skrip CSV. Untuk
   dev/testing sebelum skrip itu ada — apakah `/api/terbit` boleh membuat
   slug baru kalau belum ada (mis. untuk data uji), atau strictly 404 kalau
   slug belum pernah di-seed (mengikuti model produksi apa adanya)?

## 9. Milestone

- **M1** — routing dasar + penyimpanan file + halaman editor jalan di phpBro
  lokal, tanpa blok (fields saja).
- **M2** — 3 blok pertama + seluruh checklist keamanan §6 lolos self-review.
- **M3 (fase lanjut, di luar PR ini)** — dockerize (Bagian 6), skrip seed CSV
  (Bagian 7), blok tambahan (Bagian 8).

## 10. Referensi

- `docs/panduan-infra-pelatihan-web-smp-versi-siswa.md` — Bagian 2 (arsitektur),
  Bagian 3 (tiga larangan keras), Bagian 5–8 (spek detail), tidak di-commit
  (lihat `.gitignore`).
