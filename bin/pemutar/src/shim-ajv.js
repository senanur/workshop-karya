// Shim untuk 'ajv' — lihat catatan di build.mjs. Menerapkan cukup API yang
// dipakai scratch-parser (ajv() -> {addSchema, compile}) supaya validator
// skema JSON di peramban tidak mengkompilasi fungsi lewat `new Function`
// (yang butuh 'unsafe-eval').
//
// Validasi .sb3 sesungguhnya sudah dilakukan server (app/sb3.php) sebelum
// berkas terbit. Yang perlu dipertahankan di sini hanyalah keputusan
// scratch-parser tentang versi proyek (SB2 vs SB3): SB2 ditolak, SB3 diterima
// bila berbentuk proyek Scratch 3 (ada `targets` dan `meta`).
function AjvShim() {
    const api = {
        addSchema() {
            return api;
        },
        compile(schema) {
            const isSb3 = typeof schema === 'object' && schema !== null &&
                String(schema.$id).indexOf('sb3') !== -1;
            if (isSb3) {
                return function validatorSb3(input) {
                    return !!input && Array.isArray(input.targets) && !!input.meta;
                };
            }
            // SB2 dan sprite tidak didukung pemutar ini.
            return function validatorSb2() {
                return false;
            };
        }
    };
    return api;
}

module.exports = AjvShim;