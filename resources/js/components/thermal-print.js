/**
 * Thermal Receipt Preview & Print — paper-efficient Opsi B layout.
 * Header alamat+telepon digabung, QR kecil di blok No. TRX, footer padat.
 * EscPos class mirrors PHP EscPosWriter byte-for-byte for 1:1 preview=print parity.
 * Supports Web Bluetooth (auto-reconnect) and CUPS raw printing via backend.
 */
class EscPos {
  /** Font 5x7 minimal untuk coret harga via raster GS v 0. */
  static STRIKE_FONT = {
    '0': ['01110','10001','10011','10101','11001','10001','01110'],
    '1': ['00100','01100','00100','00100','00100','00100','01110'],
    '2': ['01110','10001','00001','00010','00100','01000','11111'],
    '3': ['11110','00001','00001','01110','00001','00001','11110'],
    '4': ['00010','00110','01010','10010','11111','00010','00010'],
    '5': ['11111','10000','11110','00001','00001','10001','01110'],
    '6': ['00110','01000','10000','11110','10001','10001','01110'],
    '7': ['11111','00001','00010','00100','01000','01000','01000'],
    '8': ['01110','10001','10001','01110','10001','10001','01110'],
    '9': ['01110','10001','10001','01111','00001','00010','01100'],
    'R': ['11110','10001','10001','11110','10100','10010','10001'],
    'P': ['11110','10001','10001','11110','10000','10000','10000'],
    'H': ['10001','10001','10001','11111','10001','10001','10001'],
    'A': ['01110','10001','10001','11111','10001','10001','10001'],
    'G': ['01111','10000','10000','10111','10001','10001','01111'],
    'E': ['11111','10000','10000','11110','10000','10000','11111'],
    'T': ['11111','00100','00100','00100','00100','00100','00100'],
    'L': ['10000','10000','10000','10000','10000','10000','11111'],
    'I': ['01110','00100','00100','00100','00100','00100','01110'],
    ':': ['00000','00100','00100','00000','00100','00100','00000'],
    'p': ['11110','10001','10001','11110','10000','10000','10000'],
    '.': ['00000','00000','00000','00000','00000','01100','01100'],
    '-': ['00000','00000','00000','11111','00000','00000','00000'],
    ' ': ['00000','00000','00000','00000','00000','00000','00000'],
  };

  constructor(paperWidth = 58) {
    this.paperWidth = Number(paperWidth) === 80 ? 80 : 58;
    this.compressed = true; // Always small font for paper saving
    this.columns = this.compressed
      ? (this.paperWidth === 80 ? 64 : 42)
      : (this.paperWidth === 80 ? 48 : 32);
    this.buf = [];
    this.init();
  }

  /** Initialize printer — ESC @ + GS ! 1 (compressed) */
  init() {
    this.buf = [0x1b, 0x40]; // ESC @
    if (this.compressed) {
      this.buf.push(0x1d, 0x21, 0x01); // GS ! 1 = half width
    }
    return this;
  }

  // --- Alignment ---
  alignCenter() { this.buf.push(0x1b, 0x61, 0x01); return this; }
  alignLeft() { this.buf.push(0x1b, 0x61, 0x00); return this; }
  alignRight() { this.buf.push(0x1b, 0x61, 0x02); return this; }

  // --- Font styling ---
  bold(on) { this.buf.push(0x1b, 0x45, on ? 1 : 0); return this; }

  /** ESC G n — double-strike, huruf lebih tebal di printer thermal. */
  doubleStrike(on) { this.buf.push(0x1b, 0x47, on ? 1 : 0); return this; }

  /** GS B n — white/black reverse untuk harga lama. */
  reverse(on) { this.buf.push(0x1d, 0x42, on ? 1 : 0); return this; }
  size(n) { this.buf.push(0x1d, 0x21, n); return this; }
  sizeDoubleWidth(on) { this.size(on ? 0x80 : 0x00); return this; }
  sizeDoubleHeight(on) { this.size(on ? 0x10 : 0x00); return this; }

  // --- Content ---
  text(str) {
    this.buf.push(...this.ascii(str), 0x0a); // + LF
    return this;
  }

  rule() { return this.text('-'.repeat(this.columns)); }

  feed(n) { this.buf.push(...Array(n).fill(0x0a)); return this; }

  cut() { this.buf.push(0x1d, 0x56, 0x42, 0x00); return this; } // GS V B 0

  /**
   * Ukuran QR lama disimpan dalam "px" (40/60/80/100/150).
   * Printer memakai module size 2..8, jadi nilai lama > 8 dinormalisasi ke 4
   * (≈48px di preview) agar tetap scan-able namun hemat kertas.
   */
  normalizeQrSize(value) {
    const n = Number(value);
    if (!Number.isFinite(n) || n <= 0) return 4;
    if (n > 8) return 4;
    return Math.max(2, Math.min(8, Math.round(n)));
  }

  /** Ukuran QR efektif struk: dibatasi maksimal modul 3 agar lebih ringkas. */
  effectiveQrSize(value) {
    return this.normalizeQrSize(value);
  }

  /** Model 2 QR Code — mirrors PHP EscPosWriter::qrcode() */
  qrcode(content, size = 4) {
    const d = this.ascii(content);
    if (!d || !d.length) return this;
    const len = d.length + 3;
    const pL = len & 0xff;
    const pH = (len >> 8) & 0xff;
    const modSize = this.normalizeQrSize(size);

    // 1. Model 2: GS ( k 4 0 49 65 50 0
    this.buf.push(0x1d, 0x28, 0x6b, 0x04, 0x00, 0x31, 0x41, 0x32, 0x00);
    // 2. Module size: GS ( k 3 0 49 67 size
    this.buf.push(0x1d, 0x28, 0x6b, 0x03, 0x00, 0x31, 0x43, modSize);
    // 3. Error correction M: GS ( k 3 0 49 69 49
    this.buf.push(0x1d, 0x28, 0x6b, 0x03, 0x00, 0x31, 0x45, 0x31);
    // 4. Store data: GS ( k pL pH 49 80 48 data
    this.buf.push(0x1d, 0x28, 0x6b, pL, pH, 0x31, 0x50, 0x30, ...d);
    // 5. Print: GS ( k 3 0 49 81 48
    this.buf.push(0x1d, 0x28, 0x6b, 0x03, 0x00, 0x31, 0x51, 0x30);
    return this;
  }

  /** ESC B 1 = strikethrough on, ESC B 0 = off */
  textStrikethrough(str) {
    this.buf.push(0x1b, 0x42, 0x01);
    this.buf.push(...this.ascii(str), 0x0a);
    this.buf.push(0x1b, 0x42, 0x00);
    return this;
  }

  // --- Helpers ---
  ascii(str) {
    if (!str) return [];
    return String(str)
      .replace(/[éèêë]/g, 'e')
      .replace(/[áàâä]/g, 'a')
      .replace(/[íìîï]/g, 'i')
      .replace(/[óòôö]/g, 'o')
      .replace(/[úùûü]/g, 'u')
      .replace(/ñ/g, 'n')
      .replace(/ç/g, 'c')
      .replace(/[±×÷]/g, m => ({ '±': '+/-', '×': 'x', '÷': '/' })[m])
      .replace(/[–—]/g, '-')
      .replace(/[’‘]/g, "'")
      .replace(/[“”]/g, '"')
      .replace(/→/g, '->')
      .replace(/[^\x20-\x7E]/g, '')
      .split('').map(c => c.charCodeAt(0));
  }

  money(v) { return 'Rp ' + Number(v || 0).toLocaleString('id-ID'); }

  /** Angka mentah tanpa "Rp" — dipakai kolom kanan baris item (senilai contoh pengaturan). */
  numberOnly(v) { return Number(v || 0).toLocaleString('id-ID'); }

