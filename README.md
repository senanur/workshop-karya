# KaryaWeb

A no-database, no-account PHP app that lets ~28 SMP students each get a
public page at `karya.labpplg.web.id/<slug>`, published from a training
session. Two tracks:

- **Jalur A (web)** — kids edit real HTML/CSS in a browser-based code
  editor (two files: `Isi` and `Gaya`), inside guardrails: server-side
  sanitization, a strict CSP, and a page that's already built and named
  before they arrive.
- **Jalur B (Scratch)** — kids upload a `.sb3` game they built in Scratch
  and get a public playable page instead of a raw file on a lab computer.

State lives on the filesystem under `KARYA_DATA_DIR` (atomic writes, no
sessions — the edit code is sent with every API request). See
`docs/PRD-karyaweb-v2.md` §2/§5 for why, and what's kept from v1.

## Where to look

| Doc | Read this for |
|---|---|
| [`docs/PRD-karyaweb-v2.md`](docs/PRD-karyaweb-v2.md) | The current spec for Jalur A: routes, data model, API contract, security checklist, milestone status. Start here. |
| [`docs/PRD-jalur-scratch.md`](docs/PRD-jalur-scratch.md) | Companion spec for Jalur B (`.sb3` upload/player), still in progress. |
| [`docs/TASK-M-S2-M-S3-jalur-scratch.md`](docs/TASK-M-S2-M-S3-jalur-scratch.md) | Design notes for Jalur B's player page and upload UI (now implemented — see Status below for what's still unverified). |
| [`docs/PRD-karyaweb.md`](docs/PRD-karyaweb.md) | v1, superseded by v2 but kept as a record of what M1–M3a actually built. v2 wins on any conflict. |
| [`docs/panduan-infra-pelatihan-web-smp-versi-siswa.md`](docs/panduan-infra-pelatihan-web-smp-versi-siswa.md) | Infra + facilitator guide: Dokploy/Traefik, deploy mechanics, the session rundown. |
| [`docs/PHP-404-extended-path-bug.md`](docs/PHP-404-extended-path-bug.md) | One specific local-dev gotcha (phpBro + Windows extended-length paths). |
| [`docs/halaman-contoh-web/`](docs/halaman-contoh-web/) | The example child page (`isi.html`, `gaya.css`, `blok.js`) the editor and templates are built from — a starting point, not just a sample. |
| [`AGENT.md`](AGENT.md) | Notes for anyone (human or agent) working in this repo — currently just which git remote is authoritative. |

## Status

- **Jalur A**: M4 (editor, sanitizer, router) verified live in production.
  M5 (photos, versions, publish panel) and M6 (seeding, dinding karya,
  hide switch) are done in code but not yet deployed or verified live —
  see the status header of `docs/PRD-karyaweb-v2.md` for exactly what's
  left.
- **Jalur B**: M-S1 (`.sb3` validation), M-S2 (player page, Scratch VM
  bundle), and M-S3 (upload UI, publish panel) are all done and verified —
  including in real production now, not just locally: a real game plays end
  to end on PC and phone, and the full upload → preview → publish → QR flow
  works on `karya.labpplg.web.id`. Getting there surfaced two real production
  bugs invisible to local testing (`ZipArchive` missing at runtime in the
  Docker image; `/api/unggah` and `/api/terbit-sb3` sharing one rate-limit
  lock) — both fixed, see `docs/PRD-jalur-scratch.md` §10. M-S4 (lab-network
  test, dry run with PPLG students) is what's left — needs hands-on access
  this session doesn't have.

## Running locally

```
php -S 0.0.0.0:3000 index.php
```

Set `KARYA_DATA_DIR` to override where state is stored (default is a
sibling folder outside the docroot). Production runs this same command
inside the container built from `Dockerfile`, behind Traefik via Dokploy.

## Git remote

Push to `github`, not `origin` — see `AGENT.md`.
