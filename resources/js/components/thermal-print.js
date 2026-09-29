// [T-35] [ADR 0009] Cetak thermal 58mm/80mm via Web Bluetooth (BLE / GATT) murni di browser (PC & Mobile)
// Bekerja langsung di Google Chrome / Edge (Android, Windows, Mac, Linux).
// Tanpa aplikasi pihak ketiga (RawBT dll). Mendukung auto-reconnect printer tersimpan.

/* ============================== ESC/POS builder ============================== */

const ESC = 0x1b;

// Transliterasi ASCI-safe: é→e, dst., sisanya → '?'
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
        // Code128 standard ESC/POS: GS k 73 (len+2) {B data...
        this.raw(0x1d, 0x6b, 0x49, d.length + 2, 0x7b, 0x42);
        this.text(d);
        return this;
    }
    cut() {
        // Feed 3 baris lalu potong kertas
        return this.feed(3).raw(0x1d, 0x56, 0x42, 0x00);
    }
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
    if (data.no_transaksi) {
        E.push({ t: 'barcode', d: data.no_transaksi });
    }
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
    if (data.no_transaksi) {
        E.push({ t: 'barcode', d: data.no_transaksi });
    }
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
            p.align(1).size(0).barcode128(e.d).feed(2);
            continue;
        }
        // line
        p.align(e.c ?? 0).size(e.h ? (e.h === 1 ? 0x10 : 0x90) : 0).bold(!!e.b);
        let s = e.s;
        if (e.c === 2) s = s.padStart(width);
        p.line(s);
    }
    p.cut();
    return p.toBytes();
}

/* ============================== Renderer: HTML (Web Print A4 / PDF) ============================== */

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

/* ============================== Web Bluetooth (BLE GATT) ============================== */

// Daftar UUID Service BLE Thermal Printer populer (58mm & 80mm)
const THERMAL_SERVICES = [
    '0000ffe0-0000-1000-8000-00805f9b34fb', // Standard HM-10 / CC2540 / POS-58
    '000018f0-0000-1000-8000-00805f9b34fb', // MPT-II / Rego
    '0000fff0-0000-1000-8000-00805f9b34fb', // Goojprt / Panda / EP-58
    '0000ff00-0000-1000-8000-00805f9b34fb', // Xprinter / PT-210
    '0000fee7-0000-1000-8000-00805f9b34fb', // Tencent / micro-printer
    '0000ae00-0000-1000-8000-00805f9b34fb',
    '0000ae30-0000-1000-8000-00805f9b34fb',
    '0000af30-0000-1000-8000-00805f9b34fb',
    '49535343-fe7d-4ae5-8fa9-9fafd205e455', // ISSC transparent UART
    '6e400001-b5a3-f393-e0a9-e50e24dcca9e', // Nordic UART Service (NUS)
    'e7810a71-73ae-499d-8c15-faa9aef0c3f2', // Nordic UART legacy
];

// Persistent state di memory browser agar bisa print otomatis tanpa popup berulang
let cachedBleDevice = null;
let cachedCharacteristic = null;

function notify(message, type = 'info') {
    if (typeof window.showToast === 'function') {
        window.showToast(message, type);
    } else {
        console.log(`[POS Print ${type}]:`, message);
    }
}

function resolveReceiptData(passedData) {
    if (passedData && passedData.no_transaksi) return passedData;
    const el = document.getElementById('pos-receipt-data');
    if (el && el.textContent) {
        try {
            const d = JSON.parse(el.textContent);
            if (d && d.no_transaksi) return d;
        } catch (e) {
            console.error('Failed to parse #pos-receipt-data:', e);
        }
    }
    return null;
}

// Cari characteristic untuk menulis byte ESC/POS
async function findWriteCharacteristic(server) {
    let char = null;
    let services = [];

    try {
        services = await server.getPrimaryServices();
    } catch (err) {
        console.warn('[BLE] getPrimaryServices failed, checking specific UUIDs:', err);
    }

    for (const svc of services) {
        try {
            const cs = await svc.getCharacteristics();
            char = cs.find((c) => c.properties.write || c.properties.writeWithoutResponse);
            if (char) return char;
        } catch (e) { /* ignore */ }
    }

    // Jika getPrimaryServices kosong, loop spesifik service UUID
    for (const uuid of THERMAL_SERVICES) {
        try {
            const svc = await server.getPrimaryService(uuid);
            const cs = await svc.getCharacteristics();
            char = cs.find((c) => c.properties.write || c.properties.writeWithoutResponse);
            if (char) return char;
        } catch (err) { /* service tidak ada di printer ini */ }
    }

    return null;
}