  /** Persentase diskon partai: utamakan nilai settingan, fallback dari diskon/subtotal. */
  diskonPartaiPersen(override, diskon, subtotal) {
    diskon = Number(diskon || 0);
    subtotal = Number(subtotal || 0);
    if (diskon <= 0) return null;
    if (Number.isFinite(Number(override)) && Number(override) > 0) {
      return Math.round(Number(override) * 100) / 100;
    }
    if (subtotal <= 0) return null;
    return Math.round((diskon / subtotal) * 10000) / 100;
  }

  formatPersen(persen) {
    if (persen === null) return '';
    return String(persen).replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1');
  }

  /** Satuan singkat (pcs/box/set) ditampilkan UPPERCASE agar padan dengan contoh struk. */
  unitLabel(satuan) {
    const u = this.clean(satuan || 'PCS');
    return u.length <= 4 ? u.toUpperCase() : u;
  }

  /** Raw text tanpa LF — untuk baris yang disusun dengan escape di tengah (ESC B). */
  raw(str) { this.buf.push(...this.ascii(str)); return this; }

  /** ESC - n — underline (fallback). */
  underline(on) { this.buf.push(0x1b, 0x2d, on ? 1 : 0); return this; }

  /** Cetak teks bercoret tengah memakai raster GS v 0. */
  strikeRaster(text, strikeFrom = 0, strikeLength = null) {
    const chars = this.clean(text).split('');
    if (!chars.length) return this;

    const scaleY = 2;
    const width = chars.length * 6;
    const height = 7 * scaleY;
    const bytesPerRow = Math.ceil(width / 8);
    const rows = [];
    const strikeEnd = strikeLength === null ? chars.length : Math.min(chars.length, strikeFrom + strikeLength);

    for (let gy = 0; gy < 7; gy++) {
      for (let sy = 0; sy < scaleY; sy++) {
        const bits = new Array(bytesPerRow * 8).fill(0);
        chars.forEach((ch, i) => {
          const glyph = EscPos.STRIKE_FONT[ch.toUpperCase()] || EscPos.STRIKE_FONT[' '];
          for (let gx = 0; gx < 5; gx++) {
            if (glyph[gy][gx] === '1') bits[i * 6 + gx] = 1;
          }
        });
        if (gy === 3) {
          for (let i = Math.max(0, strikeFrom); i < strikeEnd; i++) {
            for (let gx = 0; gx < 6; gx++) bits[i * 6 + gx] = 1;
          }
        }
        const row = [];
        for (let b = 0; b < bytesPerRow; b++) {
          let byte = 0;
          for (let bit = 0; bit < 8; bit++) {
            if (bits[b * 8 + bit]) byte |= 1 << (7 - bit);
          }
          row.push(byte);
        }
        rows.push(row);
      }
    }

    this.buf.push(
      0x1d, 0x76, 0x30, 0x00,
      bytesPerRow & 0xff, (bytesPerRow >> 8) & 0xff,
      height & 0xff, (height >> 8) & 0xff,
      ...rows.flat()
    );
    return this;
  }

  /** ESC B n — strikethrough on/off (Epson ESC/POS standar). */
  escB(on) { this.buf.push(0x1b, 0x42, on ? 1 : 0); return this; }

  pad(left, right) {
    left = this.clean(left);
    right = this.clean(right);
    const totalLen = left.length + right.length;

    if (totalLen >= this.columns) {
      const padRight = Math.max(0, this.columns - right.length);
      return left + '\n' + ' '.repeat(padRight) + right;
    }

    const pad = this.columns - totalLen;
    return left + ' '.repeat(pad) + right;
  }

  clean(str) {
    if (!str) return '';
    return String(str)
      .replace(/[éèêë]/g, 'e')
      .replace(/[áàâä]/g, 'a')
      .replace(/[íìîï]/g, 'i')
      .replace(/[óòôö]/g, 'o')
      .replace(/[úùûü]/g, 'u')
      .replace(/ñ/g, 'n')
      .replace(/ç/g, 'c')
      .replace(/[±×÷]/g, m => ({ '±': '+/-', '×': 'x', '÷': '/' })[m])
      .replace(/[–—]/g, '-')
      .replace(/[’‘]/g, "'")
      .replace(/[“”]/g, '"')
      .replace(/→/g, '->')
      .replace(/[^\x20-\x7E]/g, '');
  }

  /** Ringkas garansi agar cukup satu baris di kertas 58mm. */
  compactGaransi(garansi) {
    let s = this.clean(garansi);
    s = s.replace(/\b20\d{2}\b/g, '');
    s = s.replace(/[()]/g, '');
    s = s.replace(/\s+/g, ' ').trim();
    s = s.replace(/[\/ -]+$/, '');
    return s || this.clean(garansi);
  }

  wrap(text) {
    const clean = this.clean(text);
    const lines = [];
    let rest = clean;
    while (rest.length > this.columns) {
      // Cari pemisah terakhir (slash/koma/tanda kurung/spasi) pada batas kolom
      let cut = this.columns;
      for (let i = this.columns; i >= 1; i--) {
        if ('/,-) '.includes(rest[i - 1])) { cut = i; break; }
      }
      lines.push(rest.slice(0, cut).replace(/\s+$/, ''));
      rest = rest.slice(cut);
    }
    if (rest.trim()) lines.push(rest);
    return lines;
  }

