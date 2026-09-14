<?php

declare(strict_types=1);

if (!defined('KARYA_APP')) {
    http_response_code(404);
    exit;
}

// This page is the app's own UI (not child-submitted content), so unlike
// child pages it's allowed to carry its own inline JS. It never injects
// server-side values into markup — the whole form is populated by the
// browser via fetch(), using textContent/value assignment only, so nothing
// a child types is ever interpreted as HTML even in their own browser.
function karya_render_editor_page(): string
{
    $catalogJson = json_encode(array_map(
        static fn (array $b) => ['label' => $b['label'], 'fields' => $b['fields']],
        karya_block_catalog()
    ), JSON_UNESCAPED_UNICODE);

    return <<<HTML
<!doctype html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bikin Halamanmu</title>
<style>
  * { box-sizing:border-box; }
  body { font-family:system-ui,sans-serif; margin:0; background:#f4f4f8; color:#232323; }
  header { background:#6b3fa0; color:#fff; padding:1rem 1.25rem; }
  header h1 { margin:0; font-size:1.1rem; }
  #gate { max-width:360px; margin:2.5rem auto; padding:1.5rem; background:#fff;
          border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.06); }
  #gate label, .field label, .block-fields label { display:block; font-size:.85rem; font-weight:600; margin:.75rem 0 .25rem; }
  #gate input, .field input, .block-fields input { width:100%; padding:.55rem .7rem; border:1px solid #ccc;
                               border-radius:8px; font-size:1rem; }
  button { margin-top:1.25rem; padding:.65rem 1.1rem; border:none; border-radius:999px;
           background:#6b3fa0; color:#fff; font-size:1rem; cursor:pointer; }
  button:disabled { opacity:.5; cursor:not-allowed; }
  .msg { margin-top:.75rem; font-size:.85rem; }
  .msg.error { color:#c0392b; }
  .msg.ok { color:#1e824c; }
  #workspace { display:none; }
  .layout { display:flex; flex-wrap:wrap; gap:1.25rem; padding:1.25rem; max-width:1000px; margin:0 auto; }
  .panel { flex:1 1 360px; background:#fff; border-radius:14px; padding:1.25rem;
           box-shadow:0 2px 10px rgba(0,0,0,.06); }
  .block-toggle { display:flex; align-items:center; gap:.5rem; margin-top:1rem; font-weight:600; }
  .block-fields { margin-left:1.6rem; display:none; }
  .block-fields.open { display:block; }
  iframe#preview { width:100%; height:520px; border:1px solid #ddd; border-radius:10px; background:#fff; }
</style>
</head>
<body>
<header><h1>Bikin Halamanmu — karya.labpplg.web.id</h1></header>

<div id="gate">
  <label for="slug">Slug (dari kartu)</label>
  <input id="slug" autocomplete="off" placeholder="andi">
  <label for="kode">Kode edit (6 karakter)</label>
  <input id="kode" autocomplete="off" placeholder="A1B2C3">
  <button id="btnBuka">Buka Halamanku</button>
  <div id="gateMsg" class="msg"></div>
</div>

<div id="workspace">
  <div class="layout">
    <div class="panel">
      <div class="field">
        <label for="judul">Judul / Namamu</label>
        <input id="judul" maxlength="60">
      </div>
      <div class="field">
        <label for="tentang">Tentang aku</label>
        <input id="tentang" maxlength="280">
      </div>
      <div id="blocksArea"></div>
      <button id="btnTerbit">Terbitkan</button>
      <div id="terbitMsg" class="msg"></div>
    </div>
    <div class="panel">
      <p style="margin-top:0;font-size:.85rem;color:#666;">Pratinjau (perkiraan tampilan)</p>
      <iframe id="preview" title="Pratinjau"></iframe>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';
  var CATALOG = {$catalogJson};
  var state = { slug: '', kode: '' };

  var gate = document.getElementById('gate');
  var workspace = document.getElementById('workspace');
  var gateMsg = document.getElementById('gateMsg');
  var terbitMsg = document.getElementById('terbitMsg');
  var blocksArea = document.getElementById('blocksArea');
  var preview = document.getElementById('preview');

  function setMsg(el, text, cls) {
    el.textContent = text;
    el.className = 'msg' + (cls ? ' ' + cls : '');
  }

  function buildBlocksUI() {
    blocksArea.innerHTML = '';
    Object.keys(CATALOG).forEach(function (blockId) {
      var block = CATALOG[blockId];
      var wrap = document.createElement('div');

      var toggleRow = document.createElement('div');
      toggleRow.className = 'block-toggle';
      var cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.id = 'blk-' + blockId;
      cb.dataset.blockId = blockId;
      var lbl = document.createElement('label');
      lbl.htmlFor = cb.id;
      lbl.textContent = block.label;
      toggleRow.appendChild(cb);
      toggleRow.appendChild(lbl);
      wrap.appendChild(toggleRow);

      var fieldsDiv = document.createElement('div');
      fieldsDiv.className = 'block-fields';
      fieldsDiv.id = 'fields-' + blockId;
      Object.keys(block.fields).forEach(function (fieldName) {
        var def = block.fields[fieldName];
        var fLabel = document.createElement('label');
        fLabel.textContent = def.label;
        var fInput = document.createElement('input');
        fInput.maxLength = def.maxlen;
        fInput.dataset.blockId = blockId;
        fInput.dataset.fieldName = fieldName;
        fInput.addEventListener('input', updatePreview);
        fieldsDiv.appendChild(fLabel);
        fieldsDiv.appendChild(fInput);
      });
      wrap.appendChild(fieldsDiv);
      blocksArea.appendChild(wrap);

      cb.addEventListener('change', function () {
        fieldsDiv.classList.toggle('open', cb.checked);
        updatePreview();
      });
    });
  }

  function collectBlocks() {
    var out = {};
    Object.keys(CATALOG).forEach(function (blockId) {
      var cb = document.getElementById('blk-' + blockId);
      if (!cb || !cb.checked) { return; }
      var values = {};
      document.querySelectorAll('input[data-block-id="' + blockId + '"][data-field-name]').forEach(function (input) {
        values[input.dataset.fieldName] = input.value;
      });
      out[blockId] = values;
    });
    return out;
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
  }

  // Client-side approximation only, purely visual, never sent anywhere —
  // the real page is always rendered server-side by /api/terbit.
  function updatePreview() {
    var judul = document.getElementById('judul').value;
    var tentang = document.getElementById('tentang').value;
    var blocks = collectBlocks();
    var html = '<div style="font-family:system-ui,sans-serif;padding:1rem;">' +
      '<h1>' + esc(judul || state.slug) + '</h1><p style="color:#555;">' + esc(tentang) + '</p>';
    Object.keys(blocks).forEach(function (blockId) {
      var block = CATALOG[blockId];
      html += '<div style="margin-top:1rem;padding:.75rem 1rem;background:#f7f2fb;border-radius:10px;">' +
        '<strong>' + esc(block.label) + '</strong><br>';
      Object.keys(blocks[blockId]).forEach(function (fieldName) {
        var v = blocks[blockId][fieldName];
        if (v) { html += esc(v) + '<br>'; }
      });
      html += '</div>';
    });
    html += '</div>';
    var doc = preview.contentDocument;
    doc.open(); doc.write(html); doc.close();
  }

  document.getElementById('btnBuka').addEventListener('click', function () {
    var slug = document.getElementById('slug').value.trim().toLowerCase();
    var kode = document.getElementById('kode').value.trim();
    setMsg(gateMsg, 'Membuka...', '');

    fetch('/api/buka', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ slug: slug, kode: kode })
    }).then(function (res) {
      return res.json().then(function (data) { return { ok: res.ok, data: data }; });
    }).then(function (result) {
      if (!result.ok) {
        setMsg(gateMsg, result.data.error || 'Slug atau kode salah.', 'error');
        return;
      }
      state.slug = slug;
      state.kode = kode;
      document.getElementById('judul').value = (result.data.fields && result.data.fields.judul) || slug;
      document.getElementById('tentang').value = (result.data.fields && result.data.fields.tentang) || '';
      buildBlocksUI();
      var blocks = result.data.blocks || {};
      Object.keys(blocks).forEach(function (blockId) {
        var cb = document.getElementById('blk-' + blockId);
        if (!cb) { return; }
        cb.checked = true;
        document.getElementById('fields-' + blockId).classList.add('open');
        Object.keys(blocks[blockId]).forEach(function (fieldName) {
          var input = document.querySelector('input[data-block-id="' + blockId + '"][data-field-name="' + fieldName + '"]');
          if (input) { input.value = blocks[blockId][fieldName]; }
        });
      });
      gate.style.display = 'none';
      workspace.style.display = 'block';
      updatePreview();
    }).catch(function () {
      setMsg(gateMsg, 'Gagal menghubungi server.', 'error');
    });
  });

  document.getElementById('judul').addEventListener('input', updatePreview);
  document.getElementById('tentang').addEventListener('input', updatePreview);

  document.getElementById('btnTerbit').addEventListener('click', function () {
    var btn = document.getElementById('btnTerbit');
    btn.disabled = true;
    setMsg(terbitMsg, 'Menerbitkan...', '');

    fetch('/api/terbit', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        slug: state.slug,
        kode: state.kode,
        fields: {
          judul: document.getElementById('judul').value,
          tentang: document.getElementById('tentang').value
        },
        blocks: collectBlocks()
      })
    }).then(function (res) {
      return res.json().then(function (data) { return { ok: res.ok, data: data }; });
    }).then(function (result) {
      btn.disabled = false;
      if (!result.ok) {
        setMsg(terbitMsg, result.data.error || 'Gagal menerbitkan.', 'error');
        return;
      }
      setMsg(terbitMsg, 'Terbit! Lihat di ' + result.data.url, 'ok');
    }).catch(function () {
      btn.disabled = false;
      setMsg(terbitMsg, 'Gagal menghubungi server.', 'error');
    });
  });
})();
</script>
</body>
</html>
HTML;
}
