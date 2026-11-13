/**
 * Thermal Receipt Preview & Print — Paper-efficient layout matching UTEParts reference.
 * EscPos class mirrors PHP EscPosWriter byte-for-byte for 1:1 preview=print parity.
 * Supports Web Bluetooth (auto-reconnect) and CUPS raw printing via backend.
 */
class EscPos {
  constructor(paperWidth = 58) {
    this.paperWidth = paperWidth; // 58 or 80
    this.compressed = true; // Always small font for paper saving
    this.columns = this.compressed
      ? (paperWidth === 80 ? 64 : 42)
      : (paperWidth === 80 ? 48 : 32);
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
  qrcode(content, size = 4) {
    const d = this.ascii(content);
    if (!d) return this;
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
    // Transliterate non-ASCII → nearest ASCII for cp437 compatibility
    return str
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

  money(v) { return 'Rp ' + Number(v).toLocaleString('id-ID'); }

  pad(left, right) {
    left = this.clean(left);
    right = this.clean(right);
    const pad = Math.max(1, this.columns - left.length - right.length);
    return left + ' '.repeat(pad) + right;
  }

  clean(str) {
    return str
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
    let line = '';
    for (const word of clean.split(' ')) {
      if ((line + word).length > this.columns) {
        if (line) lines.push(line);
        line = word;
      } else {
        line += (line ? ' ' : '') + word;
      }
    }
    if (line) lines.push(line);
    return lines;
  }

  // ============================================================
  // Full receipt builder — mirrors PHP EscPosWriter::receipt()
  // ============================================================
  buildReceipt(data) {
    const {
      items = [],
      total, bayar, kembali,
      no_transaksi, metode_bayar, tanggal, kasir, cabang,
      pelanggan, tier, tipe_konsumen, saldo_poin, poin_ditukar,
      diskon_tier, diskon_partai, diskon_poin, diskon, diskon_persen, diskon_nominal,
      pajak, ppn_persen, ongkir, subtotal, subtotal_retail, hemat_total,
      is_piutang, jatuh_tempo, sisa_piutang, komisi_mitra_nominal,
      no_tiket, jenis_hp, kurir, is_pickup, resi, sumber,
      garansi,
      footer_text, kebijakan_retur, catatan_kaki,
      nama_toko, slogan, alamat, telepon, website_medsos,
      show_cabang, show_kasir, show_pelanggan, show_waktu,
      show_qr_retur, qr_caption, qr_size, qr_url,
      paper_width = 58,
      font_size = 'small',
    } = data;

    // Re-init with correct paper width
    this.paperWidth = paper_width;
    this.compressed = font_size === 'small';
    this.columns = this.compressed
      ? (paper_width === 80 ? 64 : 42)
      : (paper_width === 80 ? 48 : 32);
    this.init();

    // ======== HEADER ========
    this.alignCenter();
    this.bold(true).sizeDoubleWidth(true).text(nama_toko || 'UTEPARTS').sizeDoubleWidth(false).bold(false);
    if (slogan) this.text(slogan);
    if (alamat) this.text(alamat);
    if (telepon) this.text('Telp/WA: ' + telepon);
    if (website_medsos) this.text(website_medsos);
    if (show_cabang && cabang) this.text(cabang);
    if (show_waktu && tanggal) this.text(tanggal);
    this.rule();

    // ======== 2-COL: QR (Left) + URL/Date/Warranty (Right) ========
    const qrContent = no_transaksi || '-';
    const qrUrl = qr_url || 'www.uteparts.id';

    if (show_qr_retur && no_transaksi !== '-') {
      this.alignCenter();
      this.qrcode(qrContent, qr_size || 4);
      this.bold(true).text(qrContent).bold(false);
      this.text(qr_caption || 'Scan QR untuk Retur & Garansi');

      this.alignLeft();
      this.text('Web: ' + qrUrl);
      if (tanggal) this.text('Tgl: ' + tanggal);
      if (garansi) this.text('Garansi: ' + garansi);
      this.text('Barang fisik utk tukar tdk rusak');
    } else {
      this.alignLeft();
      this.text('Web: ' + qrUrl);
      if (tanggal) this.text('Tgl: ' + tanggal);
      if (garansi) this.text('Garansi: ' + garansi);
      this.text('Barang fisik utk tukar tdk rusak');
    }

    this.rule();

    // ======== TRANSACTION INFO ========
    this.alignLeft();
    this.text(this.pad('TRX', no_transaksi));

    if (sumber && !['pos', 'offline'].includes(s?.toLowerCase())) {
      this.text(this.pad('Pesanan', sumber.toUpperCase()));
    }

    if (no_tiket) {
      this.bold(true).text(this.pad('Tiket', no_tiket)).bold(false);
    }

    if (jenis_hp) {
      this.text(this.pad('Unit', jenis_hp));
    }

    if (kurir) {
      const kurirLbl = is_pickup ? 'Ambil' : 'Kurir';
      const resiTeks = (resi && resi !== '-') ? ' (' + resi + ')' : '';
      this.text(this.pad(kurirLbl, kurir + resiTeks));
    }

    if (show_kasir && kasir) {
      this.text(this.pad('Kasir', kasir));
    }

    if (show_pelanggan) {
      const pel = pelanggan || 'Umum';
      this.text(this.pad('Pelanggan', pel));

      if (tier && tier !== 'Retail' && tier !== 'Umum') {
        const lbl = ['reseller', 'agen'].includes(tipe_konsumen) ? 'Mitra' : 'Tier';
        this.bold(true).text(this.pad(lbl, tier)).bold(false);
      }

      if (saldo_poin && Number(saldo_poin) > 0) {
        this.text(this.pad('Poin', Number(saldo_poin).toLocaleString('id-ID') + ' Poin'));
      }
    }

    this.rule();

    // ======== ITEMS ========
    for (const item of items) {
      const nama = item.nama || 'Item';
      for (const line of this.wrap(nama)) {
        this.bold(true).text(line).bold(false);
      }

      const qtySatuan = item.qty + (item.satuan ? ' ' + item.satuan : '');
      const harga = this.money(item.harga);
      const subtotal = this.money(item.subtotal);

      this.text(this.pad(qtySatuan + ' x ' + harga, subtotal));

      // Strikethrough retail price if discounted
      const hargaRetail = Number(item.harga_retail ?? 0);
      const hargaJual = Number(item.harga ?? 0);
      if (hargaRetail > hargaJual) {
        const normal = this.money(hargaRetail);
        const disc = this.money(hargaJual);
        // Use ESC B strikethrough (preview renders via CSS line-through)
        this.textStrikethrough('Normal: ' + normal + ' -> ' + disc);
      }

      if (item.catatan) {
        for (const ln of this.wrap('  * ' + item.catatan)) {
          this.text(ln);
        }
      }
    }

    this.rule();

    // ======== SUBTOTAL BREAKDOWN ========
    const showBreakdown = true; // always show for paper-saving layout
    if (showBreakdown && diskon_tier && Number(diskon_tier) > 0) {
      const subRetail = Number(subtotal_retail ?? 0) || (Number(subtotal ?? 0) + Number(diskon_tier));
      this.text(this.pad('Subtotal Retail', this.money(subRetail)));

      const tierLbl = (tier && tier !== 'Retail')
        ? 'Pot ' + tier
        : (['reseller', 'agen'].includes(tipe_konsumen) ? 'Pot Mitra' : 'Pot Tier');
      this.text(this.pad(tierLbl, '-' + this.money(diskon_tier)));
      this.text(this.pad('Subtotal', this.money(subtotal ?? total)));
    } else {
      this.text(this.pad('Subtotal', this.money(subtotal ?? total)));
    }

    if (diskon_partai && Number(diskon_partai) > 0) {
      this.text(this.pad('Diskon Partai', '-' + this.money(diskon_partai)));
    }
    if (diskon_poin && Number(diskon_poin) > 0) {
      this.text(this.pad('Tukar Poin (' + (poin_ditukar || 0) + 'pts)', '-' + this.money(diskon_poin)));
    }
    if (diskon && Number(diskon) > 0) {
      const lbl = (diskon_persen && Number(diskon_persen) > 0)
        ? 'Diskon (' + diskon_persen + '%)'
        : 'Diskon Kasir';
      this.text(this.pad(lbl, '-' + this.money(diskon)));
    }
    if (pajak && Number(pajak) > 0) {
      const ppnLbl = ppn_persen ? 'PPN (' + ppn_persen + '%)' : 'PPN';
      this.text(this.pad(ppnLbl, '+' + this.money(pajak)));
    }
    if (ongkir && Number(ongkir) > 0) {
      this.text(this.pad('Ongkir', '+' + this.money(ongkir)));
    }

    this.rule();

    // ======== TOTAL & PAYMENT ========
    this.bold(true).sizeDoubleHeight(true).text(this.pad('TOTAL', this.money(total))).sizeDoubleHeight(false).bold(false);

    if (is_piutang) {
      this.text('Status: BELUM LUNAS (PIUTANG)');
      if (jatuh_tempo) this.text('Jatuh Tempo: ' + jatuh_tempo);
      this.text('Sisa Tag: ' + this.money(sisa_piutang ?? total));
    } else {
      this.text('Bayar: ' + this.money(bayar));
      this.text('Kembali: ' + this.money(kembali ?? 0));
      this.text('Metode: ' + metode_bayar);
    }

    if (hemat_total && Number(hemat_total) > 0) {
      this.bold(true).text(this.pad('HEMAT', this.money(hemat_total))).bold(false);
    }

    if (komisi_mitra_nominal && Number(komisi_mitra_nominal) > 0) {
      this.text('Komisi: +' + this.money(komisi_mitra_nominal) + ' (Saldo)');
    }

    if (saldo_poin !== undefined) {
      this.text('Sisa Poin: ' + Number(saldo_poin).toLocaleString('id-ID') + ' pts');
    }

    this.rule().feed(1);

    // ======== FOOTER ========
    this.alignCenter();
    this.text(footer_text || 'Terima Kasih atas Kunjungan Anda!');

    if (kebijakan_retur) {
      for (const ln of this.wrap(kebijakan_retur)) {
        this.text(ln);
      }
    }
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

  /** Render HTML preview that visually matches the ESC/POS output 1:1 */
  renderPreviewHtml(data) {
    const bytes = this.buildReceipt(data);
    // We'll build HTML directly mirroring the layout logic above
    // This is more reliable than trying to render bytes visually
    return this.buildPreviewHtml(data);
  }

  /** Build HTML preview matching the exact paper layout */
  buildPreviewHtml(data) {
    const {
      items = [],
      total, bayar, kembali,
      no_transaksi, metode_bayar, tanggal, kasir, cabang,
      pelanggan, tier, tipe_konsumen, saldo_poin, poin_ditukar,
      diskon_tier, diskon_partai, diskon_poin, diskon, diskon_persen, diskon_nominal,
      pajak, ppn_persen, ongkir, subtotal, subtotal_retail, hemat_total,
      is_piutang, jatuh_tempo, sisa_piutang, komisi_mitra_nominal,
      no_tiket, jenis_hp, kurir, is_pickup, resi, sumber,
      garansi,
      footer_text, kebijakan_retur, catatan_kaki,
      nama_toko, slogan, alamat, telepon, website_medsos,
      show_cabang, show_kasir, show_pelanggan, show_waktu,
      show_qr_retur, qr_caption, qr_url,
      paper_width = 58,
    } = data;

    const money = (v) => 'Rp ' + Number(v).toLocaleString('id-ID');
    const esc = (str) => str.replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"');

    const paperClass = paper_width === 80 ? 'paper-80' : 'paper-58';
    const qrContent = no_transaksi || '-';
    const qrUrl = qr_url || 'www.uteparts.id';

    let html = `
<div class="thermal-preview ${paperClass}" style="--paper-width: ${paper_width}mm;">
  <div class="receipt">
`;

    // Header
    html += `
    <div class="section header centered">
      <div class="shop-name">${esc(nama_toko || 'UTEPARTS')}</div>
      ${slogan ? `<div class="slogan">${esc(slogan)}</div>` : ''}
      ${alamat ? `<div class="addr">${esc(alamat)}</div>` : ''}
      ${telepon ? `<div class="phone">Telp/WA: ${esc(telepon)}</div>` : ''}
      ${website_medsos ? `<div class="web">${esc(website_medsos)}</div>` : ''}
      ${show_cabang && cabang ? `<div class="cabang">${esc(cabang)}</div>` : ''}
      ${show_waktu && tanggal ? `<div class="time">${esc(tanggal)}</div>` : ''}
      <hr class="rule">
    </div>
`;

    // QR + Right column
    html += `
    <div class="section qr-section">
      ${show_qr_retur && no_transaksi !== '-' ? `
      <div class="qr-col">
        <div class="qr-code" data-qr="${esc(qrContent)}"></div>
        <div class="qr-label bold">${esc(qrContent)}</div>
        <div class="qr-caption">${esc(qr_caption || 'Scan QR untuk Retur & Garansi')}</div>
      </div>
      ` : ''}
      <div class="info-col">
        <div>Web: ${esc(qrUrl)}</div>
        ${tanggal ? `<div>Tgl: ${esc(tanggal)}</div>` : ''}
        ${garansi ? `<div>Garansi: ${esc(garansi)}</div>` : ''}
        <div>Barang fisik utk tukar tdk rusak</div>
      </div>
      <hr class="rule">
    </div>
`;

    // Transaction info
    html += `
    <div class="section info-section">
      <div class="row"><span class="label">TRX</span><span class="value">${esc(no_transaksi)}</span></div>
      ${sumber && !['pos', 'offline'].includes(sumber.toLowerCase()) ? `<div class="row"><span class="label">Pesanan</span><span class="value">${esc(sumber.toUpperCase())}</span></div>` : ''}
      ${no_tiket ? `<div class="row"><span class="label">Tiket</span><span class="value bold">${esc(no_tiket)}</span></div>` : ''}
      ${jenis_hp ? `<div class="row"><span class="label">Unit</span><span class="value">${esc(jenis_hp)}</span></div>` : ''}
      ${kurir ? `<div class="row"><span class="label">${is_pickup ? 'Ambil' : 'Kurir'}</span><span class="value">${esc(kurir + (resi && resi !== '-' ? ' (' + resi + ')' : ''))}</span></div>` : ''}
      ${show_kasir && kasir ? `<div class="row"><span class="label">Kasir</span><span class="value">${esc(kasir)}</span></div>` : ''}
      ${show_pelanggan ? `
        <div class="row"><span class="label">Pelanggan</span><span class="value">${esc(pelanggan || 'Umum')}</span></div>
        ${tier && tier !== 'Retail' && tier !== 'Umum' ? `<div class="row"><span class="label">${['reseller', 'agen'].includes(tipe_konsumen) ? 'Mitra' : 'Tier'}</span><span class="value bold">${esc(tier)}</span></div>` : ''}
        ${saldo_poin && Number(saldo_poin) > 0 ? `<div class="row"><span class="label">Poin</span><span class="value">${Number(saldo_poin).toLocaleString('id-ID')} Poin</span></div>` : ''}
      ` : ''}
      <hr class="rule">
    </div>
`;

    // Items
    html += `
    <div class="section items-section">
`;
    for (const item of items) {
      const nama = item.nama || 'Item';
      const qtySatuan = item.qty + (item.satuan ? ' ' + item.satuan : '');
      const harga = money(item.harga);
      const subtotalItem = money(item.subtotal);
      const hargaRetail = Number(item.harga_retail ?? 0);
      const hargaJual = Number(item.harga ?? 0);

      html += `
      <div class="item">
        <div class="item-name bold">${esc(nama)}</div>
        <div class="row"><span class="label">${esc(qtySatuan + ' x ' + harga)}</span><span class="value">${esc(subtotalItem)}</span></div>
`;
      if (hargaRetail > hargaJual) {
        html += `<div class="row strikethrough"><span class="label">Normal: ${money(hargaRetail)}</span><span class="value arrow">${esc('→')}</span><span class="value">${money(hargaJual)}</span></div>`;
      }
      if (item.catatan) {
        html += `<div class="item-note">* ${esc(item.catatan)}</div>`;
      }
      html += `</div>`;
    }
    html += `      <hr class="rule">
    </div>
`;

    // Subtotal breakdown
    html += `
    <div class="section totals-section">
`;
    if (diskon_tier && Number(diskon_tier) > 0) {
      const subRetail = Number(subtotal_retail ?? 0) || (Number(subtotal ?? 0) + Number(diskon_tier));
      html += `<div class="row"><span class="label">Subtotal Retail</span><span class="value">${money(subRetail)}</span></div>`;
      const tierLbl = (tier && tier !== 'Retail')
        ? 'Pot ' + tier
        : (['reseller', 'agen'].includes(tipe_konsumen) ? 'Pot Mitra' : 'Pot Tier');
      html += `<div class="row"><span class="label">${tierLbl}</span><span class="value neg">-${money(diskon_tier)}</span></div>`;
      html += `<div class="row"><span class="label">Subtotal</span><span class="value">${money(subtotal ?? total)}</span></div>`;
    } else {
      html += `<div class="row"><span class="label">Subtotal</span><span class="value">${money(subtotal ?? total)}</span></div>`;
    }
    if (diskon_partai && Number(diskon_partai) > 0) {
      html += `<div class="row"><span class="label">Diskon Partai</span><span class="value neg">-${money(diskon_partai)}</span></div>`;
    }
    if (diskon_poin && Number(diskon_poin) > 0) {
      html += `<div class="row"><span class="label">Tukar Poin (${poin_ditukar || 0}pts)</span><span class="value neg">-${money(diskon_poin)}</span></div>`;
    }
    if (diskon && Number(diskon) > 0) {
      const lbl = (diskon_persen && Number(diskon_persen) > 0)
        ? 'Diskon (' + diskon_persen + '%)'
        : 'Diskon Kasir';
      html += `<div class="row"><span class="label">${esc(lbl)}</span><span class="value neg">-${money(diskon)}</span></div>`;
    }
    if (pajak && Number(pajak) > 0) {
      const ppnLbl = ppn_persen ? 'PPN (' + ppn_persen + '%)' : 'PPN';
      html += `<div class="row"><span class="label">${esc(ppnLbl)}</span><span class="value pos">+${money(pajak)}</span></div>`;
    }
    if (ongkir && Number(ongkir) > 0) {
      html += `<div class="row"><span class="label">Ongkir</span><span class="value pos">+${money(ongkir)}</span></div>`;
    }
    html += `      <hr class="rule">
    </div>
`;

    // Total & Payment
    html += `
    <div class="section payment-section">
      <div class="row total-row bold"><span class="label">TOTAL</span><span class="value">${money(total)}</span></div>
`;
    if (is_piutang) {
      html += `
      <div class="row"><span class="label">Status</span><span class="value piutang">BELUM LUNAS (PIUTANG)</span></div>
      ${jatuh_tempo ? `<div class="row"><span class="label">Jatuh Tempo</span><span class="value">${esc(jatuh_tempo)}</span></div>` : ''}
      <div class="row"><span class="label">Sisa Tag</span><span class="value">${money(sisa_piutang ?? total)}</span></div>
`;
    } else {
      html += `
      <div class="row"><span class="label">Bayar</span><span class="value">${money(bayar)}</span></div>
      <div class="row"><span class="label">Kembali</span><span class="value">${money(kembali ?? 0)}</span></div>
      <div class="row"><span class="label">Metode</span><span class="value">${esc(metode_bayar)}</span></div>
`;
    }
    if (hemat_total && Number(hemat_total) > 0) {
      html += `<div class="row bold"><span class="label">HEMAT</span><span class="value">${money(hemat_total)}</span></div>`;
    }
    if (komisi_mitra_nominal && Number(komisi_mitra_nominal) > 0) {
      html += `<div class="row"><span class="label">Komisi</span><span class="value pos">+${money(komisi_mitra_nominal)} (Saldo)</span></div>`;
    }
    if (saldo_poin !== undefined) {
      html += `<div class="row"><span class="label">Sisa Poin</span><span class="value">${Number(saldo_poin).toLocaleString('id-ID')} pts</span></div>`;
    }
    html += `      <hr class="rule">
    </div>
`;

    // Footer
    html += `
    <div class="section footer centered">
      <div>${esc(footer_text || 'Terima Kasih atas Kunjungan Anda!')}</div>
      ${kebijakan_retur ? `<div class="policy">${esc(kebijakan_retur)}</div>` : ''}
      ${catatan_kaki ? `<div class="note">${esc(catatan_kaki)}</div>` : ''}
      ${is_piutang ? `
        <div class="piutang-signature">
          <p>"Barang diterima & berjanji melunasi sblm jatuh tempo"</p>
          <div class="sig-row">
            <div class="sig-col">Penerima,</div>
            <div class="sig-col">Kasir,</div>
          </div>
          <div class="sig-row">
            <div class="sig-col">(............)</div>
            <div class="sig-col">(............)</div>
          </div>
        </div>
      ` : ''}
    </div>
  </div>
</div>
`;
    return html;
  }
}

/** Thermal printer manager — Web Bluetooth with auto-reconnect */
class ThermalPrinter {
  constructor() {
    this.device = null;
    this.characteristic = null;
    this.reconnectTimer = null;
    this.isConnecting = false;
    this.onStatusChange = null;
    this.onError = null;

    // Auto-reconnect on page load if previously paired
    this.loadSavedDevice();
  }

  /** Load previously paired device from localStorage */
  async loadSavedDevice() {
    try {
      const saved = localStorage.getItem('thermal_printer_device');
      if (saved) {
        const { name, id } = JSON.parse(saved);
        // Request device with filter to re-use pairing
        this.device = await navigator.bluetooth.requestDevice({
          filters: [{ name }],
          optionalServices: ['000018f0-0000-1000-8000-00805f9b34fb'] // Common thermal service
        });
        this.device.addEventListener('gattserverdisconnected', () => this.scheduleReconnect());
        this.setStatus('ready');
      }
    } catch (e) {
      // No saved device or user cancelled
      this.setStatus('unpaired');
    }
  }

  /** Connect to printer — saves pairing for auto-reconnect */
  async connect() {
    if (this.isConnecting) return;
    if (this.device?.gatt?.connected) return this.setStatus('connected');

    this.isConnecting = true;
    this.setStatus('connecting');

    try {
      if (!this.device) {
        this.device = await navigator.bluetooth.requestDevice({
          filters: [
            { services: ['000018f0-0000-1000-8000-00805f9b34fb'] },
            { namePrefix: 'POS' },
            { namePrefix: 'Thermal' },
            { namePrefix: 'Printer' }
          ],
          optionalServices: ['000018f0-0000-1000-8000-00805f9b34fb']
        });
        this.device.addEventListener('gattserverdisconnected', () => this.scheduleReconnect());
      }

      const server = await this.device.gatt.connect();
      const service = await server.getPrimaryService('000018f0-0000-1000-8000-00805f9b34fb');
      this.characteristic = await service.getCharacteristic('00002af1-0000-1000-8000-00805f9b34fb');

      // Save for auto-reconnect
      localStorage.setItem('thermal_printer_device', JSON.stringify({
        name: this.device.name,
        id: this.device.id
      }));

      this.setStatus('connected');
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
    this.setStatus('disconnected');
    this.reconnectTimer = setTimeout(() => this.connect(), 3000);
  }

  /** Print raw ESC/POS bytes */
  async print(bytes) {
    if (!this.characteristic) await this.connect();
    if (!this.characteristic) throw new Error('Printer not connected');

    const mtu = 20; // BLE MTU
    for (let i = 0; i < bytes.length; i += mtu) {
      const chunk = bytes.slice(i, i + mtu);
      await this.characteristic.writeValue(chunk);
      // Small delay to avoid overwhelming printer buffer
      await new Promise(r => setTimeout(r, 10));
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
    this.setStatus('unpaired');
  }
}

// Export for global use
window.EscPos = EscPos;
window.ThermalPrinter = ThermalPrinter;