  // ============================================================
  // Full receipt builder — mirrors PHP EscPosWriter::receipt()
  // ============================================================
  /**
   * @param data       payload struk (lihat EscPosWriter::receipt())
   * @param paperWidthOverride  lebar kertas aktif (58|80) — menang atas data.paper_width
   */
  buildReceipt(data, paperWidthOverride = null) {
    const {
      items = [],
      total = 0, bayar = 0, kembali = 0,
      no_transaksi = '-', metode_bayar = 'TUNAI', metode = 'TUNAI', tanggal, waktu, kasir, cabang,
      pelanggan, tier, tipe_konsumen, saldo_poin, poin_ditukar,
      diskon_tier, diskon_partai, diskon_partai_persen, diskon_poin, diskon, diskon_persen, diskon_nominal, diskon_box,
      pajak, ppn_persen, ongkir, subtotal, subtotal_retail, hemat_total,
      is_piutang, jatuh_tempo, sisa_piutang, komisi_mitra_nominal,
      no_tiket, jenis_hp, kurir, is_pickup, resi, sumber,
      garansi, label_struk, no_transaksi_asal, goodwill, voucher, komplain,
      footer_text, kebijakan_retur, catatan_kaki,
      nama_toko, slogan, alamat, telepon, website_medsos,
      show_cabang, show_kasir = true, show_pelanggan = true, show_waktu = true,
      show_subtotal_breakdown = true,
      show_qr_retur = true, qr_caption, qr_size = 3, qr_url,
      font_size = 'small',
      strikethrough_mode = 'esc_b',
    } = data;

    // Re-init with correct paper width
    this.paperWidth = Number(paperWidthOverride ?? data.paper_width) === 80 ? 80 : 58;
    this.compressed = font_size === 'small';
    this.columns = this.compressed
      ? (this.paperWidth === 80 ? 64 : 42)
      : (this.paperWidth === 80 ? 48 : 32);
    this.buf = [];
    this.init();

    const qrShown = Boolean(show_qr_retur && no_transaksi && no_transaksi !== '-');
    const tglVisible = Boolean(show_waktu && (tanggal || waktu));

    // ======== HEADER — ringkas: alamat + telepon jadi satu baris ========
    this.alignCenter();
    this.bold(true)
      .size(0x90) // double width + double height
      .text(nama_toko || 'BREMSPACE')
      .size(0x00)
      .bold(false);
    if (slogan) this.text(slogan);

    // Alamat dan nomor HP各自 baris, agar tidak ada tanda '-' menggantung.
    if (alamat) {
      for (const alamatLine of this.wrap(alamat)) {
        this.text(alamatLine);
      }
    }
    if (telepon) this.text(telepon);
    if (show_cabang !== false && show_cabang !== 0 && show_cabang !== '0' && cabang) this.text(cabang);
    this.rule();

    // ======== QR + TRANSAKSI — QR tengah, ukuran penuh, jarak ke bawah ========
    this.alignCenter();
    const qrContent = no_transaksi || '-';
    const tglStr = tanggal || waktu || '';
    const captionStr = qr_caption || '';

    if (qrShown) {
      this.qrcode(qrContent, this.effectiveQrSize(qr_size));
      this.feed(1); // jarak antara QR dan No. TRX
    }
    this.alignLeft();

    if (label_struk) {
      this.alignCenter();
      this.bold(true).text(label_struk).bold(false);
      this.alignLeft();
    }
    this.text(this.pad('No. TRX:', qrContent));
    if (no_transaksi_asal && no_transaksi_asal !== '-') {
      this.text(this.pad('Asal TRX:', no_transaksi_asal));
    }

    if (sumber && !['pos', 'offline'].includes(sumber.toLowerCase())) {
      this.text(this.pad('Pesanan:', sumber.toUpperCase()));
    }

    if (no_tiket) {
      this.bold(true).text(this.pad('No. Tiket:', no_tiket)).bold(false);
    }

    if (jenis_hp) {
      this.text(this.pad('Unit:', jenis_hp));
    }

    if (kurir) {
      const kurirLbl = is_pickup ? 'Ambil:' : 'Kurir:';
      const resiTeks = (resi && resi !== '-') ? ' (' + resi + ')' : '';
      this.text(this.pad(kurirLbl, kurir + resiTeks));
    }

    if (show_kasir !== false && show_kasir !== 0 && show_kasir !== '0' && kasir) {
      this.text(this.pad('Kasir:', kasir));
    }

    if (show_pelanggan !== false && show_pelanggan !== 0 && show_pelanggan !== '0') {
      const pel = String(pelanggan || 'Umum').replace(/^Pelanggan\s+/i, '') || 'Umum';
      this.text(this.pad('Pelanggan:', pel));

      if (tier && tier !== 'Retail' && tier !== 'Umum') {
        const lbl = ['reseller', 'agen'].includes(tipe_konsumen) ? 'Mitra:' : 'Tier:';
        this.bold(true).text(this.pad(lbl, tier)).bold(false);
      }
    }

    // Web sengaja tidak dicetak; cukup tanggal bila diaktifkan.
    const metaLine = tglVisible ? 'Tgl: ' + tglStr : '';
    if (metaLine) this.text(metaLine);

    // ======== ITEMS ========
    let opsiBItem = false;
    // Diskon partai = persentase dari subtotal sebelum potongan partai.
    const diskonPartaiVal = Number(diskon_partai || 0);
    const subtotalSebelumPartai = Number(subtotal || 0);
    const faktorPartai = diskonPartaiVal > 0 && subtotalSebelumPartai > 0
      ? Math.max(0, Math.min(1, 1 - (diskonPartaiVal / subtotalSebelumPartai)))
      : null;

    for (const item of items) {
      const nama = item.nama || 'Item';
      for (const line of this.wrap(nama)) {
        this.bold(true).text(line).bold(false);
      }
      if (item.breakdown) {
        this.text('  ' + item.breakdown);
      }

      const qtySatuan = (item.qty || 1) + ' ' + this.unitLabel(item.satuan);
      const harga = this.money(item.harga);
      const rightVal = this.numberOnly(item.subtotal);
      const hargaRetail = Number(item.harga_retail ?? 0);
      const hargaJual = Number(item.harga ?? 0);
      const unitLbl = this.unitLabel(item.satuan);

      if (hargaRetail > hargaJual && ['esc_b', 'reverse'].includes(strikethrough_mode)) {
        // Opsi B: satu baris harga normal dicoret + harga diskon.
        opsiBItem = true;
        const normal = this.money(hargaRetail);
        const disc = this.money(hargaJual);

        // Seluruh baris raster agar label + nominal tetap searis.
        // Format hemat: satu baris "qty x <harga normal dicoret> harga diskon = subtotal".
        const ringkas = `${qtySatuan} x ${normal} ${disc} = ${rightVal}`;
        this.strikeRaster(ringkas, (qtySatuan + ' x ').length, normal.length);
        this.feed(1).alignLeft();
      } else if (hargaRetail > hargaJual) {
        this.text(this.pad(`${qtySatuan} x (${this.money(hargaRetail)}) -> ${this.money(hargaJual)}`, rightVal));
      } else {
        this.text(this.pad(`${qtySatuan} x ${harga}`, rightVal));
      }

      // Harga per PCS setelah diskon partai.
      if (faktorPartai !== null && hargaJual > 0) {
        const hargaPartai = Math.round(hargaJual * faktorPartai);
        this.text('Harga Partai/' + unitLbl + ': ' + this.money(hargaPartai));
      }

      if (item.catatan) {
        for (const ln of this.wrap('  * ' + item.catatan)) {
          this.text(ln);
        }
      }
    }

    // ======== SUBTOTAL BREAKDOWN & TOTALS ========
    // Opsi B: tampilkan potongan agregat dengan label yang jelas.
    if (opsiBItem) {
      if (Number(diskon_box || 0) > 0) {
        this.text(this.pad('Potongan Kemasan:', '-' + this.money(diskon_box)));
      }
      if (Number(diskon_tier || 0) > 0) {
        this.text(this.pad('Potongan Member (Tier):', '-' + this.money(diskon_tier)));
      }
    }
    // Opsi B sudah memuat subtotal dan Potongan Harga di baris item.
    if (!opsiBItem) {
      if (show_subtotal_breakdown !== false && show_subtotal_breakdown !== 0 && show_subtotal_breakdown !== '0' && diskon_tier && Number(diskon_tier) > 0) {
        const subRetail = Number(subtotal_retail ?? 0) || (Number(subtotal ?? 0) + Number(diskon_tier));
        this.text(this.pad('Subtotal Retail:', this.money(subRetail)));

        const tierLbl = (tier && tier !== 'Retail')
          ? 'Pot ' + tier + ':'
          : (['reseller', 'agen'].includes(tipe_konsumen) ? 'Pot Mitra:' : 'Pot Tier:');
        this.text(this.pad(tierLbl, '-' + this.money(diskon_tier)));
        this.text(this.pad('Subtotal:', this.money(subtotal ?? total)));
      } else {
        this.text(this.pad('Subtotal:', this.money(subtotal ?? total)));
      }

      if (diskon_box && Number(diskon_box) > 0) {
        this.text(this.pad('Diskon Box/Kemasan:', '-' + this.money(diskon_box)));
      }
    }

    if (diskon_partai && Number(diskon_partai) > 0) {
      const persenPartai = this.diskonPartaiPersen(diskon_partai_persen, diskon_partai, subtotal);
      const labelPartai = 'Diskon Partai:'
        + (persenPartai !== null ? ' ' + this.formatPersen(persenPartai) + '%' : '');
      this.text(this.pad(labelPartai, '-' + this.money(diskon_partai)));
    }
    if (diskon_poin && Number(diskon_poin) > 0) {
      this.text(this.pad('Tukar Poin (' + (poin_ditukar || 0) + 'pts):', '-' + this.money(diskon_poin)));
    }
    if (diskon && Number(diskon) > 0) {
      const lbl = (diskon_persen && Number(diskon_persen) > 0)
        ? 'Diskon (' + diskon_persen + '%):'
        : 'Diskon Kasir:';
      this.text(this.pad(lbl, '-' + this.money(diskon)));
    }
    if (pajak && Number(pajak) > 0) {
      const ppnLbl = ppn_persen ? 'PPN (' + ppn_persen + '%):' : 'PPN:';
      this.text(this.pad(ppnLbl, '+' + this.money(pajak)));
    }
    if (ongkir && Number(ongkir) > 0) {
      this.text(this.pad('Ongkir:', '+' + this.money(ongkir)));
    }

    // TOTAL lebih besar (double width), tetap satu baris.
    this.bold(true)
      .sizeDoubleWidth(true)
      .text('TOTAL: ' + this.money(total))
      .sizeDoubleWidth(false)
      .bold(false);

    if (is_piutang) {
      this.text(this.pad('Status:', 'BELUM LUNAS (PIUTANG)'));
      if (jatuh_tempo) this.text(this.pad('Jatuh Tempo:', jatuh_tempo));
      this.text(this.pad('Sisa Tag:', this.money(sisa_piutang ?? total)));
    } else {
      const caraBayar = String(metode_bayar || metode || 'TUNAI').toUpperCase();
      this.text(this.pad(`BAYAR (${caraBayar}):`, this.money(bayar || total)));
      this.text(this.pad('KEMBALI:', this.money(kembali ?? 0)));
    }

    if (saldo_poin !== undefined) {
      this.text(this.pad('Sisa Saldo Poin:', this.numberOnly(saldo_poin) + ' pts'));
    }

    if (hemat_total && Number(hemat_total) > 0) {
      this.bold(true).text(this.pad('HEMAT:', this.money(hemat_total))).bold(false);
    }

    if (komisi_mitra_nominal && Number(komisi_mitra_nominal) > 0) {
      this.text(this.pad('Komisi:', '+' + this.money(komisi_mitra_nominal) + ' (Saldo)'));
    }

    if (goodwill) {
      this.text(this.pad('Goodwill:', String(goodwill)));
    }
    if (voucher) {
      this.text(this.pad('Voucher:', String(voucher)));
    }
    if (komplain) {
      for (const ln of this.wrap(String(komplain))) {
        this.text(ln);
      }
    }

    this.rule();

    // ======== FOOTER — Opsi B: cukup garansi satu baris di bawah ========
    this.alignCenter();
    if (garansi) {
      for (const ln of this.wrap('Garansi: ' + this.compactGaransi(garansi))) {
        this.text(ln);
      }
    }

    if (is_piutang) {
      this.feed(1);
      this.text('"Barang diterima & berjanji melunasi sblm jatuh tempo"');
      this.feed(1);
      this.text('Penerima,              Kasir,');
      this.feed(2);
      this.text('(............)        (............)');
    }

    // Margin bawah dipepet: potong langsung setelah baris terakhir.
    this.feed(0).cut();

    return new Uint8Array(this.buf);
  }

