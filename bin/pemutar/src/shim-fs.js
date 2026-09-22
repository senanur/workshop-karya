// Shim untuk require('fs') — dipakai hanya oleh linebreak/grapheme-breaker,
// yang memuat data trie Unicode-nya lewat fs.readFileSync. Panggilan itu
// sudah ditransform build.mjs menjadi require() atas modul data yang
// dihasilkan, jadi di sini cukup objek kosong supaya baris `fs = require('fs')`
// tidak error saat bundling browser.
module.exports = {};