<?php

declare(strict_types=1);

if (!defined('KARYA_APP')) {
    http_response_code(404);
    exit;
}

// Both pages here are the app's own UI (not child-submitted content): they
// carry their own inline CSS/JS under karya_send_app_page_headers(). Neither
// page ever interpolates a value a child typed into markup — the editor
// populates its textareas via .value assignment from a fetch() response,
// never via innerHTML, so nothing a child types is ever parsed as HTML even
// in their own browser.
//
// Templates below are nowdocs (<<<'HTML') so the JavaScript's own `$` (the
// $('id') alias, regex `$` anchors) is never mistaken for PHP interpolation.
// The handful of real dynamic values are substituted afterwards with
// str_replace() against __TOKEN__ placeholders.

function karya_render_masuk_page(): string
{
    $tpl = <<<'HTML'
<!doctype html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk — __DOMAIN_HTML__</title>
<style>
  :root{color-scheme:light}
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
    font-family:"Segoe UI",Roboto,Arial,sans-serif;background:#ECEEF5;color:#232326;padding:1.5rem}
  .card{background:#fff;border-radius:16px;padding:2rem;max-width:360px;width:100%;
    box-shadow:0 4px 20px rgba(0,0,0,.08)}
  h1{font-size:1.25rem;margin:0 0 1.25rem}
  label{display:block;font-size:.85rem;font-weight:600;margin:.9rem 0 .3rem}
  input{width:100%;padding:.6rem .75rem;border:1px solid #ccc;border-radius:8px;font-size:1rem}
  button{margin-top:1.4rem;width:100%;padding:.7rem;border:none;border-radius:999px;
    background:#A61B2B;color:#fff;font-size:1rem;font-weight:700;cursor:pointer}
  .err{color:#A61B2B;font-size:.85rem;margin-top:.75rem}
  .err[hidden]{display:none}
</style>
</head>
<body>
<div class="card">
  <h1>Masuk ke halamanmu</h1>
  <form id="f">
    <label for="slug">Nama halaman</label>
    <input id="slug" autocomplete="off" autocapitalize="none" spellcheck="false" required>
    <label for="kode">Kode edit (dari kartu)</label>
    <input id="kode" maxlength="6" autocomplete="off" autocapitalize="characters" spellcheck="false" required>
    <button type="submit">Buka</button>
    <p class="err" id="err" hidden></p>
  </form>
</div>
<script>
(function(){
  var f=document.getElementById('f'), slug=document.getElementById('slug'),
      kode=document.getElementById('kode'), err=document.getElementById('err');
  f.addEventListener('submit', function(e){
    e.preventDefault();
    var s=slug.value.trim().toLowerCase(), k=kode.value.trim();
    if(!/^[a-z0-9-]{2,20}$/.test(s)){ err.textContent='Nama halaman tidak dikenal.'; err.hidden=false; return; }
    if(!k){ err.textContent='Ketik kode edit dari kartumu.'; err.hidden=false; return; }
    try{ sessionStorage.setItem('karya-kode-'+s, k); }catch(ex){}
    location.href='/'+s+'/edit';
  });
})();
</script>
</body>
</html>
HTML;

    return str_replace('__DOMAIN_HTML__', htmlspecialchars(KARYA_DOMAIN_LABEL, ENT_QUOTES, 'UTF-8'), $tpl);
}

function karya_render_editor_page(string $slug): string
{
    $tpl = <<<'HTML'
<!doctype html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Editor — __SLUG_HTML__</title>
<style>
:root{
  --cherry:#A61B2B; --navy:#2F3C7E; --ink:#1E2440; --text:#232326; --muted:#5E5E66;
  --paper:#FFFFFF; --mist:#ECEEF5; --line:#D8DDEA; --codebg:#F7F7FA; --warn:#FFF3C4; --warntext:#5A4300;
  color-scheme:light;
}
@media (prefers-color-scheme: dark){:root:not([data-theme="light"]){
  --cherry:#E0707C; --navy:#9DA9E8; --ink:#E6E8F2; --text:#DCDCE2; --muted:#A3A3AD;
  --paper:#15171F; --mist:#1E2233; --line:#2F3550; --codebg:#1A1D28; --warn:#4A3E12; --warntext:#F5E3A0; color-scheme:dark}}
*{box-sizing:border-box}
html,body{height:100%}
body{margin:0;background:var(--paper);color:var(--text);font-family:"Segoe UI",Roboto,Arial,sans-serif;font-size:15px;display:flex;flex-direction:column}
.bar{display:flex;align-items:center;gap:8px;padding:8px 12px;background:var(--ink);color:#fff;flex-wrap:wrap}
.bar .logo{font-weight:700;letter-spacing:.02em;margin-right:4px}
.bar .url{flex:1;min-width:160px;background:rgba(255,255,255,.12);border-radius:6px;padding:4px 12px;font-family:Consolas,monospace;font-size:.9rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#fff;text-decoration:none}
a.url:hover{background:rgba(255,255,255,.2)}
button,select,label.btn{font:inherit;border:1px solid rgba(255,255,255,.35);background:transparent;color:#fff;border-radius:6px;padding:5px 12px;cursor:pointer}
button:hover,label.btn:hover{background:rgba(255,255,255,.1)}
button:disabled{opacity:.5;cursor:default}
button.pub{background:var(--cherry);border-color:var(--cherry);font-weight:700}
button:focus-visible,select:focus-visible,textarea:focus-visible,input:focus-visible{outline:3px solid #F6C9CF;outline-offset:1px}
select{background:var(--ink);color:#fff}
select option{color:#000;background:#fff}
.pane{flex:1;min-height:0;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr)}
.kode{display:flex;flex-direction:column;border-right:1px solid var(--line);min-height:0}
.tabs{display:flex;gap:2px;padding:6px 8px 0;background:var(--mist);border-bottom:1px solid var(--line)}
.tabs button{color:var(--muted);border:1px solid transparent;border-bottom:0;border-radius:6px 6px 0 0;padding:5px 14px}
.tabs button[aria-selected="true"]{background:var(--codebg);color:var(--ink);font-weight:700;border-color:var(--line)}
.tabs .sp{flex:1}
.tabs .hint{align-self:center;font-size:.8rem;color:var(--muted);padding-right:6px}
textarea{flex:1;min-height:0;width:100%;resize:none;border:0;margin:0;padding:12px 14px;background:var(--codebg);color:var(--text);
  font-family:"Cascadia Code",Consolas,"Courier New",monospace;font-size:15px;line-height:1.5;tab-size:2;white-space:pre;overflow:auto}
.warn{display:flex;gap:10px;align-items:center;padding:7px 14px;background:var(--warn);color:var(--warntext);font-size:.9rem;border-top:1px solid var(--line)}
.warn[hidden]{display:none}
.prev{display:flex;flex-direction:column;min-height:0;background:var(--mist)}
.prev .lbl{font-size:.8rem;color:var(--muted);padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;display:flex;justify-content:space-between}
iframe{flex:1;width:100%;border:0;background:#fff}
.mtabs{display:none}
.toast{position:fixed;left:50%;bottom:18px;transform:translateX(-50%);background:var(--ink);color:#fff;padding:10px 16px;border-radius:8px;font-size:.95rem;box-shadow:0 6px 24px rgba(0,0,0,.25);max-width:90vw;z-index:20}
.toast[hidden]{display:none}
@media (max-width:760px){
  .pane{grid-template-columns:1fr;grid-template-rows:auto 1fr}
  .kode{border-right:0}
  .mtabs{display:flex;gap:4px;padding:6px 12px;background:var(--mist);border-bottom:1px solid var(--line)}
  .mtabs button{color:var(--muted);border-color:var(--line)}
  .mtabs button[aria-selected="true"]{background:var(--paper);color:var(--ink);font-weight:700}
  body.lihat-kode .prev{display:none} body.lihat-kode .kode{min-height:0}
  body:not(.lihat-kode) .kode{display:none}
  .bar .hint{display:none}
}
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
.hasil-peringatan{margin-top:1rem;padding:.7rem .9rem;background:var(--warn);color:var(--warntext);border-radius:8px;font-size:.85rem;text-align:left}
.hasil-peringatan[hidden]{display:none}
.hasil-peringatan ul{margin:.4rem 0 0;padding-left:1.2rem}
.hasil-tutup{margin-top:1.2rem;width:100%;padding:.6rem;border:none;border-radius:999px;background:var(--cherry);color:#fff;font-size:1rem;font-weight:700;cursor:pointer}
</style>
</head>
<body>
<div class="bar">
  <span class="logo">labpplg</span>
  <span class="url" id="url">__DOMAIN_HTML__/__SLUG_HTML__/edit</span>
  <a class="url" id="urlLink" href="/__SLUG_HTML__" target="_blank" rel="noopener" hidden>Lihat halamanmu ↗</a>
  <label class="btn" id="lblFoto">Unggah foto<input type="file" id="foto" accept="image/*" hidden></label>
  <select id="sisip" aria-label="Sisipkan blok">
    <option value="">Sisipkan blok ▾</option>
    <option value="A">A · Kutipan favorit</option>
    <option value="B">B · Galeri tiga foto</option>
    <option value="C">C · Lima teratas</option>
    <option value="D">D · Lencana</option>
    <option value="E">E · Tombol rahasia</option>
    <option value="F">F · Hitung mundur</option>
    <option value="G">G · Hujan emoji</option>
  </select>
  <select id="pilihVersi" aria-label="Versi tersimpan" hidden>
    <option value="">Versi tersimpan ▾</option>
  </select>
  <button id="btnReset">Kembalikan ke contoh</button>
  <button class="pub" id="btnTerbit">Terbitkan</button>
</div>
<div class="mtabs" role="tablist">
  <button id="mKode" role="tab" aria-selected="true">Kode</button>
  <button id="mPrev" role="tab" aria-selected="false">Pratinjau</button>
</div>
<div class="pane">
  <div class="kode">
    <div class="tabs" role="tablist">
      <button id="tabIsi" role="tab" aria-selected="true">Isi</button>
      <button id="tabGaya" role="tab" aria-selected="false">Gaya</button>
      <span class="sp"></span>
      <span class="hint" id="hint">HTML · ubah teks di antara tag, jangan hapus tagnya</span>
    </div>
    <textarea id="edIsi" spellcheck="false" aria-label="Kode Isi (HTML)"></textarea>
    <textarea id="edGaya" spellcheck="false" aria-label="Kode Gaya (CSS)" hidden></textarea>
    <div class="warn" id="warn" hidden>⚠ <span id="warnTxt"></span></div>
  </div>
  <div class="prev">
    <div class="lbl"><span>Pratinjau</span><span id="prevInfo">berubah saat kamu mengetik</span></div>
    <iframe id="frame" title="Pratinjau halaman" sandbox="allow-scripts"></iframe>
  </div>
</div>
<div class="toast" id="toast" hidden></div>

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
    <h1>Halamanmu sudah terbit!</h1>
    <p class="hasil-url" id="hasilUrl"></p>
    <div class="qr" id="qr"></div>
    <p class="hasil-hint">Foto QR ini pakai kamera HP, atau bagikan tautannya.</p>
    <div class="hasil-tombol">
      <button id="btnBagikan">Bagikan</button>
      <button id="btnSalin">Salin tautan</button>
      <a class="tombol-buka" id="btnBuka" href="/__SLUG_HTML__" target="_blank" rel="noopener">Buka halaman ↗</a>
    </div>
    <div class="hasil-peringatan" id="hasilPeringatan" hidden>
      <div>⚠ Ada bagian yang diberesin otomatis waktu diterbitkan:</div>
      <ul id="peringatanList"></ul>
    </div>
    <button class="hasil-tutup" id="hasilTutup">Lanjut menyunting</button>
  </div>
</div>

<script src="/aset/qrcode.js"></script>
<script>
(function(){
  var SLUG=__SLUG_JSON__, DOMAIN=__DOMAIN_JSON__;
  var $=function(id){return document.getElementById(id)};
  var edIsi=$('edIsi'), edGaya=$('edGaya'), frame=$('frame'), warn=$('warn'), warnTxt=$('warnTxt');
  var gate=$('gate'), gateKode=$('gateKode'), gateErr=$('gateErr');
  var CONTOH_ISI='', CONTOH_GAYA='', KODE=null;
  var MARK='<!-- ===== BLOK TAMBAHAN';
  var BLOK={
    A:'<blockquote class="kutipan">\n  Jangan takut salah, takutlah tidak mencoba.\n  <cite>— Kata Ibu</cite>\n</blockquote>',
    B:'<div class="galeri">\n  <img src="foto/contoh-1.svg" alt="Gambar komikku">\n  <img src="foto/contoh-2.svg" alt="Kopi si kucing">\n  <img src="foto/contoh-3.svg" alt="Lapangan voli">\n</div>',
    C:'<h2>Lima lagu favoritku</h2>\n<ol class="top5">\n  <li>Lagu pertama</li>\n  <li>Lagu kedua</li>\n  <li>Lagu ketiga</li>\n  <li>Lagu keempat</li>\n  <li>Lagu kelima</li>\n</ol>',
    D:'<div class="lencana">\n  <span>Kelas 9A</span>\n  <span>Tim voli</span>\n  <span>Pencinta kucing</span>\n</div>',
    E:'<button class="rahasia"\n  data-pesan="Kamu menemukan rahasiaku: aku takut cicak.">\n  Jangan diklik\n</button>',
    F:'<div class="mundur"\n  data-tanggal="2027-06-12"\n  data-label="hari lagi menuju lulus SMP">\n</div>',
    G:'<div class="hujan"\n  data-emoji="⭐"\n  data-jumlah="25">\n</div>'
  };

  /* ---- pintu masuk ---- */
  function tampilkanGate(pesan){
    gate.hidden=false;
    if(pesan){ gateErr.textContent=pesan; gateErr.hidden=false; } else { gateErr.hidden=true; }
    gateKode.focus();
  }
  function sembunyikanGate(){ gate.hidden=true; }

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
        CONTOH_ISI=res.data.templat.isi; CONTOH_GAYA=res.data.templat.gaya;
        edIsi.value=res.data.isi||CONTOH_ISI; edGaya.value=res.data.gaya||CONTOH_GAYA;
        isiDaftarVersi(res.data.versi);
        sembunyikanGate(); load(); render();
      })
      .catch(function(){ $('gateMasuk').disabled=false; tampilkanGate('Tidak bisa menghubungi server. Coba lagi.'); });
  }
  $('gateMasuk').onclick=function(){ bukaDenganKode(gateKode.value); };
  gateKode.addEventListener('keydown',function(e){ if(e.key==='Enter') bukaDenganKode(gateKode.value); });

  /* ---- simpan/muat draf (per browser, per slug) ---- */
  function load(){
    try{
      var d=JSON.parse(localStorage.getItem('draf-'+SLUG)||'null');
      if(d&&typeof d.isi==='string'){ edIsi.value=d.isi; edGaya.value=d.gaya; }
    }catch(e){}
  }
  function save(){
    try{ localStorage.setItem('draf-'+SLUG,JSON.stringify({isi:edIsi.value,gaya:edGaya.value})); }catch(e){}
  }

  /* ---- cek keseimbangan tag ---- */
  var VOID={img:1,br:1,hr:1,input:1,meta:1,link:1,source:1,wbr:1};
  function cekTag(html){
    var s=html.replace(/<!--[\s\S]*?-->/g,function(m){return m.replace(/[^\n]/g,' ')});
    var re=/<\s*(\/?)([a-zA-Z][\w-]*)([^<>]*?)(\/?)>/g, m, stack=[];
    var lineOf=function(pos){return s.slice(0,pos).split('\n').length};
    while((m=re.exec(s))){
      var close=m[1]==='/', name=m[2].toLowerCase(), self=m[4]==='/';
      if(VOID[name]||self) continue;
      if(!close){ stack.push({n:name,l:lineOf(m.index)}); }
      else{
        if(!stack.length) return 'ada </'+name+'> di baris '+lineOf(m.index)+' tanpa pembukanya';
        var top=stack.pop();
        if(top.n!==name) return '<'+top.n+'> di baris '+top.l+' belum ditutup, tetapi ada </'+name+'> di baris '+lineOf(m.index);
      }
    }
    if(stack.length){ var t=stack[stack.length-1]; return '<'+t.n+'> di baris '+t.l+' belum ditutup'; }
    var lt=(s.match(/</g)||[]).length, gt=(s.match(/>/g)||[]).length;
    if(lt!==gt) return 'jumlah tanda < dan > tidak sama; ada tag yang belum lengkap';
    return null;
  }

  /* ---- pratinjau ---- */
  var lastGood=null, timer=null;
  function bangun(isi,gaya){
    return '<!doctype html><html lang="id"><head><meta charset="utf-8">'
      +'<base href="/'+SLUG+'/">'
      +'<link rel="stylesheet" href="/aset/dasar.css">'
      +'<style>'+gaya+'</style></head><body><div class="halaman">'+isi+'</div>'
      +'<script src="/aset/blok.js"><\/script></body></html>';
  }
  function render(){
    var err=cekTag(edIsi.value);
    if(err){ warnTxt.textContent=err+'. Pratinjau menampilkan versi terakhir yang benar.'; warn.hidden=false; if(lastGood!==null) frame.srcdoc=bangun(lastGood,edGaya.value); return; }
    warn.hidden=true; lastGood=edIsi.value; frame.srcdoc=bangun(edIsi.value,edGaya.value);
  }
  function jadwal(){ save(); clearTimeout(timer); timer=setTimeout(render,350); }
  edIsi.addEventListener('input',jadwal); edGaya.addEventListener('input',jadwal);

  /* ---- tab penutup otomatis: ketik ">" setelah <tag ...> ---- */
  edIsi.addEventListener('keydown',function(e){
    if(e.key!=='>') return;
    var v=edIsi.value, p=edIsi.selectionStart, awal=v.lastIndexOf('<',p-1);
    if(awal<0) return;
    var frag=v.slice(awal,p), m=/^<([a-zA-Z][\w-]*)[^<>\/]*$/.exec(frag);
    if(!m||VOID[m[1].toLowerCase()]) return;
    var sesudah=v.slice(p);
    if(new RegExp('^>\\s*</'+m[1]).test(sesudah)) return;
    e.preventDefault();
    var ins='></'+m[1]+'>';
    edIsi.setRangeText(ins,p,p,'end'); edIsi.selectionStart=edIsi.selectionEnd=p+1; jadwal();
  });

  /* ---- tab Isi / Gaya ---- */
  function pilihTab(isi){
    $('tabIsi').setAttribute('aria-selected',isi); $('tabGaya').setAttribute('aria-selected',!isi);
    edIsi.hidden=!isi; edGaya.hidden=isi;
    $('hint').textContent=isi?'HTML · ubah teks di antara tag, jangan hapus tagnya':'CSS · ubah nilai setelah tanda titik dua';
    (isi?edIsi:edGaya).focus();
  }
  $('tabIsi').onclick=function(){pilihTab(true)}; $('tabGaya').onclick=function(){pilihTab(false)};

  /* ---- sisipkan blok di bawah penanda ---- */
  $('sisip').addEventListener('change',function(){
    var k=this.value; this.value=''; if(!k) return;
    pilihTab(true);
    var v=edIsi.value, i=v.indexOf(MARK);
    var kode='\n'+BLOK[k]+'\n';
    if(i<0){ var f=v.lastIndexOf('<footer'); i=f<0?v.length:f; edIsi.setRangeText(kode,i,i); }
    else { var akhir=v.indexOf('\n',i); akhir=akhir<0?v.length:akhir+1; edIsi.setRangeText(kode,akhir,akhir); i=akhir; }
    var awalBlok=edIsi.value.indexOf(BLOK[k].slice(0,12),i-2);
    var quote=edIsi.value.indexOf('"',awalBlok+20); var q2=edIsi.value.indexOf('"',quote+1);
    var selStart=quote>0?quote+1:awalBlok, selEnd=q2>0?q2:selStart;
    if(k==='A'||k==='C'||k==='D'){ var nl=edIsi.value.indexOf('\n',awalBlok); var nl2=edIsi.value.indexOf('\n',nl+1); selStart=nl+3; selEnd=nl2; if(k==='C'){selStart=awalBlok+4;selEnd=edIsi.value.indexOf('</h2>',awalBlok);} if(k==='D'){selStart=edIsi.value.indexOf('<span>',awalBlok)+6;selEnd=edIsi.value.indexOf('</span>',awalBlok);} }
    edIsi.focus(); edIsi.setSelectionRange(selStart,selEnd);
    var baris=edIsi.value.slice(0,selStart).split('\n').length; edIsi.scrollTop=Math.max(0,(baris-6)*22.5);
    toast('Blok '+k+' ditempel di bawah penanda. Ganti nilainya yang sedang disorot.');
    jadwal();
  });

  /* ---- unggah foto: perkecil di browser, kirim ke /api/foto, lalu tempel
     src yang dibalas server ke atribut src yang sedang disorot. Server
     memperkecil dan meng-encode ulang lagi — yang di sini cuma biar yang
     lewat wifi lab kecil. */
  function ganti_src(nilai){
    pilihTab(true);
    var v=edIsi.value, p=edIsi.selectionStart;
    var re=/src="([^"]*)"/g, m, hit=null;
    while((m=re.exec(v))){ if(m.index<=p&&p<=m.index+m[0].length){hit=m;break;} }
    if(!hit){ re.lastIndex=0; hit=re.exec(v); }
    if(!hit){ toast('Tidak ada src="…" yang bisa diganti.'); return false; }
    var a=hit.index+5, b=a+hit[1].length;
    edIsi.setRangeText(nilai,a,b,'select');
    jadwal();
    return true;
  }

  $('foto').addEventListener('change',function(){
    var f=this.files[0]; this.value=''; if(!f) return;
    if(!KODE){ tampilkanGate('Masuk dulu sebelum mengunggah foto.'); return; }
    var img=new Image(), url=URL.createObjectURL(f);
    img.onerror=function(){ URL.revokeObjectURL(url); toast('Berkasnya tidak bisa dibaca sebagai gambar.'); };
    img.onload=function(){
      var max=800, sc=Math.min(1,max/Math.max(img.width,img.height));
      var c=document.createElement('canvas'); c.width=Math.round(img.width*sc); c.height=Math.round(img.height*sc);
      c.getContext('2d').drawImage(img,0,0,c.width,c.height); URL.revokeObjectURL(url);
      // PNG dipertahankan supaya gambar bertransparansi tidak jadi hitam;
      // sisanya dikirim sebagai JPEG.
      var png=/\.png$/i.test(f.name)||f.type==='image/png';
      c.toBlob(function(blob){
        if(!blob){ toast('Fotonya gagal disiapkan. Coba lagi.'); return; }
        var fd=new FormData();
        fd.append('slug',SLUG); fd.append('kode',KODE);
        fd.append('berkas',blob,png?'foto.png':'foto.jpg');
        $('lblFoto').textContent='Mengunggah…';
        fetch('/api/foto',{method:'POST',body:fd})
          .then(function(r){ return r.json().then(function(d){ return {status:r.status,data:d}; }); })
          .then(function(res){
            $('lblFoto').textContent='Unggah foto';
            if(res.status===401){ KODE=null; try{sessionStorage.removeItem('karya-kode-'+SLUG);}catch(e){} tampilkanGate('Kode sudah tidak berlaku. Masuk lagi.'); return; }
            if(res.status!==200){ toast((res.data&&res.data.error)||'Fotonya gagal diunggah.'); return; }
            if(ganti_src(res.data.src)) toast('Foto terpasang. Terbitkan supaya orang lain bisa lihat.');
          })
          .catch(function(){ $('lblFoto').textContent='Unggah foto'; toast('Tidak bisa menghubungi server. Coba lagi.'); });
      }, png?'image/png':'image/jpeg', .82);
    };
    img.src=url;
  });

  /* ---- versi tersimpan ---- */
  function isiDaftarVersi(daftar){
    var sel=$('pilihVersi');
    while(sel.options.length>1) sel.remove(1);
    if(!daftar||!daftar.length){ sel.hidden=true; return; }
    daftar.forEach(function(v){
      var o=document.createElement('option');
      o.value=v.id;
      var d=new Date(v.waktu);
      o.textContent=isNaN(d)?v.id:d.toLocaleString('id-ID',{day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'});
      sel.appendChild(o);
    });
    sel.hidden=false;
  }

  $('pilihVersi').addEventListener('change',function(){
    var id=this.value; this.value=''; if(!id) return;
    if(!KODE){ tampilkanGate('Masuk dulu.'); return; }
    if(!confirm('Muat versi ini ke editor? Yang sedang kamu tulis sekarang akan tertimpa (tapi belum diterbitkan).')) return;
    fetch('/api/versi',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({slug:SLUG,kode:KODE,id:id})})
      .then(function(r){ return r.json().then(function(d){ return {status:r.status,data:d}; }); })
      .then(function(res){
        if(res.status!==200){ toast((res.data&&res.data.error)||'Versi itu gagal dimuat.'); return; }
        edIsi.value=res.data.isi; edGaya.value=res.data.gaya;
        jadwal(); toast('Versi lama dimuat. Belum diterbitkan — tekan Terbitkan kalau mau dipakai.');
      })
      .catch(function(){ toast('Tidak bisa menghubungi server. Coba lagi.'); });
  });

  /* ---- kembalikan ke contoh ---- */
  $('btnReset').onclick=function(){
    var isi=!edIsi.hidden;
    if(!confirm('Kembalikan tab '+(isi?'Isi':'Gaya')+' ke contoh? Perubahanmu di tab ini akan hilang.')) return;
    if(isi) edIsi.value=CONTOH_ISI; else edGaya.value=CONTOH_GAYA;
    jadwal(); toast('Tab '+(isi?'Isi':'Gaya')+' dikembalikan ke contoh.');
  };

  /* ---- panel hasil terbit: alamat, QR, bagikan ---- */
  // QR digambar sendiri dari matriks qrcode-generator (bukan innerHTML), jadi
  // tidak ada string HTML yang disuntikkan ke halaman.
  function gambarQr(teks){
    var kotak=$('qr');
    kotak.replaceChildren();
    var qr;
    try{ qr=qrcode(0,'M'); qr.addData(teks); qr.make(); }
    catch(e){ return; } // QR gagal bukan alasan menyembunyikan alamatnya
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
  function tampilkanHasil(url,peringatan){
    ALAMAT=location.protocol+'//'+location.host+url;
    $('hasilUrl').textContent=DOMAIN+url;
    $('btnBuka').href=url;
    gambarQr(ALAMAT);

    var kotak=$('hasilPeringatan'), ul=$('peringatanList');
    ul.replaceChildren();
    if(peringatan&&peringatan.length){
      peringatan.forEach(function(p){ var li=document.createElement('li'); li.textContent=p; ul.appendChild(li); });
      kotak.hidden=false;
    } else { kotak.hidden=true; }

    $('hasil').hidden=false;
  }
  $('hasilTutup').onclick=function(){ $('hasil').hidden=true; };

  $('btnBagikan').onclick=function(){
    if(navigator.share){
      navigator.share({title:'Halaman webku',url:ALAMAT}).catch(function(){});
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
    if(!warn.hidden){ toast('Masih ada tag yang belum ditutup. Perbaiki dulu sebelum menerbitkan.'); return; }
    if(!KODE){ tampilkanGate('Masuk dulu sebelum menerbitkan.'); return; }
    $('btnTerbit').disabled=true;
    fetch('/api/terbit',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({slug:SLUG,kode:KODE,isi:edIsi.value,gaya:edGaya.value})})
      .then(function(r){ return r.json().then(function(d){ return {status:r.status,data:d}; }); })
      .then(function(res){
        $('btnTerbit').disabled=false;
        if(res.status===401){ KODE=null; try{sessionStorage.removeItem('karya-kode-'+SLUG);}catch(e){} tampilkanGate('Kode sudah tidak berlaku. Masuk lagi.'); return; }
        if(res.status!==200){ toast((res.data&&res.data.error)||'Gagal menerbitkan, coba lagi.'); return; }
        var link=$('urlLink'); link.href=res.data.url; link.hidden=false;
        isiDaftarVersi(res.data.versi);
        tampilkanHasil(res.data.url,res.data.peringatan);
      })
      .catch(function(){ $('btnTerbit').disabled=false; toast('Tidak bisa menghubungi server. Coba lagi.'); });
  };

  /* ---- tab HP: kode / pratinjau ---- */
  $('mKode').onclick=function(){document.body.classList.add('lihat-kode'); this.setAttribute('aria-selected',true); $('mPrev').setAttribute('aria-selected',false)};
  $('mPrev').onclick=function(){document.body.classList.remove('lihat-kode'); this.setAttribute('aria-selected',true); $('mKode').setAttribute('aria-selected',false)};
  if(window.matchMedia('(max-width:760px)').matches) document.body.classList.add('lihat-kode');

  var tt=null; function toast(m){ var t=$('toast'); t.textContent=m; t.hidden=false; clearTimeout(tt); tt=setTimeout(function(){t.hidden=true},4200); }

  /* ---- mulai: coba kode tersimpan dari sesi ini, kalau tidak ada tampilkan gate ---- */
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