  /** Alias for buildReceipt */
  build(data) {
    return this.buildReceipt(data);
  }

  /** Render HTML preview that visually matches the ESC/POS output 1:1 */
  renderPreviewHtml(data) {
    return this.buildPreviewHtml(data);
  }

  /** Build HTML preview matching the exact paper layout */
  /**
   * Render HTML preview yang mengikuti buildReceipt() (ESC/POS bytes) persis.
   * Preview dan kertas sekarang memakai urutan + format yang sama.
   */
  buildPreviewHtml(data, paperWidthOverride = null) {
    const {
      items = [], total = 0, bayar = 0, kembali = 0,
      no_transaksi = '-', metode_bayar = 'TUNAI', metode = 'TUNAI', tanggal, waktu, kasir, cabang,
      pelanggan, tier, tipe_konsumen, saldo_poin, poin_ditukar,
      diskon_tier, diskon_partai, diskon_partai_persen, diskon_poin, diskon, diskon_persen, diskon_nominal, diskon_box,
      pajak, ppn_persen, ongkir, subtotal, subtotal_retail, hemat_total,
      is_piutang, jatuh_tempo, sisa_piutang, komisi_mitra_nominal,
      no_tiket, jenis_hp, kurir, is_pickup, resi, sumber,
      garansi, label_struk, no_transaksi_asal, goodwill, voucher, komplain,
      footer_text, kebijakan_retur, catatan_kaki,
      nama_toko, slogan, alamat, telepon, website_medsos,
      show_cabang, show_kasir = true, show_pelanggan = true, show_waktu = true,
      show_subtotal_breakdown = true,
      show_qr_retur = true, qr_caption, qr_url, qr_svg,
      paper_width = 58, font_size = 'small', strikethrough_mode = 'esc_b',
    } = data;

    const widthNum = Number(paperWidthOverride ?? paper_width) === 80 ? 80 : 58;
    const paperClass = widthNum === 80 ? 'paper-80' : 'paper-58';
    const cols = (font_size === 'small') ? (widthNum === 80 ? 64 : 42) : (widthNum === 80 ? 48 : 32);

    const money = (v) => this.money(v);
    const numberOnly = (v) => this.numberOnly(v);
    const unitLabel = (s) => this.unitLabel(s);
    const clean = (v) => this.clean(v);
    const esc = (v) => String(clean(v))
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const wrap = (v) => {
      const saved = this.columns;
      this.columns = cols;
      const out = this.wrap(v);
      this.columns = saved;
      return out;
    };
    const row = (left, right) => {
      const saved = this.columns;
      this.columns = cols;
      const out = this.pad(clean(left), clean(right));
      this.columns = saved;
      return out;
    };
    const rule = '-'.repeat(cols);
    const center = (v, style = '') => '<div style="text-align:center;white-space:pre-wrap;width:100%;' + style + '">' + v + '</div>';
    const line = (v = '', style = '') => '<div style="white-space:pre-wrap;width:100%;' + style + '">' + esc(v) + '</div>';
    const lineHtml = (v, style = '') => '<div style="white-space:pre-wrap;width:100%;' + style + '">' + v + '</div>';

    const qrShown = Boolean(show_qr_retur && no_transaksi && no_transaksi !== '-');
    const tglVisible = Boolean(show_waktu && (tanggal || waktu));
    const qrContent = no_transaksi || '-';
    const tglStr = tanggal || waktu || '';
    const captionStr = qr_caption || '';
    const renderedQr = qr_svg || '';

    const fallbackQr = `<svg width="48" height="48" viewBox="0 0 100 100" style="border:1px solid #d1d5db;background:#fff;">
      <rect width="100" height="100" fill="white"/>
      <rect x="10" y="10" width="25" height="25" fill="black"/>
      <rect x="15" y="15" width="15" height="15" fill="white"/>
      <rect x="19" y="19" width="7" height="7" fill="black"/>
      <rect x="65" y="10" width="25" height="25" fill="black"/>
      <rect x="70" y="15" width="15" height="15" fill="white"/>
      <rect x="74" y="19" width="7" height="7" fill="black"/>
      <rect x="10" y="65" width="25" height="25" fill="black"/>
      <rect x="15" y="70" width="15" height="15" fill="white"/>
      <rect x="19" y="74" width="7" height="7" fill="black"/>
      <rect x="45" y="45" width="10" height="10" fill="black"/>
      <rect x="40" y="20" width="6" height="15" fill="black"/>
      <rect x="60" y="45" width="8" height="8" fill="black"/>
      <rect x="45" y="65" width="12" height="6" fill="black"/>
      <rect x="65" y="65" width="10" height="15" fill="black"/>
    </svg>`;

    let html = '<div class="thermal-receipt-doc ' + paperClass + '" style="width:100%;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:' + (widthNum === 80 ? '8.5px' : '8px') + ';line-height:1.2;color:#000;background:#fff;">';

    // HEADER — sama dengan buildReceipt()
    html += center('<div style="font-size:15px;font-weight:900;letter-spacing:.04em;">' + esc(nama_toko || 'BREMSPACE') + '</div>');
    if (slogan) html += center('<div>' + esc(slogan) + '</div>');

    if (alamat) {
      for (const alamatLine of wrap(alamat)) {
        html += center('<div>' + esc(alamatLine) + '</div>');
      }
    }
    if (telepon) html += center('<div>' + esc(telepon) + '</div>');
    if (show_cabang !== false && show_cabang !== 0 && show_cabang !== '0' && cabang) {
      html += center('<div>' + esc(cabang) + '</div>');
    }
    html += line(rule);

    // QR + TRANSAKSI — QR dipusatkan; satu baris kosong setelah QR mengikuti buildReceipt().
    if (qrShown) {
      html += center('<div style="display:inline-block;line-height:0;margin-bottom:10px;">' + (renderedQr || fallbackQr) + '</div>');
    }

    // TRANSAKSI — sama dengan buildReceipt()
    if (label_struk) html += center('<div style="font-weight:800;">' + esc(label_struk) + '</div>');
    html += line(row('No. TRX:', qrContent));
    if (no_transaksi_asal && no_transaksi_asal !== '-') html += line(row('Asal TRX:', no_transaksi_asal));
    if (sumber && !['pos', 'offline'].includes(String(sumber).toLowerCase())) html += line(row('Pesanan:', String(sumber).toUpperCase()));
    if (no_tiket) html += line(row('No. Tiket:', no_tiket));
    if (jenis_hp) html += line(row('Unit:', jenis_hp));
    if (kurir) {
      const kurirLbl = is_pickup ? 'Ambil:' : 'Kurir:';
      const resiTeks = (resi && resi !== '-') ? ' (' + resi + ')' : '';
      html += line(row(kurirLbl, kurir + resiTeks));
    }
    if (show_kasir !== false && show_kasir !== 0 && show_kasir !== '0' && kasir) html += line(row('Kasir:', kasir));
    if (show_pelanggan !== false && show_pelanggan !== 0 && show_pelanggan !== '0') {
      html += line(row('Pelanggan:', String(pelanggan || 'Umum').replace(/^Pelanggan\s+/i, '') || 'Umum'));
      if (tier && tier !== 'Retail' && tier !== 'Umum') {
        const lbl = ['reseller', 'agen'].includes(String(tipe_konsumen || '').toLowerCase()) ? 'Mitra:' : 'Tier:';
        html += line(row(lbl, tier));
      }
    }

    const metaLineText = tglVisible ? 'Tgl: ' + tglStr : '';
    if (metaLineText) html += line(metaLineText);

    // ITEMS — sama dengan buildReceipt()
    let opsiBItem = false;
    const diskonPartaiVal = Number(diskon_partai || 0);
    const subtotalSebelumPartai = Number(subtotal || 0);
    const faktorPartai = diskonPartaiVal > 0 && subtotalSebelumPartai > 0
      ? Math.max(0, Math.min(1, 1 - (diskonPartaiVal / subtotalSebelumPartai)))
      : null;

    for (const item of items) {
      const nama = item.nama || 'Item';
      for (const namaLine of wrap(nama)) html += line(namaLine, 'font-weight:800;');
      if (item.breakdown) html += line('  ' + item.breakdown, 'color:#4b5563;');

      const qtySatuan = (item.qty || 1) + ' ' + unitLabel(item.satuan);
      const rightVal = numberOnly(item.subtotal);
      const hargaRetail = Number(item.harga_retail ?? 0);
      const hargaJual = Number(item.harga ?? 0);
      const unitLbl = unitLabel(item.satuan);

      if (hargaRetail > hargaJual && ['esc_b', 'reverse'].includes(strikethrough_mode)) {
        // Opsi B — Harga normal dicoret + harga diskon dalam satu baris.
        opsiBItem = true;
        const normal = money(hargaRetail);
        const disc = money(hargaJual);

        // Format hemat: qty x <harga normal dicoret> harga diskon = subtotal.
        html += lineHtml(esc(qtySatuan + ' x ') + '<span style="text-decoration:line-through;">' + esc(normal) + '</span> '
            + esc(disc) + ' = ' + esc(rightVal));
      } else if (hargaRetail > hargaJual) {
        html += line(row(qtySatuan + ' x (' + money(hargaRetail) + ') -> ' + money(hargaJual), rightVal));
      } else {
        html += line(row(qtySatuan + ' x ' + money(hargaJual), rightVal));
      }

      if (faktorPartai !== null && hargaJual > 0) {
        const hargaPartai = Math.round(hargaJual * faktorPartai);
        html += line('Harga Partai/' + unitLbl + ': ' + money(hargaPartai));
      }

      if (item.catatan) {
        for (const catLine of wrap('  * ' + item.catatan)) html += line(catLine);
      }
    }

    // TOTAL & RINCIAN — Opsi B memuat subtotal di baris item + potongan agregat.
    if (opsiBItem) {
      if (Number(diskon_box || 0) > 0) {
        html += line(row('Potongan Kemasan:', '-' + money(diskon_box)));
      }
      if (Number(diskon_tier || 0) > 0) {
        html += line(row('Potongan Member (Tier):', '-' + money(diskon_tier)));
      }
    }
    if (!opsiBItem) {
      const showBreakdown = show_subtotal_breakdown !== false && show_subtotal_breakdown !== 0 && show_subtotal_breakdown !== '0';
      if (showBreakdown && diskon_tier && Number(diskon_tier) > 0) {
        const subRetail = Number(subtotal_retail ?? 0) || (Number(subtotal ?? 0) + Number(diskon_tier));
        html += line(row('Subtotal Retail:', money(subRetail)));
        const tierLbl = (tier && tier !== 'Retail')
          ? 'Pot ' + tier + ':'
          : (['reseller', 'agen'].includes(String(tipe_konsumen || '').toLowerCase()) ? 'Pot Mitra:' : 'Pot Tier:');
        html += line(row(tierLbl, '-' + money(diskon_tier)));
        html += line(row('Subtotal:', money(subtotal ?? total)));
      } else {
        html += line(row('Subtotal:', money(subtotal ?? total)));
      }
      if (diskon_box && Number(diskon_box) > 0) html += line(row('Diskon Box/Kemasan:', '-' + money(diskon_box)));
    }
    if (diskon_partai && Number(diskon_partai) > 0) {
      const persenPartai = this.diskonPartaiPersen(diskon_partai_persen, diskon_partai, subtotal);
      const labelPartai = 'Diskon Partai:'
        + (persenPartai !== null ? ' ' + this.formatPersen(persenPartai) + '%' : '');
      html += line(row(labelPartai, '-' + money(diskon_partai)));
    }
    if (diskon_poin && Number(diskon_poin) > 0) html += line(row('Tukar Poin (' + (poin_ditukar || 0) + 'pts):', '-' + money(diskon_poin)));
    if (diskon && Number(diskon) > 0) {
      const lbl = (diskon_persen && Number(diskon_persen) > 0)
        ? 'Diskon (' + diskon_persen + '%):'
        : 'Diskon Kasir:';
      html += line(row(lbl, '-' + money(diskon)));
    }
    if (pajak && Number(pajak) > 0) {
      const ppnLbl = ppn_persen ? 'PPN (' + ppn_persen + '%):' : 'PPN:';
      html += line(row(ppnLbl, '+' + money(pajak)));
    }
    if (ongkir && Number(ongkir) > 0) html += line(row('Ongkir:', '+' + money(ongkir)));

    html += center('<div style="font-size:14px;font-weight:900;">' + esc('TOTAL: ' + money(total)) + '</div>');

    if (is_piutang) {
      html += line(row('Status:', 'BELUM LUNAS (PIUTANG)'));
      if (jatuh_tempo) html += line(row('Jatuh Tempo:', jatuh_tempo));
      html += line(row('Sisa Tag:', money(sisa_piutang ?? total)));
    } else {
      const caraBayar = String(metode_bayar || metode || 'TUNAI').toUpperCase();
      html += line(row('BAYAR (' + caraBayar + '):', money(bayar || total)));
      html += line(row('KEMBALI:', money(kembali ?? 0)));
    }

    if (saldo_poin !== undefined) html += line(row('Sisa Saldo Poin:', numberOnly(saldo_poin) + ' pts'));
    if (hemat_total && Number(hemat_total) > 0) html += line(row('HEMAT:', money(hemat_total)));
    if (komisi_mitra_nominal && Number(komisi_mitra_nominal) > 0) html += line(row('Komisi:', '+' + money(komisi_mitra_nominal) + ' (Saldo)'));
    if (goodwill) html += line(row('Goodwill:', String(goodwill)));
    if (voucher) html += line(row('Voucher:', String(voucher)));
    if (komplain) {
      for (const komplainLine of wrap(String(komplain))) html += line(komplainLine);
    }

    html += line(rule);

    // FOOTER — Opsi B: cukup garansi satu baris, sama dengan buildReceipt()
    if (garansi) {
      for (const kakiLine of wrap('Garansi: ' + this.compactGaransi(garansi))) {
        html += center('<div>' + esc(kakiLine) + '</div>');
      }
    }
    if (is_piutang) {
      html += center('<div style="margin-top:8px;">&quot;Barang diterima &amp; berjanji melunasi sblm jatuh tempo&quot;</div>');
      html += '<div style="display:flex;justify-content:space-between;width:100%;margin-top:8px;padding:0 8px;"><div>Penerima,</div><div>Kasir,</div></div>';
      html += '<div style="display:flex;justify-content:space-between;width:100%;margin-top:16px;padding:0 8px;"><div>(............)</div><div>(............)</div></div>';
    }

    html += '</div>';
    return html;
  }
}

