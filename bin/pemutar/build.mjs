// build.mjs — menghasilkan aset/scratch/pemutar.js dari bin/pemutar/src/glue.js.
//
// Dibangun di mesin pengembangan, sekali, dan hasilnya di-commit (PRD-jalur-scratch
// §8): Dockerfile tidak punya tahap Node. Menjalankan:
//
//     cd bin/pemutar && npm install && node build.mjs
//
// Paket Scratch yang dirujuk src/glue.js di-alias ke sumbernya sendiri (bukan dist)
// supaya yang masuk bundel persis versi BSD-3 yang dipasang di package.json dan
// tidak ada salinan ganda (scratch-vm membawa scratch-svg-renderer dan
// scratch-storage sebagai dependensinya sendiri dengan versi yang lebih tua).
//
// Kode sumber Scratch masih memakai beberapa loader khas webpack yang tidak dikenal
// esbuild; semuanya ditangani plugin "loaderKhas" di bawah:
//
//   - require('...mp3?arrayBuffer')  — ekstensi musik Scratch; berkas dibaca lalu
//     di-decode menjadi ArrayBuffer saat runtime.
//   - require('raw-loader!...')       — shader GLSL; isi berkas dipakai apa adanya.
//   - require('base64-loader!...')    — font; diekspor sebagai string base64.
//   - require('!ify-loader!pkg')      — transform lama; cukup resolve paketnya biasa.

import path from 'node:path';
import fs from 'node:fs';
import {createRequire} from 'node:module';
import {fileURLToPath} from 'node:url';
import esbuild from 'esbuild';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..', '..');
const outfile = path.join(root, 'aset', 'scratch', 'pemutar.js');

const loaderKhas = {
    name: 'loader-khas-scratch',
    setup(build) {
        const req = createRequire(path.join(__dirname, 'x.js'));

        // require('...?arrayBuffer') -> ArrayBuffer berisi isi berkas.
        build.onResolve({filter: /\?arrayBuffer$/}, args => {
            const clean = args.path.replace(/\?arrayBuffer$/, '');
            return {path: path.resolve(path.dirname(args.importer), clean), namespace: 'ab'};
        });
        build.onLoad({filter: /.*/, namespace: 'ab'}, args => {
            const data = fs.readFileSync(args.path);
            const b64 = data.toString('base64');
            const contents =
                'var b=require("buffer").Buffer;' +
                'var u=new Uint8Array(b.from("' + b64 + '","base64"));' +
                'module.exports=u.buffer;';
            return {contents, loader: 'js', resolveDir: path.dirname(args.path)};
        });

        // require('raw-loader!./shaders/x.glsl') -> string isi berkas.
        build.onResolve({filter: /^raw-loader!/}, args => {
            const rel = args.path.replace(/^raw-loader!/, '');
            return {path: path.resolve(path.dirname(args.importer), rel), namespace: 'raw'};
        });
        build.onLoad({filter: /.*/, namespace: 'raw'}, args => {
            const contents = JSON.stringify(fs.readFileSync(args.path, 'utf8'));
            return {contents: 'module.exports=' + contents + ';', loader: 'js'};
        });

        // require('base64-loader!./font.ttf') -> string base64 isi berkas.
        build.onResolve({filter: /^base64-loader!/}, args => {
            const rel = args.path.replace(/^base64-loader!/, '');
            return {path: path.resolve(path.dirname(args.importer), rel), namespace: 'b64'};
        });
        build.onLoad({filter: /.*/, namespace: 'b64'}, args => {
            const contents = JSON.stringify(fs.readFileSync(args.path).toString('base64'));
            return {contents: 'module.exports=' + contents + ';', loader: 'js'};
        });

        // require('!ify-loader!pkg') -> resolve paket biasa dari folder importir.
        build.onResolve({filter: /^!ify-loader!/}, args => {
            const name = args.path.replace(/^!ify-loader!/, '');
            const importerFile = path.join(path.dirname(args.importer), '__resolver__.js');
            return {path: createRequire(importerFile).resolve(name)};
        });

        // linebreak & grapheme-breaker (CoBu yang lama) memuat data trie
        // Unicode lewat fs.readFileSync. ify-loader dulu yang menginlinenya;
        // di sini panggilan itu diganti require atas modul data, lalu 'fs'
        // di-shim menjadi objek kosong (lihat src/shim-fs.js).
        const linebreaker = path.join(__dirname, 'node_modules', 'linebreak', 'src', 'linebreaker.js');
        const grapheme = path.join(__dirname, 'node_modules', 'grapheme-breaker', 'src', 'GraphemeBreaker.js');

        build.onLoad({filter: /linebreak[\\/]src[\\/]linebreaker\.js$/}, args => {
            const source = fs.readFileSync(args.path, 'utf8')
                .replace("fs.readFileSync(__dirname + '/classes.trie', 'base64')", "require('./classes.trie.b64')");
            return {contents: source, loader: 'js'};
        });
        build.onLoad({filter: /grapheme-breaker[\\/]src[\\/]GraphemeBreaker\.js$/}, args => {
            const source = fs.readFileSync(args.path, 'utf8')
                .replace("fs.readFileSync(__dirname + '/classes.trie')", "require('./classes.trie.bin')");
            return {contents: source, loader: 'js'};
        });

        build.onResolve({filter: /classes\.trie\.(b64|bin)$/}, args => {
            return {path: path.resolve(path.dirname(args.importer), args.path), namespace: 'trie'};
        });
        build.onLoad({filter: /.*/, namespace: 'trie'}, args => {
            const b64Mode = args.path.endsWith('.b64');
            const data = fs.readFileSync(args.path.replace(/\.(b64|bin)$/, ''));
            if (b64Mode) {
                // linebreak memanggil fs.readFileSync(p, 'base64') -> string.
                return {contents: 'module.exports=' + JSON.stringify(data.toString('base64')) + ';', loader: 'js'};
            }
            // grapheme-breaker memanggil tanpa encoding -> Buffer/Uint8Array.
            const b64 = data.toString('base64');
            const contents =
                'var b=require("buffer").Buffer;' +
                'module.exports=new Uint8Array(b.from("' + b64 + '","base64"));';
            return {contents, loader: 'js', resolveDir: path.dirname(args.path)};
        });
    }
};

