/* ============================================================
   blok.js — skrip untuk blok katalog yang butuh interaksi.
   Dimuat di halaman karya. Peserta tidak menulis JavaScript;
   mereka hanya mengubah atribut data-… pada bloknya.
   ============================================================ */
(function () {
  // E · Tombol rahasia: klik → tampilkan data-pesan di bawah tombol
  document.querySelectorAll('.rahasia').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var lama = btn.nextElementSibling;
      if (lama && lama.classList.contains('rahasia-pesan')) { lama.remove(); return; }
      var p = document.createElement('div');
      p.className = 'rahasia-pesan';
      p.textContent = btn.getAttribute('data-pesan') || '(isi data-pesan dulu)';
      btn.insertAdjacentElement('afterend', p);
    });
  });

  // F · Hitung mundur: data-tanggal (YYYY-MM-DD) → selisih hari dari hari ini
  document.querySelectorAll('.mundur').forEach(function (el) {
    var t = el.getAttribute('data-tanggal') || '';
    var label = el.getAttribute('data-label') || 'hari lagi';
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(t.trim());
    var b = document.createElement('b');
    var s = document.createElement('span');
    if (!m) {
      b.textContent = '?';
      s.textContent = 'tanggal harus ditulis tahun-bulan-tanggal, contoh 2027-06-12';
    } else {
      var target = new Date(+m[1], +m[2] - 1, +m[3]);
      var kini = new Date(); kini.setHours(0, 0, 0, 0);
      var hari = Math.round((target - kini) / 86400000);
      b.textContent = Math.abs(hari);
      s.textContent = hari < 0 ? 'hari yang lalu · ' + label : label;
    }
    el.replaceChildren(b, s);
  });

  // G · Hujan emoji: data-emoji, data-jumlah (maks 60)
  document.querySelectorAll('.hujan').forEach(function (el) {
    var emoji = el.getAttribute('data-emoji') || '⭐';
    var n = Math.min(60, Math.max(0, parseInt(el.getAttribute('data-jumlah') || '25', 10) || 0));
    el.replaceChildren();
    for (var k = 0; k < n; k++) {
      var i = document.createElement('i');
      i.textContent = emoji;
      i.style.left = (Math.random() * 100) + '%';
      i.style.fontSize = (14 + Math.random() * 18) + 'px';
      i.style.animationDuration = (6 + Math.random() * 8) + 's';
      i.style.animationDelay = (-Math.random() * 14) + 's';
      i.style.opacity = (0.35 + Math.random() * 0.5).toFixed(2);
      el.appendChild(i);
    }
  });
})();
