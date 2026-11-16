/**
 * Thermal Receipt Preview & Print — Paper-efficient layout matching UTEParts reference.
 * EscPos class mirrors PHP EscPosWriter byte-for-byte for 1:1 preview=print parity.
 * Supports Web Bluetooth (auto-reconnect) and CUPS raw printing via backend.
 */
class EscPos {
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

  /** Model 2 QR Code — mirrors PHP EscPosWriter::qrcode() */
  qrcode(content, size = 3) {
    const d = this.ascii(content);
    if (!d || !d.length) return this;
    const len = d.length + 3;
    const pL = len & 0xff;
    const pH = (len >> 8) & 0xff;
    const modSize = Math.max(2, Math.min(8, size));

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

  /** Satuan singkat (pcs/box/set) ditampilkan UPPERCASE agar padan dengan contoh struk. */
  unitLabel(satuan) {
    const u = this.clean(satuan || 'PCS');
    return u.length <= 4 ? u.toUpperCase() : u;
  }

  /** Raw text tanpa LF — untuk baris yang disusun dengan escape di tengah (ESC B). */
  raw(str) { this.buf.push(...this.ascii(str)); return this; }

  /** ESC B n — strikethrough on/off (Epson ESC/POS standar). */
  escB(on) { this.buf.push(0x1b, 0x42, on ? 1 : 0); return this; }

  pad(left, right) {
    left = this.clean(left);
    right = this.clean(right);
    const pad = Math.max(1, this.columns - left.length - right.length);
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
      diskon_tier, diskon_partai, diskon_poin, diskon, diskon_persen, diskon_nominal,
      pajak, ppn_persen, ongkir, subtotal, subtotal_retail, hemat_total,
      is_piutang, jatuh_tempo, sisa_piutang, komisi_mitra_nominal,
      no_tiket, jenis_hp, kurir, is_pickup, resi, sumber,
      garansi,
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

    // ======== HEADER (Centered) ========
    this.alignCenter();
    this.bold(true).sizeDoubleWidth(true).text(nama_toko || 'UTEParts').sizeDoubleWidth(false).bold(false);
    this.text(slogan || 'SPAREPARTS/ACCESORIES/SERVICE');
    if (alamat) this.text(alamat);
    if (telepon) this.text('Telp/WA: ' + telepon);
    if (show_cabang && cabang) this.text(cabang);

    // ======== QR / Web / Tanggal (urutan sama dengan preview) ========
    const qrContent = no_transaksi || '-';
    const qrUrlDomain = qr_url || website_medsos || 'www.uteparts.id';
    const tglStr = tanggal || waktu || '';
    const captionStr = qr_caption || 'Scan QR ini untuk retur & klaim garansi';

    if (qrShown) {
      this.alignCenter();
      this.qrcode(qrContent, qr_size || 3);
      this.bold(true).text(qrContent).bold(false);
      this.text(captionStr);
      this.alignLeft();
      this.text('Web: ' + qrUrlDomain);
      if (tglVisible) this.text('Tgl: ' + tglStr);
    } else {
      this.alignLeft();
      this.text('Web: ' + qrUrlDomain);
      if (tglVisible) this.text('Tgl: ' + tglStr);
      this.text(captionStr);
    }

    this.rule();

    // ======== TRANSACTION INFO ========
    this.alignLeft();
    this.text(this.pad('No. TRX:', qrContent));

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

    if (show_kasir && kasir) {
      this.text(this.pad('Kasir:', kasir));
    }

    if (show_pelanggan) {
      const pel = pelanggan || 'Umum';
      this.text(this.pad('Pelanggan:', pel));

      if (tier && tier !== 'Retail' && tier !== 'Umum') {
        const lbl = ['reseller', 'agen'].includes(tipe_konsumen) ? 'Mitra:' : 'Tier:';
        this.bold(true).text(this.pad(lbl, tier)).bold(false);
      }
    }

    // ======== ITEMS ========
    for (const item of items) {
      const nama = item.nama || 'Item';
      for (const line of this.wrap(nama)) {
        this.bold(true).text(line).bold(false);
      }

      const qtySatuan = (item.qty || 1) + ' ' + this.unitLabel(item.satuan);
      const harga = this.money(item.harga);
      const rightVal = this.numberOnly(item.subtotal);
      const hargaRetail = Number(item.harga_retail ?? 0);
      const hargaJual = Number(item.harga ?? 0);

      if (hargaRetail > hargaJual && strikethrough_mode === 'esc_b') {
        // Baris disusun manual: "1 PCS x " + [ESC B 1]harga normal[ESC B 0] + " harga final" + spasi + subtotal.
        const normal = this.money(hargaRetail);
        const disc = this.money(hargaJual);
        const prefix = qtySatuan + ' x ';
        const gap = Math.max(1, this.columns - prefix.length - normal.length - 1 - disc.length - rightVal.length);
        this.raw(prefix);
        this.escB(true).raw(normal).escB(false);
        this.raw(' ' + disc);
        this.buf.push(...this.ascii(' '.repeat(gap) + rightVal), 0x0a);
      } else if (hargaRetail > hargaJual) {
        this.text(this.pad(`${qtySatuan} x (${this.money(hargaRetail)}) -> ${this.money(hargaJual)}`, rightVal));
      } else {
        this.text(this.pad(`${qtySatuan} x ${harga}`, rightVal));
      }

      if (item.catatan) {
        for (const ln of this.wrap('  * ' + item.catatan)) {
          this.text(ln);
        }
      }
    }

    // ======== SUBTOTAL BREAKDOWN & TOTALS (Directly follows items) ========
    if (show_subtotal_breakdown !== false && diskon_tier && Number(diskon_tier) > 0) {
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

    if (diskon_partai && Number(diskon_partai) > 0) {
      this.text(this.pad('Diskon Partai:', '-' + this.money(diskon_partai)));
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

    // Bold total
    this.bold(true).sizeDoubleHeight(true).text(this.pad('TOTAL:', this.money(total))).sizeDoubleHeight(false).bold(false);

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

    this.rule();

    // ======== FOOTER (Centered) ========
    this.alignCenter();
    if (garansi) this.text('Garansi: ' + garansi);
    this.text(footer_text || 'Terima kasih atas kunjungan Anda!');
    this.text(kebijakan_retur || 'Segel toko wajib utuh & fisik barang tidak cacat.');

    if (catatan_kaki) {
      for (const ln of this.wrap(catatan_kaki)) {
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

    this.feed(1).cut();

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

  /** Build HTML preview aligned with ESC/POS receipt layout/flow */
  buildPreviewHtml(data, paperWidthOverride = null) {
    const {
      items = [],
      total = 0, bayar = 0, kembali = 0,
      no_transaksi = '-', metode_bayar = 'TUNAI', metode = 'TUNAI', tanggal, waktu, kasir, cabang,
      pelanggan, tier, tipe_konsumen, saldo_poin, poin_ditukar,
      diskon_tier, diskon_partai, diskon_poin, diskon, diskon_persen, diskon_nominal,
      pajak, ppn_persen, ongkir, subtotal, subtotal_retail, hemat_total,
      is_piutang, jatuh_tempo, sisa_piutang, komisi_mitra_nominal,
      no_tiket, jenis_hp, kurir, is_pickup, resi, sumber,
      garansi,
      footer_text, kebijakan_retur, catatan_kaki,
      nama_toko, slogan, alamat, telepon, website_medsos,
      show_cabang, show_kasir = true, show_pelanggan = true, show_waktu = true,
      show_subtotal_breakdown = true,
      show_qr_retur = true, qr_caption, qr_url, qr_svg,
      paper_width = 58, qr_size = 3,
    } = data;

    const money = (v) => 'Rp ' + Number(v || 0).toLocaleString('id-ID');
    const numberOnly = (v) => Number(v || 0).toLocaleString('id-ID');
    const unitLabel = (satuan) => {
      const u = String(satuan || 'PCS');
      return u.length <= 4 ? u.toUpperCase() : u;
    };
    const esc = (str) => String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    const widthNum = Number(paperWidthOverride ?? paper_width) === 80 ? 80 : 58;
    const paperClass = widthNum === 80 ? 'paper-80' : 'paper-58';
    const qrContent = no_transaksi || '-';
    const qrUrlDomain = qr_url || website_medsos || 'www.uteparts.id';
    const tglStr = show_waktu ? (tanggal || waktu || '') : '';
    const caraBayar = String(metode_bayar || metode || 'TUNAI').toUpperCase();
    const captionStr = qr_caption || 'Scan QR ini untuk retur & klaim garansi';
    const qrShown = !!(show_qr_retur && qrContent !== '-');
    const rule = '<div style="border-top:1px dashed #000; width:100%; margin:4px 0;"></div>';
    const row = (left, right, opts = {}) => `
      <div style="display:flex; justify-content:space-between; gap:8px; width:100%; ${opts.style || ''}">
        <span style="${opts.leftStyle || ''}">${esc(left)}</span>
        <span style="text-align:right; white-space:nowrap; ${opts.rightStyle || ''}">${esc(right)}</span>
      </div>`;

    const renderedQr = qr_svg || `
      <svg width="68" height="68" viewBox="0 0 100 100" style="background:#fff; border:1px solid #d1d5db; border-radius:4px; padding:2px;">
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
      </svg>`;

    // Items (follow ESC/POS flow)
    const itemsHtml = items.map(item => {
      const nama = item.nama || 'Item';
      const qtySatuan = (item.qty || 1) + ' ' + unitLabel(item.satuan);
      const harga = money(item.harga);
      const rightVal = numberOnly(item.subtotal);
      const hargaRetail = Number(item.harga_retail ?? 0);
      const hargaJual = Number(item.harga ?? 0);
      const leftPrice = (hargaRetail > hargaJual)
        ? `${qtySatuan} x <span style="text-decoration:line-through;opacity:.8">${esc(money(hargaRetail))}</span> ${esc(money(hargaJual))}`
        : `${qtySatuan} x ${esc(harga)}`;
      return `
        <div style="width:100%; margin-bottom:2px;">
          <div style="font-weight:700; line-height:1.25; word-break:break-word;">${esc(nama)}</div>
          <div style="display:flex; justify-content:space-between; gap:8px; width:100%; font-size:inherit;">
            <span>${leftPrice}</span>
            <span style="text-align:right; white-space:nowrap;">${esc(rightVal)}</span>
          </div>
          ${item.catatan ? `<div style="font-size:0.9em; opacity:.85;">  * ${esc(item.catatan)}</div>` : ''}
        </div>`;
    }).join('');

    // Totals/breakdown (match buildReceipt conditions/order)
    let totalsHtml = '';
    if (show_subtotal_breakdown !== false && diskon_tier && Number(diskon_tier) > 0) {
      const subRetail = Number(subtotal_retail ?? 0) || (Number(subtotal ?? 0) + Number(diskon_tier));
      const tierLbl = (tier && tier !== 'Retail') ? `Pot ${tier}:` : (['reseller', 'agen'].includes(tipe_konsumen) ? 'Pot Mitra:' : 'Pot Tier:');
      totalsHtml += row('Subtotal Retail:', money(subRetail));
      totalsHtml += row(tierLbl, '-' + money(diskon_tier));
      totalsHtml += row('Subtotal:', money(subtotal ?? total));
    } else {
      totalsHtml += row('Subtotal:', money(subtotal ?? total));
    }
    if (diskon_partai && Number(diskon_partai) > 0) totalsHtml += row('Diskon Partai:', '-' + money(diskon_partai));
    if (diskon_poin && Number(diskon_poin) > 0) totalsHtml += row(`Tukar Poin (${poin_ditukar || 0}pts):`, '-' + money(diskon_poin));
    if (diskon && Number(diskon) > 0) {
      const lbl = (diskon_persen && Number(diskon_persen) > 0) ? `Diskon (${diskon_persen}%):` : 'Diskon Kasir:';
      totalsHtml += row(lbl, '-' + money(diskon));
    }
    if (pajak && Number(pajak) > 0) totalsHtml += row(ppn_persen ? `PPN (${ppn_persen}%):` : 'PPN:', '+' + money(pajak));
    if (ongkir && Number(ongkir) > 0) totalsHtml += row('Ongkir:', '+' + money(ongkir));

    totalsHtml += `
      <div style="display:flex; justify-content:space-between; gap:8px; width:100%; font-size:1.2em; font-weight:900; padding:2px 0;">
        <span>TOTAL:</span><span style="text-align:right; white-space:nowrap;">${esc(money(total))}</span>
      </div>`;

    if (is_piutang) {
      totalsHtml += row('Status:', 'BELUM LUNAS (PIUTANG)', { rightStyle: 'font-weight:700;' });
      if (jatuh_tempo) totalsHtml += row('Jatuh Tempo:', jatuh_tempo);
      totalsHtml += row('Sisa Tag:', money(sisa_piutang ?? total), { rightStyle: 'font-weight:700;' });
    } else {
      totalsHtml += row(`BAYAR (${caraBayar}):`, money(bayar || total), { rightStyle: 'font-weight:600;' });
      totalsHtml += row('KEMBALI:', money(kembali ?? 0), { rightStyle: 'font-weight:600;' });
    }
    if (saldo_poin !== undefined) totalsHtml += row('Sisa Saldo Poin:', numberOnly(saldo_poin) + ' pts');
    if (hemat_total && Number(hemat_total) > 0) totalsHtml += row('HEMAT:', money(hemat_total), { leftStyle: 'font-weight:700;', rightStyle: 'font-weight:700;' });
    if (komisi_mitra_nominal && Number(komisi_mitra_nominal) > 0) totalsHtml += row('Komisi:', '+' + money(komisi_mitra_nominal) + ' (Saldo)');

    // Header/QR block (follow buildReceipt order)
    const qrBlock = qrShown ? `
      <div style="text-align:center; width:100%; margin:2px 0;">
        <div style="display:inline-block;">${renderedQr}</div>
        <div style="font-weight:700; margin-top:2px;">${esc(qrContent)}</div>
        <div style="font-size:0.9em;">${esc(captionStr)}</div>
      </div>
      <div style="text-align:left; width:100%;">
        <div>Web: ${esc(qrUrlDomain)}</div>
        ${tglStr ? `<div>Tgl: ${esc(tglStr)}</div>` : ''}
      </div>
    ` : `
      <div style="text-align:left; width:100%;">
        <div>Web: ${esc(qrUrlDomain)}</div>
        ${tglStr ? `<div>Tgl: ${esc(tglStr)}</div>` : ''}
        <div>${esc(captionStr)}</div>
      </div>
    `;

    // Transaction info
    let infoHtml = row('No. TRX:', qrContent, { rightStyle: 'font-weight:700;' });
    if (sumber && !['pos', 'offline'].includes(String(sumber).toLowerCase())) infoHtml += row('Pesanan:', String(sumber).toUpperCase());
    if (no_tiket) infoHtml += row('No. Tiket:', no_tiket, { leftStyle: 'font-weight:700;', rightStyle: 'font-weight:700;' });
    if (jenis_hp) infoHtml += row('Unit:', jenis_hp);
    if (kurir) infoHtml += row(is_pickup ? 'Ambil:' : 'Kurir:', kurir + (resi && resi !== '-' ? ' (' + resi + ')' : ''));
    if (show_kasir && kasir) infoHtml += row('Kasir:', kasir);
    if (show_pelanggan) {
      infoHtml += row('Pelanggan:', pelanggan || 'Umum');
      if (tier && tier !== 'Retail' && tier !== 'Umum') {
        const lbl = ['reseller', 'agen'].includes(tipe_konsumen) ? 'Mitra:' : 'Tier:';
        infoHtml += row(lbl, tier, { leftStyle: 'font-weight:700;', rightStyle: 'font-weight:700;' });
      }
    }

    const footerPiutang = is_piutang ? `
      <div style="margin-top:6px;">
        <div style="font-style:italic; font-size:0.9em; text-align:center;">"Barang diterima &amp; berjanji melunasi sblm jatuh tempo"</div>
        <div style="margin-top:6px; text-align:center;">Penerima,              Kasir,</div>
        <div style="margin-top:14px; text-align:center;">(............)        (............)</div>
      </div>` : '';

    return `
<div class="thermal-receipt-doc ${paperClass}" style="width:100%; font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace; font-size:${widthNum === 80 ? '11px' : '10px'}; line-height:1.3; color:#000; background:#fff;">
  <div style="text-align:center; width:100%;">
    <div style="font-size:1.4em; font-weight:900; letter-spacing:0.04em; text-transform:uppercase;">${esc(nama_toko || 'UTEParts')}</div>
    <div style="font-size:0.9em; font-weight:600; text-transform:uppercase; margin-top:2px;">${esc(slogan || 'SPAREPARTS/ACCESORIES/SERVICE')}</div>
    ${alamat ? `<div style="font-size:0.85em; margin-top:2px;">${esc(alamat)}</div>` : ''}
    ${telepon ? `<div style="font-size:0.9em;">Telp/WA: ${esc(telepon)}</div>` : ''}
    ${show_cabang && cabang ? `<div style="font-size:0.9em; font-weight:600;">${esc(cabang)}</div>` : ''}
  </div>

  ${qrBlock}
  ${rule}

  <div style="width:100%;">${infoHtml}</div>
  <div style="width:100%; margin:4px 0;">${itemsHtml}</div>
  <div style="width:100%;">${totalsHtml}</div>

  ${rule}
  <div style="text-align:center; width:100%; font-size:0.9em;">
    ${garansi ? `<div style="font-weight:700;">Garansi: ${esc(garansi)}</div>` : ''}
    <div>${esc(footer_text || 'Terima kasih atas kunjungan Anda!')}</div>
    <div>${esc(kebijakan_retur || 'Segel toko wajib utuh & fisik barang tidak cacat.')}</div>
    ${catatan_kaki ? `<div style="margin-top:2px;">${esc(catatan_kaki)}</div>` : ''}
    ${footerPiutang}
  </div>
</div>`;
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

  /** Connect to printer — prompts device picker if not connected */
  async connect(pairNew = false) {
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
        this.device = await navigator.bluetooth.requestDevice({
          filters: [
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
          ],
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

    init() {
      this.paperSize = localStorage.getItem('ute_pos_paper_size') ||
        (this.data.default_width?.includes('80') ? '80' : '58');

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

      // Auto-trigger background print on checkout if configured
      this.$nextTick(() => {
        if (this.autoDirectPrint) {
          setTimeout(() => {
            this.directPrint(this.paperSize);
          }, 350);
        }
      });
    },

    async directPrint(paperSize) {
      if (this.isPrinting) return;
      this.isPrinting = true;

      try {
        const paperWidth = Number(paperSize) === 80 ? 80 : 58;
        // Selera kertas & mode font selalu eksplisit agar output == preview == backend job.
        const payload = { ...this.data, paper_width: paperWidth, font_size: 'small' };
        const esc = new window.EscPos(paperWidth);
        const bytes = esc.buildReceipt(payload, paperWidth);

        // 1. CUPS raw printing if configured
        if (savedCupsPrinter) {
          await this.printViaCups(savedCupsPrinter, bytes);
          this.showToast(`Struk dikirim ke printer CUPS: ${savedCupsPrinter}`);
        }
        // 2. Web Bluetooth direct print
        else if (this.printer.device || localStorage.getItem('thermal_printer_device')) {
          await this.printer.print(bytes);
          this.showToast('Struk berhasil dicetak via Bluetooth');
        }
        // 3. Browser fallback dialog
        else {
          this.printViaBrowser(paperSize);
        }
      } catch (e) {
        console.error('Print failed:', e);
        // Fallback to browser print on BLE error if user desires
        if (confirm('Gagal cetak via Bluetooth (' + e.message + '). Buka cetak browser?')) {
          this.printViaBrowser(paperSize);
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
      const esc = new window.EscPos(paperWidth);
      const html = esc.buildPreviewHtml({ ...this.data, paper_width: paperWidth, font_size: 'small' }, paperWidth);

      printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="utf-8">
          <title>Struk UTEParts</title>
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