/** Thermal printer manager — Web Bluetooth with persistent device & silent auto-reconnect */
class ThermalPrinter {
  constructor() {
    this.device = null;
    this.characteristic = null;
    this.reconnectTimer = null;
    this.isConnecting = false;
    this.onStatusChange = null;
    this.onError = null;
    this.status = 'unpaired';

    // Candidate thermal BLE service UUIDs
    this.serviceUuids = [
      '000018f0-0000-1000-8000-00805f9b34fb', // Standard POS
      'e7810a71-73ae-499d-8c15-faa9aef0c3f2', // PosPrinter / Goojprt
      '49535343-fe7d-4ae5-8fa9-9fafd205e455', // ISSC transparent UART
      '0000ff00-0000-1000-8000-00805f9b34fb', // ESC/POS vendor
      '0000fee7-0000-1000-8000-00805f9b34fb', // Tencent/WeChat POS
    ];

    // Attempt silent auto-reconnect on boot
    this.loadSavedDevice();
  }

  /** Load previously paired device silently from localStorage via getDevices() */
  async loadSavedDevice() {
    try {
      const saved = localStorage.getItem('thermal_printer_device');
      if (!saved) {
        this.setStatus('unpaired');
        return;
      }

      const { name, id } = JSON.parse(saved);
      if (navigator.bluetooth && typeof navigator.bluetooth.getDevices === 'function') {
        const devices = await navigator.bluetooth.getDevices();
        const matched = devices.find(d => d.id === id || d.name === name);
        if (matched) {
          this.device = matched;
          this.device.addEventListener('gattserverdisconnected', () => this.scheduleReconnect());
          await this.connectGatt();
          return;
        }
      }

      // Saved info available even if silent auto-reconnect awaits user action
      this.setStatus('saved');
    } catch (e) {
      console.warn('Silent BLE reconnect skipped:', e);
      this.setStatus('unpaired');
    }
  }