await esbuild.build({
    entryPoints: [path.join(__dirname, 'src', 'glue.js')],
    bundle: true,
    outfile,
    format: 'iife',
    globalName: 'Pemutar',
    platform: 'browser',
    target: ['es2017'],
    define: {
        'process.env.NODE_ENV': '"production"',
        // Beberapa dependensi (buffer, scratch-storage) menyebut `global`
        // sebagai objek lingkungan; di peramban itu `window`. Kode sumber
        // Scratch sendiri juga memakai pola `typeof global !== 'undefined'`.
        'global': 'window'
    },
    alias: {
        'fs': path.join(__dirname, 'src', 'shim-fs.js'),
        // ajv dipakai scratch-parser untuk memvalidasi project.json; ajv 6
        // mengkompilasi skema menjadi fungsi lewat `new Function` (butuh
        // 'unsafe-eval'). Validasi .sb3 sudah dilakukan server di app/sb3.php,
        // jadi di peramban validator itu diganti shim tanpa eval (lihat
        // shim-ajv.js). Ini yang membuat halaman pemutar lolos CSP tanpa
        // 'unsafe-eval' — lihat PRD-jalur-scratch §6.
        'ajv': path.join(__dirname, 'src', 'shim-ajv.js'),
        // isomorphic-dompurify memilih jsdom di Node lewat kondisi "default";
        // untuk peramban cukup versi browser.js yang memakai window asli,
        // persis seperti yang dipilih webpack Scratch lewat field "browser".
        'isomorphic-dompurify': path.join(__dirname, 'node_modules', 'isomorphic-dompurify', 'browser.js'),
        'scratch-vm': path.join(__dirname, 'node_modules', 'scratch-vm', 'src', 'index.js'),
        'scratch-render': path.join(__dirname, 'node_modules', 'scratch-render', 'src', 'index.js'),
        'scratch-svg-renderer': path.join(__dirname, 'node_modules', 'scratch-svg-renderer', 'src', 'index.js'),
        'scratch-storage': path.join(__dirname, 'node_modules', 'scratch-storage', 'src', 'index.ts')
    },
    minify: true,
    logLevel: 'info',
    plugins: [loaderKhas]
});

const size = fs.statSync(outfile).size;
console.log(`\npemutar.js ditulis: ${outfile} (${(size / 1024 / 1024).toFixed(2)} MB)`);
console.log(`versi: vm=${pkgVersion('scratch-vm')} render=${pkgVersion('scratch-render')} svg=${pkgVersion('scratch-svg-renderer')} storage=${pkgVersion('scratch-storage')}`);

function pkgVersion(name) {
    const p = path.join(__dirname, 'node_modules', name, 'package.json');
    if (!fs.existsSync(p)) return '?';
    return JSON.parse(fs.readFileSync(p, 'utf8')).version;
}