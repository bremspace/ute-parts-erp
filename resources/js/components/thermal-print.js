// [T-35] [ADR 0009] Cetak thermal 58mm/80mm via Web Bluetooth (ESC/POS) + fallback Web Print
// Pure-client, tanpa dependensi npm baru. Barcode GS k Code128, ASCI-safe (transliterasi seperti sisi server).
// Alur: Bluetooth dulu → gagal → window.print() dengan area render tersembunyi (tidak pernah buntu).
// Fallback server-side (queue/CUPS) dikerjakan paralel di sisi backend (fixer).

/* ============================== ESC/POS builder ============================== */

const ESC = 0x1b;

// Transliterasi ASCI-safe: é→e, dst. (sama dengan sisi fixer), sisanya → '?'
const MAP = { ß: 'ss', œ: 'oe', æ: 'ae', '’': "'", '‘': "'", '“': '"', '”': '"', '–': '-', '—': '-', '…': '...', '°': 'o', '·': '.', '\u00a0': ' ' };

function transliterate(s) {
    return String(s ?? '')
        .replace(/[\u2018\u2019]/g, "'").replace(/[\u201c\u201d]/g, '"')
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^\x20-\x7E]/g, (c) => MAP[c] ?? '?');
}

class EscPos {
    constructor() {
        this.bytes = [ESC, 0x40]; // init printer
    }
    raw(...b) { this.bytes.push(...b); return this; }
    align(a) { return this.raw(ESC, 0x61, a); } // 0=kiri 1=tengah 2=kanan
    bold(on) { return this.raw(ESC, 0x45, on ? 1 : 0); }
    size(n) { return this.raw(0x1d, 0x21, n); } // GS ! n (0=1x, 0x10=2x tinggi, 0x80=2x lebar, 0x90=keduanya)
    text(s) {
        for (const ch of transliterate(s)) this.bytes.push(ch.charCodeAt(0) & 0xff);
        return this;
    }
    line(s) { return this.text(s).raw(0x0a); }
    feed(n) { return this.raw(ESC, 0x64, n); }
    barcode128(s) {
        const d = transliterate(String(s));
        this.raw(0x1d, 0x6b, 0x49, d.length); // GS k 73 = Code128
        this.text(d);
        return this;
    }
    cut() { return this.raw(0x1d, 0x56, 0x41); } // GS V 65 = partial cut
    toBytes() { return new Uint8Array(this.bytes); }
}

/* ============================== Helpers ============================== */

function wrap(text, width) {
    const lines = [];
    let cur = '';
    for (const w of String(text ?? '').split(/\s+/)) {
        if (!w) continue;
        if ((cur + ' ' + w).trim().length > width) {
            if (cur) lines.push(cur.trim());
            if (w.length > width) {
                let rest = w;
                while (rest.length > width) { lines.push(rest.slice(0, width)); rest = rest.slice(width); }
                cur = rest;
            } else {
                cur = w;
            }
        } else {
            cur = (cur + ' ' + w).trim();
        }
    }
    if (cur) lines.push(cur.trim());
    return lines;
}

