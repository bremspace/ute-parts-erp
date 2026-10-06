// [T-34] [ADR 0011] Ribuan separator untuk input nominal (format Indonesia, mis. 1.000.000)
// Design:
// - Model TETAP bersih (angka mentah).
// - Input event memformat tampilan dengan pemisah ribuan titik (IDR).
// - Pemisah desimal HANYA koma (mis. 1.000.000,50). Titik selalu pemisah ribuan.
// - Menjaga posisi kursor agar tidak melompat ke akhir input saat mengetik.
// - Model dari luar (Livewire, server, MySQL decimal "50000.00") dinormalisasi rapi.

document.addEventListener('alpine:init', () => {
    Alpine.directive('format-number', (el, { expression, modifiers }, { effect, cleanup }) => {
        // Parsing input DOM dari pengguna (saat mengetik atau paste)
        const parseDom = (val) => {
            if (val === null || val === undefined) return { int: '', dec: null };
            val = String(val).trim();
            if (!val) return { int: '', dec: null };

            // Koma = pemisah desimal khas Indonesia (mis. "50.000,50" atau "50,5")
            const commaIdx = val.lastIndexOf(',');
            if (commaIdx !== -1) {
                const intPart = val.slice(0, commaIdx).replace(/[^\d]/g, '');
                const decPart = val.slice(commaIdx + 1).replace(/[^\d]/g, '');
                return { int: intPart, dec: decPart };
            }

            // Tidak ada koma: semua titik adalah pemisah ribuan (atau sisa dari format sebelumnya)
            const intPart = val.replace(/[^\d]/g, '');
            return { int: intPart, dec: null };
        };

        // Parsing nilai yang datang dari model Alpine / Livewire / database
        const parseModel = (val) => {
            if (val === null || val === undefined) return { int: '', dec: null };
            if (typeof val === 'number') {
                if (isNaN(val)) return { int: '', dec: null };
                const s = String(val);
                if (s.includes('.')) {
                    const [intPart, decPart] = s.split('.');
                    return { int: intPart, dec: decPart };
                }
                return { int: s, dec: null };
            }
            let s = String(val).trim();
            if (!s) return { int: '', dec: null };

            // Koma desimal: "1.000.000,50"
            const commaIdx = s.lastIndexOf(',');
            if (commaIdx !== -1) {
                const intPart = s.slice(0, commaIdx).replace(/[^\d]/g, '');
                const decPart = s.slice(commaIdx + 1).replace(/[^\d]/g, '');
                return { int: intPart, dec: decPart };
            }

            // Lebih dari 1 titik = pasti ribuan bertitik ("1.000.000")
            const dotCount = (s.match(/\./g) || []).length;
            if (dotCount > 1) {
                return { int: s.replace(/[^\d]/g, ''), dec: null };
            }

            if (dotCount === 1) {
                const [intPart, decPart] = s.split('.');
                // Jika bagian setelah titik >= 3 digit (mis. "5.000", "5.0000", "50.0000"), itu ribuan
                if (decPart.length >= 3) {
                    return { int: s.replace(/[^\d]/g, ''), dec: null };
                }
                // Jika desimal hanya 0 (cth ".00" atau ".0"), perlakukan sebagai rupiah bulat
                if (/^0+$/.test(decPart)) {
                    return { int: intPart.replace(/[^\d]/g, ''), dec: null };
                }
                // Float 1-2 desimal (cth "1250.50" atau "10.5")
                return { int: intPart.replace(/[^\d]/g, ''), dec: decPart.replace(/[^\d]/g, '') };
            }

            return { int: s.replace(/[^\d]/g, ''), dec: null };
        };

        const formatDisplay = (parsed) => {
            const { int, dec } = parsed;
            if (!int && dec === null) return '';
            const grouped = (int || '0').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            return dec !== null ? grouped + ',' + dec : grouped;
        };

        const formatRaw = (parsed) => {
            const { int, dec } = parsed;
            if (!int && dec === null) return '';
            return dec !== null ? (int || '0') + '.' + dec : int;
        };

        const onInput = () => {
            const prevVal = el.value;
            const cursor = el.selectionStart ?? prevVal.length;
            // Hitung berapa banyak digit sebelum kursor
            const digitsBeforeCursor = prevVal.slice(0, cursor).replace(/[^\d]/g, '').length;

            const parsed = parseDom(prevVal);
            const display = formatDisplay(parsed);
            const raw = formatRaw(parsed);

            // Update model x-model / wire:model dengan angka bersih
            if (el._x_model) {
                el._x_model.set(raw);
            }

            // Update tampilan input
            el.value = display;

            // Kembalikan posisi kursor agar tidak melompat ke ujung
            if (el.setSelectionRange && typeof cursor === 'number') {
                let newCursor = display.length;
                let digitCount = 0;
                if (digitsBeforeCursor === 0) {
                    newCursor = 0;
                } else {
                    for (let i = 0; i < display.length; i++) {
                        if (/\d/.test(display[i])) {
                            digitCount++;
                        }
                        if (digitCount === digitsBeforeCursor) {
                            newCursor = i + 1;
                            break;
                        }
                    }
                }
                el.setSelectionRange(newCursor, newCursor);
            }
        };

        el.addEventListener('input', onInput, true);

        const onBlur = () => {
            if (el.value) {
                const parsed = parseDom(el.value);
                el.value = formatDisplay(parsed);
            }
        };
        el.addEventListener('blur', onBlur);

        if (el.value) {
            const parsed = parseModel(el.value);
            el.value = formatDisplay(parsed);
        }

        // Sinkronisasi dari model ke tampilan (inisialisasi, respons server, quick cash)
        effect(() => {
            const m = el._x_model;
            if (!m) return;
            const v = m.get();

            // Jika sedang diketik pengguna dan nilainya cocok, jangan timpa tampilan
            if (document.activeElement === el) {
                const currentRaw = formatRaw(parseDom(el.value));
                const modelRaw = formatRaw(parseModel(v));
                if (currentRaw === modelRaw || currentRaw === String(v ?? '')) {
                    return;
                }
            }

            const parsed = parseModel(v);
            el.value = formatDisplay(parsed);
        });

        cleanup(() => {
            el.removeEventListener('input', onInput, true);
            el.removeEventListener('blur', onBlur);
        });
    });
});
