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

    // Tombol sentuh (PRD-jalur-scratch §6): panah+spasi tetap, ditampilkan
    // lewat CSS hanya pada layar sentuh. Lewat window.Pemutar.tombol(), jalur
    // yang sama persis dengan papan ketik sungguhan (vm.postIOData()) — bukan
    // KeyboardEvent tiruan.
    var tombolSentuh = document.querySelectorAll('[data-tombol]');
    for (var i = 0; i < tombolSentuh.length; i++) {
        (function (el) {
            var key = el.getAttribute('data-tombol');
            el.addEventListener('pointerdown', function (e) { e.preventDefault(); if (Pemutar) Pemutar.tombol(key, true); });
            var lepas = function (e) { if (e) e.preventDefault(); if (Pemutar) Pemutar.tombol(key, false); };
            el.addEventListener('pointerup', lepas);
            el.addEventListener('pointercancel', lepas);
            el.addEventListener('pointerleave', lepas);
        })(tombolSentuh[i]);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', muatProyek);
    } else {
        muatProyek();
    }
})();