// Kirim data byte ke printer Bluetooth BLE dengan chunking
async function writeBytesToCharacteristic(char, bytes) {
    const CHUNK_SIZE = 64; // Batas aman BLE MTU
    for (let i = 0; i < bytes.length; i += CHUNK_SIZE) {
        const chunk = bytes.slice(i, i + CHUNK_SIZE);
        if (char.properties.write) {
            await char.writeValue(chunk);
        } else if (char.properties.writeWithoutResponse) {
            await char.writeValueWithoutResponse(chunk);
        }
        await new Promise((resolve) => setTimeout(resolve, 20));
    }
}

// Koneksi BLE Printer (Otomatis pakai cached jika ada, atau minta pairing)
async function connectBlePrinter(forceNewPairing = false) {
    if (!('bluetooth' in navigator)) {
        const isSecure = window.location.protocol === 'https:' || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
        if (!isSecure) {
            throw new Error('SECURE_CONTEXT_REQUIRED');
        }
        throw new Error('BLUETOOTH_UNSUPPORTED');
    }

    // Jika sudah ada device tersimpan dan masih tersambung
    if (!forceNewPairing && cachedBleDevice && cachedCharacteristic && cachedBleDevice.gatt && cachedBleDevice.gatt.connected) {
        return { device: cachedBleDevice, char: cachedCharacteristic, reused: true };
    }

    // Coba reconnect ke device yang pernah dipilih jika gatt terputus
    if (!forceNewPairing && cachedBleDevice && cachedBleDevice.gatt) {
        try {
            notify(`Menghubungkan ulang ke ${cachedBleDevice.name || 'Printer Bluetooth'}...`, 'info');
            const server = await cachedBleDevice.gatt.connect();
            const char = await findWriteCharacteristic(server);
            if (char) {
                cachedCharacteristic = char;
                return { device: cachedBleDevice, char, reused: true };
            }
        } catch (err) {
            console.warn('[BLE] Reconnect failed, requesting device pairing:', err);
            cachedBleDevice = null;
            cachedCharacteristic = null;
        }
    }

    // Minta user memilih printer BLE via popup native browser
    notify('Pilih printer Bluetooth Anda pada popup browser...', 'info');
    const device = await navigator.bluetooth.requestDevice({
        acceptAllDevices: true,
        optionalServices: THERMAL_SERVICES,
    });

    device.addEventListener('gattserverdisconnected', () => {
        console.log('[BLE] Printer disconnected:', device.name);
        cachedCharacteristic = null;
    });

    const server = await device.gatt.connect();
    const char = await findWriteCharacteristic(server);

    if (!char) {
        throw new Error('Karakteristik cetak tidak ditemukan pada printer Bluetooth ini. Pastikan printer mendukung BLE ESC/POS.');
    }

    cachedBleDevice = device;
    cachedCharacteristic = char;
    localStorage.setItem('ute_ble_printer_name', device.name || 'Printer BLE');

    return { device, char, reused: false };
}

// Cetak data ke Bluetooth BLE
async function printViaBluetooth(bytes, forceNewPairing = false) {
    const { device, char, reused } = await connectBlePrinter(forceNewPairing);
    await writeBytesToCharacteristic(char, bytes);
    return device;
}

// Putus / Reset printer tersimpan
function resetBlePrinter() {
    if (cachedBleDevice && cachedBleDevice.gatt && cachedBleDevice.gatt.connected) {
        try { cachedBleDevice.gatt.disconnect(); } catch (e) { /* ignore */ }
    }
    cachedBleDevice = null;
    cachedCharacteristic = null;
    localStorage.removeItem('ute_ble_printer_name');
    notify('Printer Bluetooth berhasil diputus / di-reset.', 'info');
}

/* ============================== Universal Print Entrypoint ============================== */

let isPrintingLock = false;