function rupiah(n) {
    return 'Rp ' + Math.round(Number(n) || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

// Entri layout: {t:'line', s, c:0/1/2 (align), b (bold), h (size 0/1/2)} | {t:'sep', w} | {t:'barcode', d} | {t:'feed', n}
function pad(s, w) { s = String(s); return s.length >= w ? s : s + ' '.repeat(w - s.length); }
function pair(l, r, w) { return pad(l, w - String(r).length) + r; }

/* ============================== Layout 58mm (lebar 32) ============================== */

function layoutStruk58(data) {
    const E = [];
    const W = 32;
    E.push({ t: 'line', s: 'UTE PARTS', c: 1, b: 1, h: 1 });
    E.push({ t: 'line', s: 'Pusat Sparepart & Servis HP', c: 1 });
    E.push({ t: 'line', s: String(data.waktu ?? ''), c: 1 });
    E.push({ t: 'sep', w: W });
    E.push({ t: 'line', s: pair('No. TRX:', data.no_transaksi ?? '-', W) });
    E.push({ t: 'line', s: pair('Kasir:', data.kasir ?? '-', W) });
    E.push({ t: 'line', s: pair('Pelanggan:', `${data.pelanggan ?? 'Umum'} (${data.tier ?? 'Retail'})`, W) });
    E.push({ t: 'sep', w: W });
    for (const it of (data.items ?? [])) {
        for (const ln of wrap(it.nama ?? '', W - 2)) E.push({ t: 'line', s: '  ' + ln, b: 1 });
        E.push({ t: 'line', s: pair(`${it.qty ?? 0}x ${rupiah(it.harga)}`, rupiah(it.subtotal), W) });
    }
    E.push({ t: 'sep', w: W });
    E.push({ t: 'line', s: pair('TOTAL', rupiah(data.total), W), b: 1, h: 1 });
    E.push({ t: 'line', s: pair('BAYAR (' + (data.metode ?? '') + ')', rupiah(data.bayar), W) });
    E.push({ t: 'line', s: pair('KEMBALI', rupiah(data.kembali), W) });
    E.push({ t: 'sep', w: W });
    E.push({ t: 'barcode', d: data.no_transaksi ?? '' });
    E.push({ t: 'line', s: 'Terima Kasih atas Kunjungan Anda!', c: 1 });
    E.push({ t: 'line', s: 'Garansi part sesuai ketentuan toko.', c: 1 });
    E.push({ t: 'feed', n: 3 });
    return E;
}

/* ============================== Layout 80mm faktur (lebar 42) ============================== */

function layoutFaktur80(data) {
    const E = [];
    const W = 42;
    E.push({ t: 'line', s: 'UTE PARTS', c: 1, b: 1, h: 1 });
    E.push({ t: 'line', s: 'Pusat Sparepart & Servis HP', c: 1 });
    E.push({ t: 'line', s: `${data.waktu ?? ''}`, c: 1 });
    E.push({ t: 'sep', w: W, c: '=' });
    E.push({ t: 'line', s: pair('No. Faktur:', data.no_transaksi ?? '-', W) });
    E.push({ t: 'line', s: pair('Kasir:', data.kasir ?? '-', W) });
    E.push({ t: 'line', s: pair('Pelanggan:', data.pelanggan ?? 'Umum', W) });
    E.push({ t: 'line', s: pair('Tier:', data.tier ?? 'Retail', W) });
    E.push({ t: 'sep', w: W, c: '=' });
    E.push({ t: 'line', s: pad('NAMA / JASA', 15) + pad('QTY', 4) + pad('HARGA', 11) + pad('SUB', 12), b: 1 });
    let no = 1;
    for (const it of (data.items ?? [])) {
        const head = wrap(it.nama ?? '', W - 28);
        head.forEach((ln, i) => E.push({ t: 'line', s: (i === 0 ? pad(no++ + '.', 4) : '    ') + ln }));
        E.push({ t: 'line', s: pad(`${it.qty ?? 0}x ${rupiah(it.harga)}`, W - 12) + pad(rupiah(it.subtotal), 12) });
    }
    E.push({ t: 'sep', w: W, c: '=' });
    E.push({ t: 'line', s: pair('SUBTOTAL', rupiah(data.subtotal), W) });
    if (Number(data.diskon)) E.push({ t: 'line', s: pair('DISKON', '-' + rupiah(data.diskon), W) });
    if (data.ppn != null && Number(data.ppn)) E.push({ t: 'line', s: pair('PPN 11%', rupiah(data.ppn), W) });
    E.push({ t: 'line', s: pair('TOTAL', rupiah(data.total), W), b: 1, h: 1 });
    E.push({ t: 'line', s: pair('BAYAR (' + (data.metode ?? '') + ')', rupiah(data.bayar), W) });
    E.push({ t: 'line', s: pair('KEMBALI', rupiah(data.kembali), W) });
    E.push({ t: 'sep', w: W, c: '=' });
    E.push({ t: 'barcode', d: data.no_transaksi ?? '' });
    E.push({ t: 'line', s: 'Terima Kasih atas Kunjungan Anda!', c: 1 });
    E.push({ t: 'line', s: 'Garansi part sesuai ketentuan toko.', c: 1 });
    E.push({ t: 'feed', n: 1 });
    E.push({ t: 'line', s: pad('( ' + (data.kasir ?? '') + ' )', W / 2) + '  ( Pelanggan )' });
    E.push({ t: 'line', s: pad('', W / 2) + '  ' });
    E.push({ t: 'line', s: pad('Kasir', W / 2) + '  ' + 'Pelanggan' });
    E.push({ t: 'feed', n: 4 });
    return E;
}

/* ============================== Renderer: bytes (ESC/POS) ============================== */

function entriesToBytes(entries, width) {
    const p = new EscPos();
    for (const e of entries) {
        if (e.t === 'feed') { p.feed(e.n); continue; }
        if (e.t === 'sep') {
            const ch = e.c || '-';
            p.line(ch.repeat(e.w));
            continue;
        }
        if (e.t === 'barcode') {
            p.align(1).size(0).barcode128(e.d).feed(2); // 2 baris di bawah barcode
            continue;
        }
        // line
        p.align(e.c ?? 0).size(e.h ? (e.h === 1 ? 0x10 : 0x90) : 0).bold(!!e.b);
        let s = e.s;
        if (e.c === 2) s = s.padStart(width); // rata kanan
        p.line(s);
    }
    p.cut();
    return p.toBytes();
}

/* ============================== Renderer: HTML (fallback Web Print) ============================== */

function entriesToHtml(entries) {
    const parts = [];
    for (const e of entries) {
        if (e.t === 'feed') { for (let i = 0; i < e.n; i++) parts.push(''); continue; }
        if (e.t === 'sep') { parts.push(e.s || ''); continue; }
        if (e.t === 'barcode') {
            parts.push('[BARCODE ' + e.d + ']');
            continue;
        }
        const cls = [];
        if (e.c === 1) cls.push('c');
        if (e.c === 2) cls.push('r');
        if (e.b) cls.push('b');
        if (e.h === 1) cls.push('h2');
        if (e.h === 2) cls.push('h4');
        parts.push(`<div class="${cls.join(' ')}">${escapeHtml(e.s)}</div>`);
    }
    return parts.join('');
}

function escapeHtml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

/* ============================== Bluetooth print ============================== */

async function printBluetooth(bytes) {
    if (!('bluetooth' in navigator)) {
        alert('Browser ini tidak mendukung Bluetooth. Coba gunakan Chrome di Android.');
        throw new Error('bluetooth-unsupported');
    }
    const device = await navigator.bluetooth.requestDevice({
        acceptAllDevices: true,
        optionalServices: ['0000ffe0-0000-1000-8000-00805f9b34fb', '000018f0-0000-1000-8000-00805f9b34fb'],
    });
    const server = await device.gatt.connect();
    let char = null;
    try {
        const services = await server.getPrimaryServices();
        for (const svc of services) {
            let cs = [];
            try { cs = await svc.getCharacteristics(); } catch (err) { /* layanan tertentu diblokir browser */ }
            char = cs.find((c) => c.properties.write || c.properties.writeWithoutResponse);
            if (char) break;
        }
        if (!char) {
            throw new Error('Printer Bluetooth tidak ditemukan. Pastikan printer menyala dan dalam mode pairing.');
        }
        if (char.properties.write) await char.writeValue(bytes);
        else if (char.properties.writeWithoutResponse) await char.writeValueWithoutResponse(bytes);
    } finally {
        try { if (device.gatt.connected) device.gatt.disconnect(); } catch (err) { /* abaikan */ }
    }
}

/* ============================== Alpine component ============================== */

document.addEventListener('alpine:init', () => {
    Alpine.data('thermalPrinter', (receiptData) => ({
        data: receiptData || {},

        async printStruk58() {
            try {
                await printBluetooth(buildStruk58(this.data));
                return;
            } catch (e) {
                // Batal pilih perangkat (NotFoundError/AbortError) → diam, jangan dialog print.
                if (e && (e.name === 'NotFoundError' || e.name === 'AbortError')) return;
            }
            this.printFallback('58', entriesToHtml(layoutStruk58(this.data)));
        },

        async printFaktur80() {
            try {
                await printBluetooth(buildFaktur80(this.data));
                return;
            } catch (e) {
                if (e && (e.name === 'NotFoundError' || e.name === 'AbortError')) return;
            }
            this.printFallback('80', entriesToHtml(layoutFaktur80(this.data)));
        },

        // Fallback: render area tersembunyi → window.print() → bersihkan setelah cetak.
        printFallback(kind, html) {
            const area = document.getElementById('printTarget-' + kind);
            if (!area) { window.print(); return; }
            area.innerHTML = html;
            document.body.dataset.modalPrint = kind;
            const done = () => {
                delete document.body.dataset.modalPrint;
                area.innerHTML = '';
                window.removeEventListener('afterprint', done);
            };
            window.addEventListener('afterprint', done);
            setTimeout(done, 60 * 1000); // pengaman jika dialog print dibatalkan
            setTimeout(() => window.print(), 60);
        },
    }));
});

function buildStruk58(data) { return entriesToBytes(layoutStruk58(data), 32); }
function buildFaktur80(data) { return entriesToBytes(layoutFaktur80(data), 42); }