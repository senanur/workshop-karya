<?php

declare(strict_types=1);

if (!defined('KARYA_APP')) {
    http_response_code(404);
    exit;
}

// Halaman unggah anak (PRD-jalur-scratch §2/§4, TASK M-S3 §4.2). Ini UI
// aplikasi (karya_send_app_page_headers), jadi boleh pakai skrip inline —
// memuat bundel pemutar (Pemutar) untuk pratinjau LOKAL dari File/ArrayBuffer
// sebelum server selesai memvalidasi, lalu mengunggah lewat /api/unggah dan
// menerbitkan lewat /api/terbit-sb3. Panel hasil (alamat, QR, Bagikan) memakai
// komponen yang sama persis dengan editor jalur web (lihat editor_view.php).
//
// Template nowdoc + str_replace(__TOKEN__) supaya `$` di JS tidak tersangkut
// interpolasi PHP.

function karya_render_unggah_page(string $slug): string
{
    $tpl = <<<'HTML'
<!doctype html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Unggah game — __SLUG_HTML__</title>
<style>
:root{
  --cherry:#A61B2B; --navy:#2F3C7E; --ink:#1E2440; --text:#232326; --muted:#5E5E66;
  --paper:#FFFFFF; --mist:#ECEEF5; --line:#D8DDEA; --codebg:#F7F7FA; --warn:#FFF3C4; --warntext:#5A4300;
  color-scheme:light;
}
*{box-sizing:border-box}
html,body{height:100%}
body{margin:0;background:var(--paper);color:var(--text);font-family:"Segoe UI",Roboto,Arial,sans-serif;font-size:15px;display:flex;flex-direction:column}
.bar{display:flex;align-items:center;gap:8px;padding:8px 12px;background:var(--ink);color:#fff;flex-wrap:wrap}
.bar .logo{font-weight:700;letter-spacing:.02em;margin-right:4px}
.bar .url{flex:1;min-width:160px;background:rgba(255,255,255,.12);border-radius:6px;padding:4px 12px;font-family:Consolas,monospace;font-size:.9rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#fff;text-decoration:none}
a.url:hover{background:rgba(255,255,255,.2)}
button,label.btn{font:inherit;border:1px solid rgba(255,255,255,.35);background:transparent;color:#fff;border-radius:6px;padding:5px 12px;cursor:pointer}
button:hover,label.btn:hover{background:rgba(255,255,255,.1)}
button:disabled{opacity:.5;cursor:default}
button.pub{background:var(--cherry);border-color:var(--cherry);font-weight:700}
button:focus-visible,label.btn:focus-visible,input:focus-visible{outline:3px solid #F6C9CF;outline-offset:1px}
main{flex:1;min-height:0;overflow:auto;display:flex;flex-direction:column;align-items:center;gap:14px;padding:18px 16px 40px}
.keterangan{max-width:520px;text-align:center;color:var(--muted);font-size:.95rem}
.keterangan b{color:var(--text)}
.stage-wrap{position:relative;width:480px;max-width:100%;aspect-ratio:4/3;background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.12);overflow:hidden}
.stage-wrap canvas{display:block;width:100%;height:100%}
.kontrol{display:flex;gap:8px;flex-wrap:wrap;justify-content:center}
.kontrol button{background:var(--paper);color:var(--ink);border:1px solid var(--line);border-radius:999px;padding:.45rem 1rem;font-size:.95rem}
.kontrol button:hover{background:var(--mist)}
.kontrol button.hijau{background:#4CAF50;border-color:#4CAF50;color:#fff;font-weight:700}
.kontrol button.berhenti{background:var(--cherry);border-color:var(--cherry);color:#fff}
.pilih{display:flex;gap:8px;align-items:center;flex-wrap:wrap;justify-content:center}
.pilih label.btn{background:var(--navy);border-color:var(--navy);color:#fff;font-weight:600}
.pilih .drop{border:2px dashed var(--line);border-radius:12px;padding:14px 22px;color:var(--muted);font-size:.9rem;text-align:center;cursor:pointer;max-width:100%}
.pilih .drop.drag{border-color:var(--cherry);background:var(--mist)}
.status{min-height:1.2em;color:var(--cherry);font-size:.9rem;text-align:center;max-width:520px}
.status[hidden]{display:none}
.status.ok{color:#2E7D32}
.gate{position:fixed;inset:0;background:rgba(30,36,64,.55);display:flex;align-items:center;justify-content:center;padding:1.25rem;z-index:30}
.gate[hidden]{display:none}
.gate-card{background:var(--paper);color:var(--text);border-radius:16px;padding:1.75rem;max-width:340px;width:100%;box-shadow:0 8px 32px rgba(0,0,0,.25)}
.gate-card h1{font-size:1.15rem;margin:0 0 .3rem}
.gate-card .gate-slug{color:var(--muted);font-size:.85rem;margin:0 0 1rem;font-family:Consolas,monospace}
.gate-card label{display:block;font-size:.85rem;font-weight:600;margin:.75rem 0 .3rem}
.gate-card input{width:100%;padding:.6rem .75rem;border:1px solid var(--line);border-radius:8px;font-size:1.1rem;letter-spacing:.1em;text-align:center}
.gate-card button{margin-top:1.1rem;width:100%;padding:.65rem;border:none;border-radius:999px;background:var(--cherry);color:#fff;font-size:1rem;font-weight:700}
.gate-err{color:var(--cherry);font-size:.85rem;margin:.75rem 0 0}
.gate-err[hidden]{display:none}
.hasil{position:fixed;inset:0;background:rgba(30,36,64,.55);display:flex;align-items:center;justify-content:center;padding:1.25rem;z-index:40;overflow:auto}
.hasil[hidden]{display:none}
.hasil-card{background:var(--paper);color:var(--text);border-radius:16px;padding:1.75rem;max-width:400px;width:100%;box-shadow:0 8px 32px rgba(0,0,0,.25);text-align:center}
.hasil-card h1{font-size:1.2rem;margin:0 0 .5rem;color:var(--cherry)}
.hasil-url{font-family:Consolas,monospace;font-size:.95rem;margin:0 0 1rem;word-break:break-all}
.qr{display:flex;justify-content:center;margin:0 0 .6rem}
.qr svg{width:180px;height:180px;background:#fff;border-radius:8px;padding:8px}
.hasil-hint{font-size:.8rem;color:var(--muted);margin:0 0 1rem}
.hasil-tombol{display:flex;gap:8px;justify-content:center;flex-wrap:wrap}
.hasil-tombol button,.hasil-tombol .tombol-buka{border:1px solid var(--line);background:transparent;color:var(--text);border-radius:999px;padding:.5rem 1rem;font:inherit;cursor:pointer;text-decoration:none}
.hasil-tombol button:hover,.hasil-tombol .tombol-buka:hover{background:var(--mist)}
.hasil-tutup{margin-top:1.2rem;width:100%;padding:.6rem;border:none;border-radius:999px;background:var(--cherry);color:#fff;font-size:1rem;font-weight:700;cursor:pointer}
.toast{position:fixed;left:50%;bottom:18px;transform:translateX(-50%);background:var(--ink);color:#fff;padding:10px 16px;border-radius:8px;font-size:.95rem;box-shadow:0 6px 24px rgba(0,0,0,.25);max-width:90vw;z-index:20}
.toast[hidden]{display:none}
@media (max-width:520px){.stage-wrap{width:100%}}
</style>
</head>
<body>
<div class="bar">
  <span class="logo">labpplg</span>
  <span class="url" id="url">__DOMAIN_HTML__/__SLUG_HTML__/unggah</span>
  <a class="url" id="urlLink" href="/__SLUG_HTML__" target="_blank" rel="noopener" hidden>Lihat halamanmu ↗</a>
  <button class="pub" id="btnTerbit" disabled>Terbitkan</button>
</div>
<main>
  <p class="keterangan">Pilih berkas <b>.sb3</b>-mu dari Scratch. Game langsung diputar sebagai pratinjau di sini sebelum diterbitkan.</p>
  <div class="stage-wrap" id="panggungWrap"><canvas id="panggung" width="480" height="360"></canvas></div>
  <div class="kontrol">
    <button class="hijau" id="hijau" type="button">▶ Bendera hijau</button>
    <button class="berhenti" id="berhenti" type="button">■ Berhenti</button>
    <button id="layar-penuh" type="button">⛶ Layar penuh</button>
  </div>
  <div class="pilih">
    <label class="drop" id="drop" for="berkas">Seret berkas .sb3 ke sini, atau <b>pilih berkas</b><input type="file" id="berkas" accept=".sb3" hidden></label>
  </div>
  <p class="status" id="status" hidden></p>
</main>

<div class="gate" id="gate">
  <div class="gate-card">
    <h1>Masuk ke halamanmu</h1>
    <p class="gate-slug">__DOMAIN_HTML__/<b id="gateSlug">__SLUG_HTML__</b></p>
    <label for="gateKode">Kode edit (dari kartu)</label>
    <input id="gateKode" maxlength="6" autocomplete="off" autocapitalize="characters" spellcheck="false">
    <button id="gateMasuk">Buka</button>
    <p class="gate-err" id="gateErr" hidden></p>
  </div>
</div>

<div class="hasil" id="hasil" hidden>
  <div class="hasil-card">
    <h1>Gamemu sudah terbit!</h1>
    <p class="hasil-url" id="hasilUrl"></p>
    <div class="qr" id="qr"></div>
    <p class="hasil-hint">Foto QR ini pakai kamera HP, atau bagikan tautannya.</p>
    <div class="hasil-tombol">
      <button id="btnBagikan">Bagikan</button>
      <button id="btnSalin">Salin tautan</button>
      <a class="tombol-buka" id="btnBuka" href="/__SLUG_HTML__" target="_blank" rel="noopener">Buka halaman ↗</a>
    </div>
    <button class="hasil-tutup" id="hasilTutup">Lanjut</button>
  </div>
</div>

<div class="toast" id="toast" hidden></div>

<script src="/aset/qrcode.js"></script>
<script src="/aset/scratch/pemutar.js"></script>
<script>
(function(){
  var SLUG=__SLUG_JSON__, DOMAIN=__DOMAIN_JSON__;
  var $=function(id){return document.getElementById(id)};
  var KODE=null, unggahOK=false;

  var tt=null;
  function toast(m){ var t=$('toast'); t.textContent=m; t.hidden=false; clearTimeout(tt); tt=setTimeout(function(){t.hidden=true},4200); }

  function status(m,ok){
    var s=$('status');
    s.textContent=m; s.hidden=!m;
    s.classList.toggle('ok',!!ok);
  }

  /* ---- pintu masuk (kode dari kartu), pola /masuk + editor ---- */
  function tampilkanGate(pesan){
    $('gate').hidden=false;
    if(pesan){ $('gateErr').textContent=pesan; $('gateErr').hidden=false; } else { $('gateErr').hidden=true; }
    $('gateKode').focus();
  }
  function sembunyikanGate(){ $('gate').hidden=true; }

  function bukaDenganKode(kode){
    kode=(kode||'').trim();
    if(!kode){ tampilkanGate('Ketik kode edit dulu.'); return; }
    $('gateMasuk').disabled=true;
    fetch('/api/buka',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({slug:SLUG,kode:kode})})
      .then(function(r){ return r.json().then(function(d){ return {status:r.status,data:d}; }); })
      .then(function(res){
        $('gateMasuk').disabled=false;
        if(res.status!==200){ tampilkanGate((res.data&&res.data.error)||'Kode salah atau slug tidak dikenal.'); return; }
        KODE=kode;
        try{ sessionStorage.setItem('karya-kode-'+SLUG, kode); }catch(e){}
        sembunyikanGate();
        status('Masuk berhasil. Pilih berkas .sb3-mu.', true);
      })
      .catch(function(){ $('gateMasuk').disabled=false; tampilkanGate('Tidak bisa menghubungi server. Coba lagi.'); });
  }
  $('gateMasuk').onclick=function(){ bukaDenganKode($('gateKode').value); };
  $('gateKode').addEventListener('keydown',function(e){ if(e.key==='Enter') bukaDenganKode($('gateKode').value); });

  /* ---- pratinjau lokal: langsung dari File/ArrayBuffer, tanpa server ---- */
  function pratinjauLokal(arrayBuffer){
    if(typeof Pemutar==='undefined'){ status('Pemutar belum dimuat. Muat ulang halaman.'); return; }
    Pemutar.mulai($('panggung'), arrayBuffer)
      .then(function(){ Pemutar.bendera_hijau(); })
      .catch(function(){ status('Berkasnya terbaca tapi tidak bisa diputar di sini. Coba berkas lain.'); });
  }

  /* ---- unggah ke server; ini yang menentukan boleh-tidaknya Terbitkan ---- */
  function unggahKeServer(file){
    unggahOK=false; setTerbit(false);
    status('Mengunggah…');
    var fd=new FormData();
    fd.append('slug',SLUG); fd.append('kode',KODE||'');
    fd.append('berkas',file,file.name);
    fetch('/api/unggah',{method:'POST',body:fd})
      .then(function(r){ return r.json().then(function(d){ return {status:r.status,data:d}; }); })
      .then(function(res){
        if(res.status===401){ KODE=null; try{sessionStorage.removeItem('karya-kode-'+SLUG);}catch(e){} tampilkanGate('Kode sudah tidak berlaku. Masuk lagi.'); return; }
        if(res.status!==200){
          unggahOK=false; setTerbit(false);
          status((res.data&&res.data.error)||'Unggahannya ditolak server. Coba lagi.');
          return;
        }
        unggahOK=true; setTerbit(true);
        status('Unggahan diterima. Tekan Terbitkan kalau sudah cocok.', true);
      })
      .catch(function(){ unggahOK=false; setTerbit(false); status('Tidak bisa menghubungi server. Coba lagi.'); });
  }

  function setTerbit(aktif){
    $('btnTerbit').disabled=!aktif;
  }

  function prosesBerkas(file){
    if(!file) return;
    if(!KODE){ tampilkanGate('Masuk dulu sebelum mengunggah.'); return; }
    if(!/\.sb3$/i.test(file.name)){
      status('Pilih berkas .sb3 (hasil "Save to your computer" dari Scratch).');
      return;
    }
    status('Memutar pratinjau…', true);
    file.arrayBuffer().then(pratinjauLokal).catch(function(){ status('Berkasnya tidak bisa dibaca di peramban ini.'); });
    unggahKeServer(file);
  }

  $('berkas').addEventListener('change',function(){ var f=this.files[0]; this.value=''; prosesBerkas(f); });

  var drop=$('drop');
  drop.addEventListener('dragover',function(e){ e.preventDefault(); drop.classList.add('drag'); });
  drop.addEventListener('dragleave',function(){ drop.classList.remove('drag'); });
  drop.addEventListener('drop',function(e){
    e.preventDefault(); drop.classList.remove('drag');
    var f=e.dataTransfer&&e.dataTransfer.files&&e.dataTransfer.files[0];
    if(f) prosesBerkas(f);
  });

  /* ---- kontrol pratinjau ---- */
  $('hijau').onclick=function(){ if(Pemutar) Pemutar.bendera_hijau(); };
  $('berhenti').onclick=function(){ if(Pemutar) Pemutar.berhenti(); };
  $('layar-penuh').onclick=function(){ if(Pemutar) Pemutar.layar_penuh(); };

  /* ---- panel hasil terbit: alamat, QR, bagikan (identik dengan jalur web) ---- */
  function gambarQr(teks){
    var kotak=$('qr');
    kotak.replaceChildren();
    var qr;
    try{ qr=qrcode(0,'M'); qr.addData(teks); qr.make(); }
    catch(e){ return; }
    var n=qr.getModuleCount(), pad=2, ukuran=n+pad*2, NS='http://www.w3.org/2000/svg';
    var svg=document.createElementNS(NS,'svg');
    svg.setAttribute('viewBox','0 0 '+ukuran+' '+ukuran);
    svg.setAttribute('role','img');
    svg.setAttribute('aria-label','Kode QR ke halamanmu');
    var latar=document.createElementNS(NS,'rect');
    latar.setAttribute('width',ukuran); latar.setAttribute('height',ukuran); latar.setAttribute('fill','#fff');
    svg.appendChild(latar);
    for(var r=0;r<n;r++){
      for(var c=0;c<n;c++){
        if(!qr.isDark(r,c)) continue;
        var sel=document.createElementNS(NS,'rect');
        sel.setAttribute('x',c+pad); sel.setAttribute('y',r+pad);
        sel.setAttribute('width',1); sel.setAttribute('height',1); sel.setAttribute('fill','#000');
        svg.appendChild(sel);
      }
    }
    kotak.appendChild(svg);
  }

  var ALAMAT='';
  function tampilkanHasil(url){
    ALAMAT=location.protocol+'//'+location.host+url;
    $('hasilUrl').textContent=DOMAIN+url;
    $('btnBuka').href=url;
    gambarQr(ALAMAT);
    $('hasil').hidden=false;
  }
  $('hasilTutup').onclick=function(){ $('hasil').hidden=true; };

  $('btnBagikan').onclick=function(){
    if(navigator.share){
      navigator.share({title:'Gamemu',url:ALAMAT}).catch(function(){});
    } else {
      salinAlamat();
    }
  };
  $('btnSalin').onclick=salinAlamat;
  function salinAlamat(){
    var selesai=function(){ toast('Tautan tersalin. Tinggal di-paste.'); };
    if(navigator.clipboard&&navigator.clipboard.writeText){
      navigator.clipboard.writeText(ALAMAT).then(selesai,function(){ toast('Salin manual: '+ALAMAT); });
    } else {
      toast('Salin manual: '+ALAMAT);
    }
  }

  /* ---- terbitkan ---- */
  $('btnTerbit').onclick=function(){
    if(!unggahOK){ toast('Unggahan belum diterima server. Tunggu sebentar atau pilih ulang berkasmu.'); return; }
    if(!KODE){ tampilkanGate('Masuk dulu sebelum menerbitkan.'); return; }
    $('btnTerbit').disabled=true;
    fetch('/api/terbit-sb3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({slug:SLUG,kode:KODE})})
      .then(function(r){ return r.json().then(function(d){ return {status:r.status,data:d}; }); })
      .then(function(res){
        $('btnTerbit').disabled=!unggahOK;
        if(res.status===401){ KODE=null; try{sessionStorage.removeItem('karya-kode-'+SLUG);}catch(e){} tampilkanGate('Kode sudah tidak berlaku. Masuk lagi.'); return; }
        if(res.status!==200){ status((res.data&&res.data.error)||'Gagal menerbitkan, coba lagi.'); return; }
        var link=$('urlLink'); link.href=res.data.url; link.hidden=false;
        status('Game sudah terbit!', true);
        tampilkanHasil(res.data.url);
      })
      .catch(function(){ $('btnTerbit').disabled=!unggahOK; status('Tidak bisa menghubungi server. Coba lagi.'); });
  };

  /* ---- mulai: coba kode tersimpan, kalau tidak ada tampilkan gate ---- */
  (function(){
    var simpan=null;
    try{ simpan=sessionStorage.getItem('karya-kode-'+SLUG); }catch(e){}
    if(simpan){ bukaDenganKode(simpan); } else { tampilkanGate(); }
  })();
})();
</script>
</body>
</html>
HTML;

    $slugHtml = htmlspecialchars($slug, ENT_QUOTES, 'UTF-8');
    $domainHtml = htmlspecialchars(KARYA_DOMAIN_LABEL, ENT_QUOTES, 'UTF-8');

    return str_replace(
        ['__SLUG_HTML__', '__DOMAIN_HTML__', '__SLUG_JSON__', '__DOMAIN_JSON__'],
        [
            $slugHtml,
            $domainHtml,
            json_encode($slug, JSON_UNESCAPED_UNICODE),
            json_encode(KARYA_DOMAIN_LABEL, JSON_UNESCAPED_UNICODE),
        ],
        $tpl
    );
}