async function printThermalReceipt(kind = '58', customData = null, forceNewPairing = false) {
    if (isPrintingLock) return;
    isPrintingLock = true;

    try {
        const data = resolveReceiptData(customData);
        if (!data) {
            notify('Data struk tidak ditemukan.', 'warning');
            return;
        }

        const bytes = kind === '80' ? buildFaktur80(data) : buildStruk58(data);

        try {
            const device = await printViaBluetooth(bytes, forceNewPairing);
            notify(`Struk berhasil dicetak ke ${device.name || 'Printer Bluetooth'}!`, 'success');
        } catch (e) {
            console.error('[POS BLE Print Error]:', e);

            if (e && (e.name === 'NotFoundError' || e.name === 'AbortError')) {
                notify('Pemilihan printer Bluetooth dibatalkan.', 'warning');
                return;
            }

            if (e.message === 'SECURE_CONTEXT_REQUIRED') {
                const modalHtml = `
                    <div id="ble-https-warning" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4">
                        <div class="glass-panel p-6 rounded-2xl max-w-md w-full border border-up-amber/40 shadow-2xl">
                            <h4 class="text-base font-bold text-up-amber flex items-center gap-2 mb-3">
                                <span>⚠️</span> Web Bluetooth Butuh HTTPS
                            </h4>
                            <p class="text-xs text-ink-200 leading-relaxed mb-4">
                                Browser (Chrome/Edge) <strong>memblokir koneksi Bluetooth langsung</strong> jika aplikasi diakses lewat protokol HTTP biasa (<code class="text-up-amber bg-black/40 px-1 py-0.5 rounded">${window.location.origin}</code>).
                            </p>
                            <div class="bg-black/30 p-3 rounded-xl border border-white/10 text-xs text-ink-300 space-y-2 mb-4">
                                <p class="font-bold text-white">Cara agar Bluetooth BLE bisa langsung aktif:</p>
                                <p>1. Akses aplikasi via domain <strong>HTTPS</strong> dengan sertifikat SSL.</p>
                                <p>2. Atau di Chrome: buka <code class="text-up-mint">chrome://flags/#unsafely-treat-insecure-origin-as-secure</code>, masukkan URL <code class="text-up-mint">${window.location.origin}</code>, pilih <strong>Enabled</strong>, lalu Relaunch.</p>
                            </div>
                            <div class="flex gap-2">
                                <button onclick="document.getElementById('ble-https-warning')?.remove()" class="flex-1 py-2.5 rounded-xl bg-white/10 hover:bg-white/15 text-white font-bold text-xs">
                                    Tutup
                                </button>
                                <button onclick="document.getElementById('ble-https-warning')?.remove(); window.print()" class="flex-1 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary/80 text-white font-bold text-xs">
                                    Gunakan Web Print
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                const existing = document.getElementById('ble-https-warning');
                if (existing) existing.remove();
                document.body.insertAdjacentHTML('beforeend', modalHtml);
                return;
            }

            if (e.message === 'BLUETOOTH_UNSUPPORTED') {
                notify('Browser ini tidak mendukung Web Bluetooth. Gunakan Google Chrome atau Microsoft Edge.', 'error');
                return;
            }

            notify(`Gagal mencetak: ${e.message || 'Koneksi Bluetooth gagal'}. Pastikan printer menyala & dekat.`, 'error');
        }
    } finally {
        setTimeout(() => {
            isPrintingLock = false;
        }, 500);
    }
}

/* ============================== Alpine Component ============================== */

function thermalPrinter(receiptData) {
    return {
        data: receiptData || null,
        isPrinting: false,
        pairedPrinterName: localStorage.getItem('ute_ble_printer_name') || null,

        init() {
            if (!this.data) {
                this.data = resolveReceiptData();
            }
        },

        async printStruk58(forceNewPairing = false) {
            if (this.isPrinting) return;
            this.isPrinting = true;
            try {
                await printThermalReceipt('58', this.data, forceNewPairing);
                this.pairedPrinterName = localStorage.getItem('ute_ble_printer_name');
            } finally {
                this.isPrinting = false;
            }
        },

        async printFaktur80(forceNewPairing = false) {
            if (this.isPrinting) return;
            this.isPrinting = true;
            try {
                await printThermalReceipt('80', this.data, forceNewPairing);
                this.pairedPrinterName = localStorage.getItem('ute_ble_printer_name');
            } finally {
                this.isPrinting = false;
            }
        },

        disconnectPrinter() {
            resetBlePrinter();
            this.pairedPrinterName = null;
        },

        webPrint() {
            window.print();
        },
    };
}

// Global exports
window.thermalPrinter = thermalPrinter;
window.printThermalReceipt = printThermalReceipt;
window.resetBlePrinter = resetBlePrinter;
window.getPosReceiptData = resolveReceiptData;

// Alpine registration
if (window.Alpine) {
    window.Alpine.data('thermalPrinter', thermalPrinter);
}
document.addEventListener('alpine:init', () => {
    if (window.Alpine) {
        window.Alpine.data('thermalPrinter', thermalPrinter);
    }
});

function buildStruk58(data) { return entriesToBytes(layoutStruk58(data), 32); }
function buildFaktur80(data) { return entriesToBytes(layoutFaktur80(data), 42); }