  /** Connect to GATT server & find writable characteristic */
  async connectGatt() {
    if (!this.device?.gatt) return;
    if (this.device.gatt.connected && this.characteristic) {
      this.setStatus('connected');
      return;
    }

    this.isConnecting = true;
    this.setStatus('connecting');

    try {
      const server = await this.device.gatt.connect();
      this.characteristic = await this.discoverWritableCharacteristic(server);

      localStorage.setItem('thermal_printer_device', JSON.stringify({
        name: this.device.name,
        id: this.device.id,
      }));
      localStorage.setItem('ute_ble_printer_name', this.device.name || 'Bluetooth Printer');

      this.setStatus('connected');
    } catch (e) {
      console.error('GATT connection error:', e);
      this.setStatus('error');
      this.onError?.(e);
      throw e;
    } finally {
      this.isConnecting = false;
    }
  }

  /** Discover writable characteristic across known thermal printer services */
  async discoverWritableCharacteristic(server) {
    for (const sUuid of this.serviceUuids) {
      try {
        const service = await server.getPrimaryService(sUuid);
        const characteristics = await service.getCharacteristics();
        for (const c of characteristics) {
          if (c.properties.write || c.properties.writeWithoutResponse) {
            return c;
          }
        }
      } catch (e) {
        // Continue searching other services
      }
    }

    // Generic fallback: check all primary services
    try {
      const services = await server.getPrimaryServices();
      for (const service of services) {
        const chars = await service.getCharacteristics();
        for (const c of chars) {
          if (c.properties.write || c.properties.writeWithoutResponse) {
            return c;
          }
        }
      }
    } catch (e) {
      // Ignore
    }

    throw new Error('Tidak ditemukan karakteristik write pada printer thermal ini');
  }

  /** Connect to printer. defaultName -> silent match; pairNew -> always show picker. */
  async connect(pairNew = false, defaultName = null, silent = false) {
    if (this.isConnecting) return;
    if (!pairNew && this.device?.gatt?.connected && this.characteristic) {
      return this.setStatus('connected');
    }

    if (!navigator.bluetooth) {
      throw new Error('Web Bluetooth tidak didukung browser ini (gunakan Chrome/Edge)');
    }

    this.isConnecting = true;
    this.setStatus('connecting');

    try {
      if (pairNew || !this.device) {
        // Cari printer default yang sudah diizinkan (tanpa dialog)
        if (!pairNew && defaultName && typeof navigator.bluetooth.getDevices === 'function') {
          const devices = await navigator.bluetooth.getDevices();
          const matched = devices.find(d => d.name === defaultName || d.id === defaultName);
          if (matched) {
            this.device = matched;
            this.device.addEventListener('gattserverdisconnected', () => this.scheduleReconnect());
            await this.connectGatt();
            return;
          }
        }

        if (silent) {
          throw new Error('Printer default belum tersedia');
        }

        const filters = defaultName
          ? [{ name: defaultName }, { namePrefix: defaultName }]
          : [
              { services: ['000018f0-0000-1000-8000-00805f9b34fb'] },
              { namePrefix: 'POS' },
              { namePrefix: 'Thermal' },
              { namePrefix: 'Printer' },
              { namePrefix: 'MPT' },
              { namePrefix: 'RP' },
              { namePrefix: 'PTP' },
              { namePrefix: 'InnerPrinter' },
              { namePrefix: 'BlueTooth' },
              { namePrefix: 'MTP' },
            ];

        this.device = await navigator.bluetooth.requestDevice({
          filters,
          optionalServices: this.serviceUuids,
        });

        this.device.addEventListener('gattserverdisconnected', () => this.scheduleReconnect());
      }

      await this.connectGatt();
    } catch (e) {
      this.setStatus('error');
      this.onError?.(e);
      throw e;
    } finally {
      this.isConnecting = false;
    }
  }

  scheduleReconnect() {
    if (this.reconnectTimer) clearTimeout(this.reconnectTimer);
    this.characteristic = null;
    this.setStatus('disconnected');
    // Try reconnecting after 3 seconds
    this.reconnectTimer = setTimeout(async () => {
      if (this.device) {
        try {
          await this.connectGatt();
        } catch (e) {
          // Reconnect will re-trigger on next print attempt
        }
      }
    }, 3000);
  }

