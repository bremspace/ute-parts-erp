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
      show_cabang, show_kasir = true, show_pelanggan = true, show_waktu,
      show_qr_retur = true, qr_caption, qr_size = 3, qr_url,
      paper_width = 58,
      font_size = 'small',
      strikethrough_mode = 'ascii',
    } = data;

    // Re-init with correct paper width
    this.paperWidth = Number(paper_width) === 80 ? 80 : 58;
    this.compressed = font_size === 'small';
    this.columns = this.compressed
      ? (this.paperWidth === 80 ? 64 : 42)
      : (this.paperWidth === 80 ? 48 : 32);
    this.buf = [];
    this.init();

    // ======== HEADER (Centered) ========
    this.alignCenter();
    this.bold(true).sizeDoubleWidth(true).text(nama_toko || 'UTEParts').sizeDoubleWidth(false).bold(false);
    this.text(slogan || 'SPAREPARTS/ACCESORIES/SERVICE');
    if (alamat) this.text(alamat);
    if (telepon) this.text('Telp/WA: ' + telepon);
    if (show_cabang && cabang) this.text(cabang);

    // ======== 2-COL: QR (Left) + URL/Date/Warranty (Right) ========
    const qrContent = no_transaksi || '-';
    const qrUrlDomain = qr_url || website_medsos || 'www.uteparts.id';
    const tglStr = tanggal || waktu || '';
    const captionStr = qr_caption || 'Scan QR ini untuk retur & klaim garansi';

    if (show_qr_retur && qrContent !== '-') {
      this.alignCenter();
      this.qrcode(qrContent, qr_size || 3);
      this.bold(true).text(qrContent).bold(false);
      this.text(captionStr);

      this.alignLeft();
      this.text('Web: ' + qrUrlDomain);
      if (tglStr) this.text('Tgl: ' + tglStr);
      if (garansi) this.text('Garansi: ' + garansi);
      this.text('Scan QR ini untuk retur & klaim garansi');
    } else {
      this.alignLeft();
      this.text('Web: ' + qrUrlDomain);
      if (tglStr) this.text('Tgl: ' + tglStr);
      if (garansi) this.text('Garansi: ' + garansi);
      this.text('Scan QR ini untuk retur & klaim garansi');
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

      const qtySatuan = (item.qty || 1) + (item.satuan ? ' ' + item.satuan : ' PCS');
      const harga = this.money(item.harga);
      const subtotalItem = this.money(item.subtotal);
      const hargaRetail = Number(item.harga_retail ?? 0);
      const hargaJual = Number(item.harga ?? 0);

      let leftPrice = '';
      if (hargaRetail > hargaJual) {
        const normal = this.money(hargaRetail);
        const disc = this.money(hargaJual);
        if (strikethrough_mode === 'overprint') {
          const prefix = qtySatuan + ' x ';
          const padStrike = ' '.repeat(prefix.length) + '-'.repeat(normal.length);
          this.buf.push(...padStrike.split('').map(c => c.charCodeAt(0)), 0x0d);
          leftPrice = `${prefix}${normal} ${disc}`;
        } else if (strikethrough_mode === 'combining') {
          const striked = normal.split('').map(c => c + '\u0336').join('');
          leftPrice = `${qtySatuan} x ${striked} ${disc}`;
        } else {
          leftPrice = `${qtySatuan} x (${normal}) -> ${disc}`;
        }
      } else {
        leftPrice = `${qtySatuan} x ${harga}`;
      }

      this.text(this.pad(leftPrice, subtotalItem));

      if (item.catatan) {
        for (const ln of this.wrap('  * ' + item.catatan)) {
          this.text(ln);
        }
      }
    }

    // ======== SUBTOTAL BREAKDOWN & TOTALS (Directly follows items) ========
    if (diskon_tier && Number(diskon_tier) > 0) {
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
      this.text(this.pad('Sisa Saldo Poin:', Number(saldo_poin).toLocaleString('id-ID') + ' pts'));
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

  /** Build HTML preview matching the exact paper layout */
  buildPreviewHtml(data) {
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
      show_cabang, show_kasir = true, show_pelanggan = true,
      show_qr_retur = true, qr_caption, qr_url, qr_svg,
      paper_width = 58,
    } = data;

    const money = (v) => 'Rp ' + Number(v || 0).toLocaleString('id-ID');
    const esc = (str) => String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    const widthNum = Number(paper_width) === 80 ? 80 : 58;
    const paperClass = widthNum === 80 ? 'paper-80' : 'paper-58';
    const qrContent = no_transaksi || '-';
    const qrUrlDomain = qr_url || website_medsos || 'www.uteparts.id';
    const tglStr = tanggal || waktu || '';
    const caraBayar = String(metode_bayar || metode || 'TUNAI').toUpperCase();

    // Generate fallback QR svg if none provided
    const renderedQr = qr_svg || `
      <svg width="68" height="68" viewBox="0 0 100 100" class="border border-gray-300 rounded p-0.5 bg-white">
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
      </svg>
    `;

    const lblDiskon = (diskon_persen && Number(diskon_persen) > 0)
      ? 'Diskon (' + diskon_persen + '%)'
      : 'Diskon Kasir';
    const ppnLabel = ppn_persen ? 'PPN (' + ppn_persen + '%)' : 'PPN';

    return `
<div class="thermal-receipt-doc ${paperClass}" style="width: 100%; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: ${widthNum === 80 ? '11px' : '10px'}; line-height: 1.25; color: #000; background: #fff;">
  <!-- HEADER (Centered) -->
  <div style="text-align: center; width: 100%; padding-bottom: 2px;">
    <div style="font-size: 14px; font-weight: 900; letter-spacing: 0.05em; text-transform: uppercase; text-align: center;">${esc(nama_toko || 'UTEParts')}</div>
    <div style="font-size: 9px; font-weight: 600; text-transform: uppercase; margin-top: 2px; text-align: center;">${esc(slogan || 'SPAREPARTS/ACCESORIES/SERVICE')}</div>
    ${alamat ? `<div style="font-size: 8px; color: #374151; margin-top: 2px; line-height: 1.2; text-align: center;">${esc(alamat)}</div>` : ''}
    ${telepon ? `<div style="font-size: 8.5px; color: #1f2937; text-align: center;">Telp/WA: ${esc(telepon)}</div>` : ''}
    ${show_cabang && cabang ? `<div style="font-size: 8.5px; font-weight: 600; text-align: center;">${esc(cabang)}</div>` : ''}
  </div>

  <!-- 2-COL: QR (Left) + URL/Date/Warranty Note (Right) -->
  <div style="display: flex; flex-direction: row; align-items: center; justify-content: space-between; width: 100%; gap: 8px; padding: 4px 0;">
    ${show_qr_retur && qrContent !== '-' ? `
      <div style="width: 68px; height: 68px; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
        ${renderedQr}
      </div>
    ` : ''}
    <div style="flex: 1; min-width: 0; text-align: left; font-size: 8.5px; line-height: 1.25;">
      <div style="font-weight: 600; color: #111827; word-break: break-all;">${esc(qrUrlDomain)}</div>
      ${tglStr ? `<div style="color: #374151;">${esc(tglStr)}</div>` : ''}
      <div style="font-size: 8px; color: #4b5563; margin-top: 2px;">${esc(qr_caption || 'Scan QR ini untuk retur & klaim garansi')}</div>
    </div>
  </div>

  <div style="border-top: 1px dashed #000; width: 100%; margin: 4px 0;"></div>

  <!-- TRANSACTION INFO (Left-Right) -->
  <div style="width: 100%; font-size: 9px; line-height: 1.35;">
    <div style="display: flex; justify-content: space-between; width: 100%;">
      <span style="color: #4b5563;">No. TRX:</span>
      <span style="font-weight: 700; color: #111827;">${esc(qrContent)}</span>
    </div>
    ${sumber && !['pos', 'offline'].includes(sumber.toLowerCase()) ? `
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">Pesanan:</span>
        <span>${esc(sumber.toUpperCase())}</span>
      </div>
    ` : ''}
    ${no_tiket ? `
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">No. Tiket:</span>
        <span style="font-weight: 700;">${esc(no_tiket)}</span>
      </div>
    ` : ''}
    ${jenis_hp ? `
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">Unit:</span>
        <span>${esc(jenis_hp)}</span>
      </div>
    ` : ''}
    ${kurir ? `
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">${is_pickup ? 'Ambil:' : 'Kurir:'}</span>
        <span>${esc(kurir + (resi && resi !== '-' ? ' (' + resi + ')' : ''))}</span>
      </div>
    ` : ''}
    ${show_kasir && kasir ? `
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">Kasir:</span>
        <span>${esc(kasir)}</span>
      </div>
    ` : ''}
    ${show_pelanggan ? `
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">Pelanggan:</span>
        <span>${esc(pelanggan || 'Umum')}</span>
      </div>
      ${tier && tier !== 'Retail' && tier !== 'Umum' ? `
        <div style="display: flex; justify-content: space-between; width: 100%;">
          <span style="color: #4b5563;">${['reseller', 'agen'].includes(tipe_konsumen) ? 'Mitra:' : 'Tier:'}</span>
          <span style="font-weight: 700;">${esc(tier)}</span>
        </div>
      ` : ''}
    ` : ''}
  </div>

  <!-- ITEMS -->
  <div style="width: 100%; margin: 4px 0;">
    ${items.map(item => {
      const nama = item.nama || 'Item';
      const qtySatuan = (item.qty || 1) + (item.satuan ? ' ' + item.satuan : ' PCS');
      const harga = money(item.harga);
      const subtotalItem = Number(item.subtotal || 0).toLocaleString('id-ID');
      const hargaRetail = Number(item.harga_retail ?? 0);
      const hargaJual = Number(item.harga ?? 0);

      return `
        <div style="width: 100%; margin-bottom: 4px;">
          <div style="font-weight: 700; font-size: 9.5px; line-height: 1.25; word-break: break-word;">${esc(nama)}</div>
          <div style="display: flex; justify-content: space-between; align-items: baseline; width: 100%; font-size: 9px; margin-top: 1px;">
            <span>
              ${qtySatuan} x ${hargaRetail > hargaJual ? `
                <span style="text-decoration: line-through; opacity: 0.6; margin-right: 2px;">${money(hargaRetail)}</span>
                <span style="font-weight: 700;">${money(hargaJual)}</span>
              ` : `<span>${harga}</span>`}
            </span>
            <span style="font-weight: 700; text-align: right; margin-left: 4px;">${subtotalItem}</span>
          </div>
          ${item.catatan ? `<div style="font-size: 8px; color: #4b5563; font-style: italic; margin-top: 1px;">* ${esc(item.catatan)}</div>` : ''}
        </div>
      `;
    }).join('')}
  </div>

  <!-- SUBTOTAL BREAKDOWN & TOTALS (Directly follows items without extra dashed line) -->
  <div style="width: 100%; font-size: 9px; line-height: 1.35;">
    ${diskon_tier && Number(diskon_tier) > 0 ? `
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">Subtotal Retail:</span>
        <span>${money(Number(subtotal_retail ?? 0) || (Number(subtotal ?? 0) + Number(diskon_tier)))}</span>
      </div>
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">${tier && tier !== 'Retail' ? 'Pot ' + esc(tier) + ':' : 'Pot Mitra:'}</span>
        <span style="color: #dc2626;">-${money(diskon_tier)}</span>
      </div>
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">Subtotal:</span>
        <span>${money(subtotal ?? total)}</span>
      </div>
    ` : `
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">Subtotal:</span>
        <span>${money(subtotal ?? total)}</span>
      </div>
    `}

    ${diskon_partai && Number(diskon_partai) > 0 ? `
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">Diskon Partai:</span>
        <span style="color: #dc2626;">-${money(diskon_partai)}</span>
      </div>
    ` : ''}
    ${diskon_poin && Number(diskon_poin) > 0 ? `
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">Tukar Poin (${poin_ditukar || 0}pts):</span>
        <span style="color: #dc2626;">-${money(diskon_poin)}</span>
      </div>
    ` : ''}
    ${diskon && Number(diskon) > 0 ? `
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">${esc(lblDiskon)}:</span>
        <span style="color: #dc2626;">-${money(diskon)}</span>
      </div>
    ` : ''}
    ${pajak && Number(pajak) > 0 ? `
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">${esc(ppnLabel)}:</span>
        <span>+${money(pajak)}</span>
      </div>
    ` : ''}
    ${ongkir && Number(ongkir) > 0 ? `
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">Ongkir:</span>
        <span>+${money(ongkir)}</span>
      </div>
    ` : ''}

    <div style="display: flex; justify-content: space-between; align-items: baseline; width: 100%; font-size: 13px; font-weight: 900; padding: 2px 0;">
      <span>TOTAL:</span>
      <span>${money(total)}</span>
    </div>

    ${is_piutang ? `
      <div style="display: flex; justify-content: space-between; width: 100%; font-weight: 700; color: #b45309;">
        <span>Status:</span>
        <span>BELUM LUNAS (PIUTANG)</span>
      </div>
      ${jatuh_tempo ? `
        <div style="display: flex; justify-content: space-between; width: 100%;">
          <span style="color: #4b5563;">Jatuh Tempo:</span>
          <span>${esc(jatuh_tempo)}</span>
        </div>
      ` : ''}
      <div style="display: flex; justify-content: space-between; width: 100%; font-weight: 700;">
        <span style="color: #4b5563;">Sisa Tag:</span>
        <span>${money(sisa_piutang ?? total)}</span>
      </div>
    ` : `
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">BAYAR (${esc(caraBayar)}):</span>
        <span style="font-weight: 600;">${money(bayar || total)}</span>
      </div>
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">KEMBALI:</span>
        <span style="font-weight: 600;">${money(kembali ?? 0)}</span>
      </div>
    `}

    ${saldo_poin !== undefined ? `
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">Sisa Saldo Poin:</span>
        <span>${Number(saldo_poin).toLocaleString('id-ID')} pts</span>
      </div>
    ` : ''}
    ${hemat_total && Number(hemat_total) > 0 ? `
      <div style="display: flex; justify-content: space-between; width: 100%; font-weight: 700; color: #059669;">
        <span>HEMAT:</span>
        <span>${money(hemat_total)}</span>
      </div>
    ` : ''}
    ${komisi_mitra_nominal && Number(komisi_mitra_nominal) > 0 ? `
      <div style="display: flex; justify-content: space-between; width: 100%; color: #2563eb;">
        <span style="color: #4b5563;">Komisi:</span>
        <span>+${money(komisi_mitra_nominal)} (Saldo)</span>
      </div>
    ` : ''}
  </div>

  <div style="border-top: 1px dashed #000; width: 100%; margin: 4px 0;"></div>

  <!-- FOOTER (Centered) -->
  <div style="text-align: center; width: 100%; font-size: 8.5px; color: #374151; line-height: 1.3; padding-top: 2px;">
    ${garansi ? `<div style="font-weight: 700; color: #111827; text-align: center;">Garansi: ${esc(garansi)}</div>` : ''}
    <div style="text-align: center;">${esc(footer_text || 'Terima kasih atas kunjungan Anda!')}</div>
    <div style="font-size: 8px; color: #4b5563; text-align: center;">${esc(kebijakan_retur || 'Segel toko wajib utuh & fisik barang tidak cacat.')}</div>
    ${catatan_kaki ? `<div style="font-size: 8px; color: #6b7280; margin-top: 2px; text-align: center;">${esc(catatan_kaki)}</div>` : ''}
    ${is_piutang ? `
      <div style="margin-top: 8px; border-top: 1px dotted #d1d5db; padding-top: 4px;">
        <div style="font-style: italic; font-size: 7.5px; text-align: center;">"Barang diterima & berjanji melunasi sblm jatuh tempo"</div>
        <div style="display: flex; justify-content: space-between; width: 100%; margin-top: 8px; padding: 0 8px;">
          <div>Penerima,</div>
          <div>Kasir,</div>
        </div>
        <div style="display: flex; justify-content: space-between; width: 100%; margin-top: 16px; padding: 0 8px;">
          <div>(............)</div>
          <div>(............)</div>
        </div>
      </div>
    ` : ''}
  </div>
</div>
`;
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
        const esc = new window.EscPos(paperWidth);
        const bytes = esc.buildReceipt(this.data);

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
      const html = esc.buildPreviewHtml(this.data);

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
