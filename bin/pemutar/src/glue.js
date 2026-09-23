// Kode perekat (glue) untuk aset/scratch/pemutar.js (PRD-jalur-scratch §3.1).
//
// Tidak memakai scratch-gui sama sekali — hanya VirtualMachine (scratch-vm),
// RenderWebGL (scratch-render), dan ScratchStorage (scratch-storage). Bundel
// dihasilkan oleh build.mjs, lalu dipanggil halaman pemutar dan halaman
// unggah lewat satu global: window.Pemutar = { mulai, bendera_hijau,
// berhenti, layar_penuh }.
//
// Alur:
//   1. mulai(canvas, arrayBuffer) — bangun VM + renderer + storage + adapter
//      bitmap + audio engine untuk canvas itu, pasang papan ketik/mouse, muat
//      proyek dari ArrayBuffer (semua aset sudah di dalam berkas), lalu
//      jalankan runtime-nya. Bendera hijau tidak ditekan di sini — halaman
//      yang menentukan kapan greenFlag() dipanggil.
//   2. bendera_hijau() — berhentikan semua lalu tekan bendera hijau.
//   3. berhenti() — stopAll.
//   4. layar_penuh() — minta fullscreen pada elemen induk canvas (panggung).

const {Buffer} = require('buffer');
const {ScratchStorage} = require('scratch-storage');
const RenderWebGL = require('scratch-render');
const VirtualMachine = require('scratch-vm');
const AudioEngine = require('scratch-audio');
const {BitmapAdapter} = require('scratch-svg-renderer');

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

// scratch-vm never listens to the DOM itself — every keypress/click has to be
// pushed in explicitly via vm.postIOData(), the same way scratch-gui does it.
// Without this the game LOADS and RUNS (green flag works) but nothing the
// player presses has any effect, because the VM's keyboard/mouse IO devices
// never receive a single event.
const TOMBOL_CEGAH = [' ', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'];

function sedangMengetik() {
    const el = document.activeElement;
    return !!el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.isContentEditable);
}

function pasangPapanKetik() {
    // Document-level and registered once (state.terdaftar), not per canvas —
    // sedangMengetik() guards it so arrow keys typed into the kode/nama input
    // on the halaman unggah never get stolen by the game underneath it.
    document.addEventListener('keydown', e => {
        if (!state.vm || sedangMengetik()) return;
        if (TOMBOL_CEGAH.indexOf(e.key) !== -1) e.preventDefault();
        state.vm.postIOData('keyboard', {key: e.key, isDown: true});
    });
    document.addEventListener('keyup', e => {
        if (!state.vm || sedangMengetik()) return;
        state.vm.postIOData('keyboard', {key: e.key, isDown: false});
    });
}

function posisiDiKanvas(canvas, e) {
    const r = canvas.getBoundingClientRect();
    return {
        x: (e.clientX - r.left) * (canvas.width / r.width),
        y: (e.clientY - r.top) * (canvas.height / r.height)
    };
}

function pasangMouse(canvas) {
    // Keyed off the canvas element itself (not state.terdaftar) so re-calling
    // mulai() on the same <canvas> — re-uploading a .sb3 on halaman unggah —
    // never stacks a second set of listeners on it.
    if (canvas._pemutarMouseTerpasang) return;
    canvas._pemutarMouseTerpasang = true;

    canvas.addEventListener('mousemove', e => {
        if (!state.vm) return;
        const p = posisiDiKanvas(canvas, e);
        state.vm.postIOData('mouse', {x: p.x, y: p.y, canvasWidth: canvas.width, canvasHeight: canvas.height});
    });
    canvas.addEventListener('mousedown', e => {
        if (!state.vm) return;
        const p = posisiDiKanvas(canvas, e);
        state.vm.postIOData('mouse', {x: p.x, y: p.y, canvasWidth: canvas.width, canvasHeight: canvas.height, isDown: true});
    });
    // mouseup on window, not the canvas: a drag that ends outside the stage
    // must still register as released, same as scratch-gui's own behaviour.
    window.addEventListener('mouseup', e => {
        if (!state.vm) return;
        const p = posisiDiKanvas(canvas, e);
        state.vm.postIOData('mouse', {x: p.x, y: p.y, canvasWidth: canvas.width, canvasHeight: canvas.height, isDown: false});
    });
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
    // Without these two, loadProject() "succeeds" but every raster costume
    // and every sound silently fails to load — the VM only logs a warning
    // ("No V2 Bitmap adapter present." / "No audio engine present"), it
    // never rejects, so the game runs costume-less and silent instead of
    // visibly failing.
    vm.attachV2BitmapAdapter(new BitmapAdapter());
    vm.attachAudioEngine(new AudioEngine());

    state.vm = vm;
    state.renderer = renderer;
    state.storage = storage;
    state.canvas = canvas;

    if (!state.terdaftar) {
        state.terdaftar = true;
        document.addEventListener('fullscreenchange', aturUkuran);
        window.addEventListener('resize', aturUkuran);
        pasangPapanKetik();
    }
    pasangMouse(canvas);

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

// Public hook for on-screen touch controls (D-pad + action button, PRD-jalur-
// scratch §6): the page's own buttons call this instead of dispatching fake
// KeyboardEvents, so it goes through the exact same vm.postIOData() path as
// a real keyboard.
function tombol(key, isDown) {
    if (!state.vm) return;
    state.vm.postIOData('keyboard', {key, isDown});
}

module.exports = {mulai, bendera_hijau, berhenti, layar_penuh, tombol};