  /** Print raw ESC/POS bytes with safe MTU chunking */
  async print(bytes) {
    if (!this.characteristic) {
      await this.connect();
    }
    if (!this.characteristic) {
      throw new Error('Printer tidak tersambung');
    }

    const payload = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
    const mtu = 64; // Safe BLE write chunk
    for (let i = 0; i < payload.length; i += mtu) {
      const chunk = payload.slice(i, i + mtu);
      if (this.characteristic.properties.writeWithoutResponse) {
        await this.characteristic.writeValueWithoutResponse(chunk);
      } else {
        await this.characteristic.writeValue(chunk);
      }
      // Pacing delay to avoid choking thermal buffer
      await new Promise(r => setTimeout(r, 15));
    }
  }

  setStatus(status) {
    this.status = status;
    this.onStatusChange?.(status);
  }

  getStatus() { return this.status; }

  disconnect() {
    if (this.reconnectTimer) clearTimeout(this.reconnectTimer);
    if (this.device?.gatt?.connected) this.device.gatt.disconnect();
    this.device = null;
    this.characteristic = null;
    localStorage.removeItem('thermal_printer_device');
    localStorage.removeItem('ute_ble_printer_name');
    this.setStatus('unpaired');
  }
}

// Export for global use
window.EscPos = EscPos;
window.ThermalPrinter = ThermalPrinter;

/**
 * Adapter cetak struk untuk POS offline (dipanggil pos-offline-engine.js).
 * Menerima payload outbox (total_akhir, metode_bayar, ...) lalu mencetak
 * lewat Web Bluetooth; fallback ke dialog cetak browser bila BLE tak tersedia.
 */
