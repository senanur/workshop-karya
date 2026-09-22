// Kode perekat (glue) untuk aset/scratch/pemutar.js (PRD-jalur-scratch §3.1).
//
// Tidak memakai scratch-gui sama sekali — hanya VirtualMachine (scratch-vm),
// RenderWebGL (scratch-render), dan ScratchStorage (scratch-storage). Bundel
// dihasilkan oleh build.mjs, lalu dipanggil halaman pemutar dan halaman
// unggah lewat satu global: window.Pemutar = { mulai, bendera_hijau,
// berhenti, layar_penuh }.
//
// Alur:
//   1. mulai(canvas, arrayBuffer) — bangun VM + renderer + storage untuk
//      canvas itu, muat proyek dari ArrayBuffer (semua aset sudah di dalam
//      berkas), lalu jalankan runtime-nya. Bendera hijau tidak ditekan di
//      sini — halaman yang menentukan kapan greenFlag() dipanggil.
//   2. bendera_hijau() — berhentikan semua lalu tekan bendera hijau.
//   3. berhenti() — stopAll.
//   4. layar_penuh() — minta fullscreen pada elemen induk canvas (panggung).

const {Buffer} = require('buffer');
const {ScratchStorage} = require('scratch-storage');
const RenderWebGL = require('scratch-render');
const VirtualMachine = require('scratch-vm');

if (typeof globalThis !== 'undefined' && typeof globalThis.Buffer === 'undefined') {
    globalThis.Buffer = Buffer;
}

const state = {vm: null, renderer: null, storage: null, canvas: null, terdaftar: false};

function aturUkuran() {
    if (!state.renderer || !state.canvas) return;
    const w = state.canvas.clientWidth || 480;
    const h = state.canvas.clientHeight || 360;
    if (w > 0 && h > 0) {
        state.renderer.resize(w, h);
    }
}

function bongkar() {
    if (state.vm) {
        try { state.vm.stopAll(); } catch (e) { /* abaikan */ }
        try { state.vm.quit(); } catch (e) { /* abaikan */ }
    }
    state.vm = null;
    state.renderer = null;
    state.storage = null;
    state.canvas = null;
}

async function mulai(canvas, arrayBuffer) {
    bongkar();

    const storage = new ScratchStorage();
    storage.addWebStore([
        storage.AssetType.Project,
        storage.AssetType.Sound,
        storage.AssetType.ImageVector,
        storage.AssetType.ImageBitmap
    ]);

    const vm = new VirtualMachine();
    vm.attachStorage(storage);

    const renderer = new RenderWebGL(canvas);
    vm.attachRenderer(renderer);

    state.vm = vm;
    state.renderer = renderer;
    state.storage = storage;
    state.canvas = canvas;

    if (!state.terdaftar) {
        state.terdaftar = true;
        document.addEventListener('fullscreenchange', aturUkuran);
        window.addEventListener('resize', aturUkuran);
    }

    aturUkuran();
    await vm.loadProject(arrayBuffer);
    vm.start();
    aturUkuran();
}

function bendera_hijau() {
    if (!state.vm) return;
    try { state.vm.stopAll(); } catch (e) { /* abaikan */ }
    state.vm.greenFlag();
}

function berhenti() {
    if (!state.vm) return;
    try { state.vm.stopAll(); } catch (e) { /* abaikan */ }
}

function layar_penuh() {
    if (!state.canvas) return;
    const target = state.canvas.parentElement || state.canvas;
    if (document.fullscreenElement) {
        if (document.exitFullscreen) document.exitFullscreen();
    } else if (target.requestFullscreen) {
        target.requestFullscreen().catch(function () { /* abaikan */ });
    } else if (target.webkitRequestFullscreen) {
        target.webkitRequestFullscreen();
    }
}

module.exports = {mulai, bendera_hijau, berhenti, layar_penuh};