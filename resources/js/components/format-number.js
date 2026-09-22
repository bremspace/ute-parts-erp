// [T-34] [ADR 0011] Ribuan separator untuk input nominal (format Indonesia, mis. 1.000.000)
// Design:
// - Model TETAP bersih (angka mentah). Urutan dipastikan oleh Livewire: wire:model di-bind
//   sebagai x-model lewat Alpine.bind saat interceptInit → x-model SELALU proses lebih dulu
//   daripada atribut x-* element (termasuk x-format-number). Jadi saat 'input', x-model sudah
//   membaca el.value mentah & menulis model; listener ini hanya memformat ulang tampilan.
// - Paste '1.000.000' / '1000000': x-model sempat menulis versi berformat → kita koreksi
//   dengan el._x_model.set(toRaw(...)) sehingga backend selalu terima '1000000'.
// - Perubahan model dari luar (setQuickCash, $set, respons server): effect reaktif membaca
//   el._x_model.get() → tampilan diformat ulang (berjalan SETELAH effect x-model yang
//   menulis nilai mentah, karena terdaftar setelahnya).
// - Pendukung desimal: koma = pemisah desimal (1.234,5), titik berkelompok-3 = ribuan.
document.addEventListener('alpine:init', () => {
    Alpine.directive('format-number', (el, { expression, modifiers }, { effect, cleanup }) => {
        // Pisahkan string input menjadi bagian integer + desimal.
        const split = (s) => {
            s = String(s ?? '');
            if (!s) return { int: '', dec: '' };
            let dec = '';

            // Koma selalu pemisah desimal (konvensi Indonesia: "1.000.000,50").
            const comma = s.lastIndexOf(',');
            if (comma !== -1 && /\d/.test(s.slice(comma + 1))) {
                dec = s.slice(comma + 1).replace(/[^\d]/g, '');
                s = s.slice(0, comma);
            } else {
                // Tanpa koma: titik terakhir dianggap desimal HANYA jika bukan pengelompok
                // ribuan (semua grup setelah grup pertama berisi 3 digit).
                const dot = s.lastIndexOf('.');
                if (dot !== -1 && /\d/.test(s.slice(dot + 1))) {
                    const tail = s.slice(dot + 1);
                    const groups = s.slice(0, dot).split('.');
                    const groupedAsThousands = groups.slice(1).every((g) => g.length === 3);
                    if (!(tail.length === 3 && groupedAsThousands)) {
                        dec = tail.replace(/[^\d]/g, '');
                        s = s.slice(0, dot);
                    }
                }
            }
            return { int: s.replace(/[^\d]/g, ''), dec };
        };

        // Tampilan: integer diberi titik ribuan; desimal dikembalikan dengan koma.
        const toDisplay = (s) => {
            const { int, dec } = split(s);
            const grouped = int.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            return dec ? grouped + ',' + dec : grouped;
        };

        // Nilai mentah untuk model: hanya digit; desimal dengan titik ("1000000" / "1000000.50").
        const toRaw = (s) => {
            const { int, dec } = split(s);
            return dec ? int + '.' + dec : int;
        };

        const onInput = () => {
            // x-model (wire:model) sudah menulis model lebih dulu; koreksi jika sempat
            // menerima nilai berformat (kasus paste/isi "1.000.000").
            if (el._x_model) el._x_model.set(toRaw(el.value));
            el.value = toDisplay(el.value);
        };
        el.addEventListener('input', onInput);

        // Sinkron model → tampilan: quick-cash, $set(...), respons server, inisialisasi.
        effect(() => {
            const m = el._x_model;
            if (!m) return;
            const v = m.get();
            el.value = toDisplay(v);
        });

        cleanup(() => el.removeEventListener('input', onInput));
    });
});