window.UteThermalPrint = {
  async printReceipt(data = {}) {
    const paperWidth = Number(localStorage.getItem('ute_pos_paper_size')) === 80 ? 80 : 58;
    const payload = {
      ...data,
      total: data.total ?? data.total_akhir ?? 0,
      bayar: data.bayar ?? data.total_akhir ?? data.total ?? 0,
      kembali: data.kembali ?? 0,
      paper_width: paperWidth,
      font_size: 'small',
    };

    const esc = new EscPos(paperWidth);
    const bytes = esc.buildReceipt(payload, paperWidth);

    try {
      const printer = new ThermalPrinter();
      await printer.print(bytes);
      return true;
    } catch (e) {
      console.warn('UteThermalPrint: BLE tidak tersedia, fallback browser print', e);
      const win = window.open('', '_blank');
      if (!win) return false;
      win.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8"><title>Struk</title>
        <style>@media print{@page{margin:0 size:${paperWidth}mm auto}body{margin:0;padding:1mm;background:#fff}}</style>
        </head><body>${esc.buildPreviewHtml(payload, paperWidth)}</body></html>`);
      win.document.close();
      win.focus();
      setTimeout(() => win.print(), 250);
      return true;
    }
  },
};

/**
 * Alpine component for thermal receipt modal.
 * Merges with parent component via spread operator: ...thermalPrinter(config)
 */
function thermalPrinter(config = {}) {
  const printer = new ThermalPrinter();
  const savedBluetoothMac = config.thermal_bluetooth_mac || '';
  const savedCupsPrinter = config.thermal_printer_name || '';
  const autoPrintEnabled = Boolean(config.thermal_auto_print || config.auto_print || false);
  const storedBleName = localStorage.getItem('ute_ble_printer_name');

  let initialPairedName = null;
  if (storedBleName) {
    initialPairedName = storedBleName;
  } else if (savedBluetoothMac) {
    initialPairedName = `BT: ${savedBluetoothMac}`;
  } else if (savedCupsPrinter) {
    initialPairedName = `CUPS: ${savedCupsPrinter}`;
  }

  return {
    data: { ...config },
    paperSize: '58',
    isPrinting: false,
    autoDirectPrint: localStorage.getItem('ute_pos_auto_direct_print') === 'true' || autoPrintEnabled,
    pairedPrinterName: initialPairedName,
    printer: printer,
    serialPort: null,
    pairedSerialName: localStorage.getItem('ute_serial_printer_name') || null,
    usbDevice: null,
    pairedUsbName: localStorage.getItem('ute_usb_printer_name') || null,
    usbInterfaceNumber: null,
    usbEndpointOut: null,

    init() {
      const templateDefault = this.data.default_width?.includes('80') ? '80' : '58';
      const lastTemplate = localStorage.getItem('ute_pos_template_width');
      if (lastTemplate !== templateDefault) {
        localStorage.setItem('ute_pos_template_width', templateDefault);
        localStorage.setItem('ute_pos_paper_size', templateDefault);
        this.paperSize = templateDefault;
      } else {
        this.paperSize = localStorage.getItem('ute_pos_paper_size') || templateDefault;
      }

      printer.onStatusChange = (status) => {
        if (status === 'connected') {
          this.pairedPrinterName = this.printer.device?.name || localStorage.getItem('ute_ble_printer_name') || 'Bluetooth Printer';
        } else if (status === 'disconnected') {
          const cached = localStorage.getItem('ute_ble_printer_name') || (savedBluetoothMac ? `BT: ${savedBluetoothMac}` : null);
          this.pairedPrinterName = cached ? `${cached} (terputus)` : null;
        } else if (status === 'unpaired') {
          this.pairedPrinterName = null;
        }
      };

      // Auto-detect previously granted USB devices (tanpa dialog)
      if (navigator.usb && typeof navigator.usb.getDevices === 'function') {
        navigator.usb.getDevices().then((devices) => {
          if (devices && devices.length > 0) {
            this.usbDevice = devices[0];
            if (!this.pairedUsbName) {
              this.pairedUsbName = devices[0].productName || 'USB Printer';
            }
          }
        }).catch(() => {});
      }

      // Auto-detect previously granted serial ports (tanpa dialog)
      if (navigator.serial && typeof navigator.serial.getPorts === 'function') {
        navigator.serial.getPorts().then((ports) => {
          if (ports && ports.length > 0) {
            this.serialPort = ports[0];
            if (!this.pairedSerialName) {
              this.pairedSerialName = 'Serial Printer';
            }
          }
        }).catch(() => {});
      }

      // Hubungkan diam-diam ke printer default dari pengaturan
      const defaultName = this.data.thermal_printer_name || savedBluetoothMac;
      if (defaultName && navigator.bluetooth) {
        this.printer.connect(false, defaultName, true).then(() => {
          this.pairedPrinterName = this.printer.device?.name || defaultName;
        }).catch(() => {});
      }

      // Auto-trigger background print on checkout if configured
      this.$nextTick(() => {
        if (this.autoDirectPrint) {
          setTimeout(() => {
            this.directPrint(this.paperSize);
          }, 350);
        }
      });
    },

    async connectUsb() {
      if (!navigator.usb) {
        this.showToast('Web USB tidak didukung browser ini (gunakan Chrome/Edge & HTTPS/localhost)');
        return;
      }
      try {
        const device = await navigator.usb.requestDevice({ filters: [] });
        await device.open();
        if (device.configuration === null) {
          await device.selectConfiguration(1);
        }
        const iface = device.configuration.interfaces.find((i) => {
          const alt = i.alternates[0];
          return alt && alt.endpoints.some((e) => e.direction === 'out' && (e.type === 'bulk' || e.type === 'interrupt'));
        });
        if (!iface) throw new Error('Interface OUT tidak ditemukan');
        const alt = iface.alternates[0];
        this.usbInterfaceNumber = iface.interfaceNumber;
        this.usbEndpointOut = alt.endpoints.find((e) => e.direction === 'out' && (e.type === 'bulk' || e.type === 'interrupt'));
        await device.claimInterface(iface.interfaceNumber);
        this.usbDevice = device;
        this.pairedUsbName = device.productName || 'USB Printer';
        localStorage.setItem('ute_usb_printer_name', this.pairedUsbName);
        this.showToast('Printer USB terhubung');
      } catch (e) {
        console.warn('USB connect failed:', e);
        this.showToast('Gagal sambung USB: ' + (e && e.message ? e.message : e));
      }
    },

    disconnectUsb() {
      try {
        if (this.usbDevice) {
          if (this.usbInterfaceNumber !== null) {
            this.usbDevice.releaseInterface(this.usbInterfaceNumber).catch(() => {});
          }
          this.usbDevice.close().catch(() => {});
        }
      } catch (e) {}
      this.usbDevice = null;
      this.usbInterfaceNumber = null;
      this.usbEndpointOut = null;
      this.pairedUsbName = null;
      localStorage.removeItem('ute_usb_printer_name');
      this.showToast('Printer USB diputuskan');
    },

    async printViaUsb(bytes) {
      if (!this.usbDevice || !this.usbEndpointOut) throw new Error('Printer USB tidak tersambung');
      await this.usbDevice.transferOut(this.usbEndpointOut.endpointNumber, bytes);
    },

    async connectSerial() {
      if (!navigator.serial) {
        this.showToast('Web Serial tidak didukung browser ini (gunakan Chrome/Edge & HTTPS/localhost)');
        return;
      }
      try {
        const port = await navigator.serial.requestPort();
        await port.open({ baudRate: 9600 });
        this.serialPort = port;
        const info = port.getInfo ? port.getInfo() : {};
        this.pairedSerialName = info.usbVendorId ? `Serial (VID ${info.usbVendorId})` : 'Serial Printer';
        localStorage.setItem('ute_serial_printer_name', this.pairedSerialName);
        this.showToast('Printer serial/USB terhubung');
      } catch (e) {
        console.warn('Serial connect failed:', e);
        this.showToast('Gagal sambung serial: ' + (e && e.message ? e.message : e));
      }
    },

    disconnectSerial() {
      try {
        if (this.serialPort && this.serialPort.writable) {
          const writer = this.serialPort.writable.getWriter();
          writer.releaseLock();
        }
        if (this.serialPort && typeof this.serialPort.close === 'function') {
          this.serialPort.close();
        }
      } catch (e) {}
      this.serialPort = null;
      this.pairedSerialName = null;
      localStorage.removeItem('ute_serial_printer_name');
      this.showToast('Printer serial diputuskan');
    },

    async printViaSerial(bytes) {
      if (!this.serialPort) throw new Error('Printer serial tidak tersambung');
      if (!this.serialPort.writable) {
        await this.serialPort.open({ baudRate: 9600 });
      }
      const writer = this.serialPort.writable.getWriter();
      await writer.write(bytes);
      writer.releaseLock();
    },

    async saveDefaultPrinter(name, deviceId = '') {
      try {
        await fetch('/api/printer/default', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
          },
          body: JSON.stringify({ name, device_id: deviceId }),
        });
        this.data.thermal_printer_name = name;
      } catch (e) {}
    },

    async choosePrinter() {
      try {
        await this.printer.connect(true);
        this.pairedPrinterName = this.printer.device?.name || 'Bluetooth Printer';
        localStorage.setItem('ute_ble_printer_name', this.pairedPrinterName);
        await this.saveDefaultPrinter(this.pairedPrinterName, this.printer.device?.id || '');
        this.showToast('Printer default: ' + this.pairedPrinterName);
      } catch (e) {
        this.showToast('Pemilihan printer dibatalkan');
      }
    },

    async directPrint(paperSize) {
      if (this.isPrinting) return;
      this.isPrinting = true;
      let bytes = null;

      try {
        const defaultName = this.data.thermal_printer_name || this.pairedPrinterName || '';

        // 1. Hubungkan: default dulu, jika gagal baru minta pilih
        if (!this.printer.device || !this.printer.characteristic) {
          if (defaultName) {
            try {
              await this.printer.connect(false, defaultName, true);
            } catch (e) {
              await this.printer.connect(true);
            }
          } else {
            await this.printer.connect(true);
          }
          this.pairedPrinterName = this.printer.device?.name || defaultName || 'Bluetooth Printer';
          localStorage.setItem('ute_ble_printer_name', this.pairedPrinterName);
        }

        // 2. Cetak
        const paperWidth = Number(paperSize) === 80 ? 80 : 58;
        const payload = { ...this.data, paper_width: paperWidth, font_size: this.data.font_size || 'small' };
        const esc = new window.EscPos(paperWidth);
        bytes = esc.buildReceipt(payload, paperWidth);
        await this.printer.print(bytes);

        // 3. Simpan sebagai default jika berbeda
        if (this.data.thermal_printer_name !== this.pairedPrinterName) {
          await this.saveDefaultPrinter(this.pairedPrinterName, this.printer.device?.id || '');
        }
        this.showToast('Struk berhasil dicetak');
      } catch (e) {
        console.error('Print failed:', e);
        this.showToast('Gagal cetak. Pilih printer lagi');
        try {
          await this.printer.connect(true);
          this.pairedPrinterName = this.printer.device?.name || 'Bluetooth Printer';
          localStorage.setItem('ute_ble_printer_name', this.pairedPrinterName);
          if (bytes) await this.printer.print(bytes);
          await this.saveDefaultPrinter(this.pairedPrinterName, this.printer.device?.id || '');
          this.showToast('Struk berhasil dicetak');
        } catch (e2) {
          this.showToast('Pemilihan printer dibatalkan');
        }
      } finally {
        this.isPrinting = false;
      }
    },

    async printViaCups(printerName, bytes) {
      const response = await fetch('/api/pos/print-raw', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify({
          printer: printerName,
          data: Array.from(bytes),
        }),
      });

      if (!response.ok) {
        throw new Error('CUPS print failed: ' + response.status);
      }
    },

    printViaBrowser(paperSize) {
      const printWindow = window.open('', '_blank');
      if (!printWindow) {
        window.print();
        return;
      }

      const paperWidth = Number(paperSize) === 80 ? 80 : 58;
      const writer = new window.EscPos(paperWidth);
      const html = writer.buildPreviewHtml({ ...this.data, paper_width: paperWidth, font_size: this.data.font_size || 'small' }, paperWidth);

      const storeName = this.data.nama_toko || 'Bremspace';

      printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="utf-8">
          <title>Struk ${storeName}</title>
          <style>
            @media print {
              @page { margin: 0; size: ${paperWidth}mm auto; }
              body { margin: 0; padding: 1mm; background: #fff; }
              .no-print { display: none !important; }
            }
            body {
              margin: 0;
              padding: 4px;
              background: #fff;
              font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
              color: #000;
              -webkit-print-color-adjust: exact;
              print-color-adjust: exact;
            }
            * { box-sizing: border-box; }
          </style>
        </head>
        <body>
          ${html}
        </body>
        </html>
      `);
      printWindow.document.close();
      printWindow.focus();
      setTimeout(() => {
        printWindow.print();
      }, 250);
    },

    async printStruk58(pairNew = false) {
      if (pairNew) {
        try {
          await this.printer.connect(true);
          this.pairedPrinterName = this.printer.device?.name || 'Bluetooth Printer';
          this.showToast('Printer Bluetooth terhubung');
        } catch (e) {
          this.showToast('Gagal menyambung: ' + e.message);
        }
        return;
      }
      await this.directPrint('58');
    },

    async printFaktur80(pairNew = false) {
      if (pairNew) {
        try {
          await this.printer.connect(true);
          this.pairedPrinterName = this.printer.device?.name || 'Bluetooth Printer';
          this.showToast('Printer Bluetooth terhubung');
        } catch (e) {
          this.showToast('Gagal menyambung: ' + e.message);
        }
        return;
      }
      await this.directPrint('80');
    },

    disconnectPrinter() {
      this.printer.disconnect();
      this.pairedPrinterName = null;
      this.showToast('Printer diputuskan');
    },

    toggleAutoDirectPrint() {
      this.autoDirectPrint = !this.autoDirectPrint;
      localStorage.setItem('ute_pos_auto_direct_print', this.autoDirectPrint);
    },

    showToast(message) {
      this.$dispatch('toast', { message, type: 'info' });
    },
  };
}

// Global exposure
window.thermalPrinter = thermalPrinter;
