// Halaman pemutar — perekat antara halaman statis dan bundel engine
// (Pemutar global dari /aset/scratch/pemutar.js). Hidup sebagai berkas
// tersendiri karena CSP halaman pemutar menolak skrip inline (script-src
// 'self'). Berjalan tanpa eval sama sekali — kalau ada yang perlu eval,
// kita harus tahu, bukan diam-diam.

(function () {
    'use strict';

    var script = document.currentScript;
    var slug = (script && script.getAttribute('data-slug')) || '';
    var kanvas = document.getElementById('panggung');
    var pesan = document.getElementById('pesan');

    function tampilGagal(txt) {
        pesan.textContent = txt;
        pesan.hidden = false;
    }

    function sembunyiGagal() {
        pesan.hidden = true;
    }

    function muatProyek() {
        sembunyiGagal();
        if (typeof Pemutar === 'undefined') {
            tampilGagal('Pemutar belum dimuat. Coba muat ulang halaman.');
            return;
        }
        fetch('/' + encodeURIComponent(slug) + '/karya.sb3')
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('Berkas gamenya belum bisa diambil.');
                }
                return r.arrayBuffer();
            })
            .then(function (buf) {
                return Pemutar.mulai(kanvas, buf);
            })
            .catch(function (err) {
                tampilGagal('Gamenya gagal dimuat: ' + (err && err.message ? err.message : 'terjadi kesalahan') + '.');
            });
    }

    document.getElementById('hijau').addEventListener('click', function () {
        sembunyiGagal();
        if (Pemutar) Pemutar.bendera_hijau();
    });
    document.getElementById('berhenti').addEventListener('click', function () {
        if (Pemutar) Pemutar.berhenti();
    });
    document.getElementById('layar-penuh').addEventListener('click', function () {
        if (Pemutar) Pemutar.layar_penuh();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', muatProyek);
    } else {
        muatProyek();
    }
})();