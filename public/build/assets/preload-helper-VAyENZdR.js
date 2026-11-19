var e=class{constructor(e=58){this.paperWidth=Number(e)===80?80:58,this.compressed=!0,this.columns=this.compressed?this.paperWidth===80?64:42:this.paperWidth===80?48:32,this.buf=[],this.init()}init(){return this.buf=[27,64],this.compressed&&this.buf.push(29,33,1),this}alignCenter(){return this.buf.push(27,97,1),this}alignLeft(){return this.buf.push(27,97,0),this}alignRight(){return this.buf.push(27,97,2),this}bold(e){return this.buf.push(27,69,+!!e),this}size(e){return this.buf.push(29,33,e),this}sizeDoubleWidth(e){return this.size(e?128:0),this}sizeDoubleHeight(e){return this.size(e?16:0),this}text(e){return this.buf.push(...this.ascii(e),10),this}rule(){return this.text(`-`.repeat(this.columns))}feed(e){return this.buf.push(...Array(e).fill(10)),this}cut(){return this.buf.push(29,86,66,0),this}qrcode(e,t=3){let n=this.ascii(e);if(!n||!n.length)return this;let r=n.length+3,i=r&255,a=r>>8&255,o=Math.max(2,Math.min(8,t));return this.buf.push(29,40,107,4,0,49,65,50,0),this.buf.push(29,40,107,3,0,49,67,o),this.buf.push(29,40,107,3,0,49,69,49),this.buf.push(29,40,107,i,a,49,80,48,...n),this.buf.push(29,40,107,3,0,49,81,48),this}textStrikethrough(e){return this.buf.push(27,66,1),this.buf.push(...this.ascii(e),10),this.buf.push(27,66,0),this}ascii(e){return e?String(e).replace(/[éèêë]/g,`e`).replace(/[áàâä]/g,`a`).replace(/[íìîï]/g,`i`).replace(/[óòôö]/g,`o`).replace(/[úùûü]/g,`u`).replace(/ñ/g,`n`).replace(/ç/g,`c`).replace(/[±×÷]/g,e=>({"±":`+/-`,"×":`x`,"÷":`/`})[e]).replace(/[–—]/g,`-`).replace(/[’‘]/g,`'`).replace(/[“”]/g,`"`).replace(/→/g,`->`).replace(/[^\x20-\x7E]/g,``).split(``).map(e=>e.charCodeAt(0)):[]}money(e){return`Rp `+Number(e||0).toLocaleString(`id-ID`)}numberOnly(e){return Number(e||0).toLocaleString(`id-ID`)}unitLabel(e){let t=this.clean(e||`PCS`);return t.length<=4?t.toUpperCase():t}raw(e){return this.buf.push(...this.ascii(e)),this}escB(e){return this.buf.push(27,66,+!!e),this}pad(e,t){e=this.clean(e),t=this.clean(t);let n=e.length+t.length;if(n>=this.columns){let n=Math.max(0,this.columns-t.length);return e+`
`+` `.repeat(n)+t}let r=this.columns-n;return e+` `.repeat(r)+t}clean(e){return e?String(e).replace(/[éèêë]/g,`e`).replace(/[áàâä]/g,`a`).replace(/[íìîï]/g,`i`).replace(/[óòôö]/g,`o`).replace(/[úùûü]/g,`u`).replace(/ñ/g,`n`).replace(/ç/g,`c`).replace(/[±×÷]/g,e=>({"±":`+/-`,"×":`x`,"÷":`/`})[e]).replace(/[–—]/g,`-`).replace(/[’‘]/g,`'`).replace(/[“”]/g,`"`).replace(/→/g,`->`).replace(/[^\x20-\x7E]/g,``):``}wrap(e){let t=this.clean(e),n=[],r=t;for(;r.length>this.columns;){let e=this.columns;for(let t=this.columns;t>=1;t--)if(`/,-) `.includes(r[t-1])){e=t;break}n.push(r.slice(0,e).replace(/\s+$/,``)),r=r.slice(e)}return r.trim()&&n.push(r),n}buildReceipt(e,t=null){let{items:n=[],total:r=0,bayar:i=0,kembali:a=0,no_transaksi:o=`-`,metode_bayar:s=`TUNAI`,metode:c=`TUNAI`,tanggal:l,waktu:u,kasir:d,cabang:f,pelanggan:ee,tier:p,tipe_konsumen:te,saldo_poin:m,poin_ditukar:ne,diskon_tier:h,diskon_partai:g,diskon_poin:_,diskon:v,diskon_persen:y,diskon_nominal:re,pajak:b,ppn_persen:x,ongkir:S,subtotal:C,subtotal_retail:ie,hemat_total:w,is_piutang:T,jatuh_tempo:E,sisa_piutang:ae,komisi_mitra_nominal:D,no_tiket:O,jenis_hp:k,kurir:A,is_pickup:oe,resi:j,sumber:M,garansi:N,label_struk:P,no_transaksi_asal:F,goodwill:I,voucher:L,komplain:R,footer_text:z,kebijakan_retur:B,catatan_kaki:V,nama_toko:se,slogan:ce,alamat:H,telepon:U,website_medsos:le,show_cabang:W,show_kasir:G=!0,show_pelanggan:K=!0,show_waktu:q=!0,show_subtotal_breakdown:J=!0,show_qr_retur:Y=!0,qr_caption:ue,qr_size:de=3,qr_url:fe,font_size:pe=`small`,strikethrough_mode:X=`esc_b`}=e;this.paperWidth=Number(t??e.paper_width)===80?80:58,this.compressed=pe===`small`,this.columns=this.compressed?this.paperWidth===80?64:42:this.paperWidth===80?48:32,this.buf=[],this.init();let me=!!(Y&&o&&o!==`-`),he=!!(q&&(l||u));this.alignCenter(),this.bold(!0).sizeDoubleWidth(!0).text(se||`BREMSPACE`).sizeDoubleWidth(!1).bold(!1),this.text(ce||`Pusat Sparepart & Servis Gadget`),H&&this.text(H),U&&this.text(`Telp/WA: `+U),W!==!1&&W!==0&&W!==`0`&&f&&this.text(f);let Z=o||`-`,Q=le||fe||`www.bremspace.com`,ge=l||u||``,$=ue||`Scan QR ini untuk retur & klaim garansi`;if(me?(this.alignCenter(),this.qrcode(Z,de||3),this.bold(!0).text(Z).bold(!1),$&&this.text($),this.alignLeft()):this.alignLeft(),this.text(`Web: `+Q),he&&this.text(`Tgl: `+ge),this.rule(),this.alignLeft(),P&&(this.alignCenter(),this.bold(!0).text(P).bold(!1),this.alignLeft()),this.text(this.pad(`No. TRX:`,Z)),F&&F!==`-`&&this.text(this.pad(`Asal TRX:`,F)),M&&![`pos`,`offline`].includes(M.toLowerCase())&&this.text(this.pad(`Pesanan:`,M.toUpperCase())),O&&this.bold(!0).text(this.pad(`No. Tiket:`,O)).bold(!1),k&&this.text(this.pad(`Unit:`,k)),A){let e=oe?`Ambil:`:`Kurir:`,t=j&&j!==`-`?` (`+j+`)`:``;this.text(this.pad(e,A+t))}if(G!==!1&&G!==0&&G!==`0`&&d&&this.text(this.pad(`Kasir:`,d)),K!==!1&&K!==0&&K!==`0`){let e=ee||`Umum`;if(this.text(this.pad(`Pelanggan:`,e)),p&&p!==`Retail`&&p!==`Umum`){let e=[`reseller`,`agen`].includes(te)?`Mitra:`:`Tier:`;this.bold(!0).text(this.pad(e,p)).bold(!1)}}for(let e of n){let t=e.nama||`Item`;for(let e of this.wrap(t))this.bold(!0).text(e).bold(!1);let n=(e.qty||1)+` `+this.unitLabel(e.satuan),r=this.money(e.harga),i=this.numberOnly(e.subtotal),a=Number(e.harga_retail??0),o=Number(e.harga??0);if(a>o&&X===`esc_b`){let e=this.money(a),t=this.money(o),r=n+` x `,s=r.length+e.length+1+t.length+i.length;if(s>=this.columns){this.raw(r),this.escB(!0).raw(e).escB(!1),this.raw(` `+t+`
`);let n=Math.max(0,this.columns-i.length);this.buf.push(...this.ascii(` `.repeat(n)+i),10)}else{let n=this.columns-s;this.raw(r),this.escB(!0).raw(e).escB(!1),this.raw(` `+t),this.buf.push(...this.ascii(` `.repeat(n)+i),10)}}else a>o?this.text(this.pad(`${n} x (${this.money(a)}) -> ${this.money(o)}`,i)):this.text(this.pad(`${n} x ${r}`,i));if(e.catatan)for(let t of this.wrap(`  * `+e.catatan))this.text(t)}if(J!==!1&&J!==0&&J!==`0`&&h&&Number(h)>0){let e=Number(ie??0)||Number(C??0)+Number(h);this.text(this.pad(`Subtotal Retail:`,this.money(e)));let t=p&&p!==`Retail`?`Pot `+p+`:`:[`reseller`,`agen`].includes(te)?`Pot Mitra:`:`Pot Tier:`;this.text(this.pad(t,`-`+this.money(h))),this.text(this.pad(`Subtotal:`,this.money(C??r)))}else this.text(this.pad(`Subtotal:`,this.money(C??r)));if(g&&Number(g)>0&&this.text(this.pad(`Diskon Partai:`,`-`+this.money(g))),_&&Number(_)>0&&this.text(this.pad(`Tukar Poin (`+(ne||0)+`pts):`,`-`+this.money(_))),v&&Number(v)>0){let e=y&&Number(y)>0?`Diskon (`+y+`%):`:`Diskon Kasir:`;this.text(this.pad(e,`-`+this.money(v)))}if(b&&Number(b)>0){let e=x?`PPN (`+x+`%):`:`PPN:`;this.text(this.pad(e,`+`+this.money(b)))}if(S&&Number(S)>0&&this.text(this.pad(`Ongkir:`,`+`+this.money(S))),this.bold(!0).sizeDoubleHeight(!0).text(this.pad(`TOTAL:`,this.money(r))).sizeDoubleHeight(!1).bold(!1),T)this.text(this.pad(`Status:`,`BELUM LUNAS (PIUTANG)`)),E&&this.text(this.pad(`Jatuh Tempo:`,E)),this.text(this.pad(`Sisa Tag:`,this.money(ae??r)));else{let e=String(s||c||`TUNAI`).toUpperCase();this.text(this.pad(`BAYAR (${e}):`,this.money(i||r))),this.text(this.pad(`KEMBALI:`,this.money(a??0)))}if(m!==void 0&&this.text(this.pad(`Sisa Saldo Poin:`,this.numberOnly(m)+` pts`)),w&&Number(w)>0&&this.bold(!0).text(this.pad(`HEMAT:`,this.money(w))).bold(!1),D&&Number(D)>0&&this.text(this.pad(`Komisi:`,`+`+this.money(D)+` (Saldo)`)),I&&this.text(this.pad(`Goodwill:`,String(I))),L&&this.text(this.pad(`Voucher:`,String(L))),R)for(let e of this.wrap(String(R)))this.text(e);if(this.rule(),this.alignCenter(),N&&this.text(`Garansi: `+N),z&&this.text(z),B&&this.text(B),V)for(let e of this.wrap(V))this.text(e);return T&&(this.feed(1),this.text(`"Barang diterima & berjanji melunasi sblm jatuh tempo"`),this.feed(1),this.text(`Penerima,              Kasir,`),this.feed(2),this.text(`(............)        (............)`)),this.feed(1).cut(),new Uint8Array(this.buf)}build(e){return this.buildReceipt(e)}renderPreviewHtml(e){return this.buildPreviewHtml(e)}buildPreviewHtml(e,t=null){let{items:n=[],total:r=0,bayar:i=0,kembali:a=0,no_transaksi:o=`-`,metode_bayar:s=`TUNAI`,metode:c=`TUNAI`,tanggal:l,waktu:u,kasir:d,cabang:f,pelanggan:ee,tier:p,tipe_konsumen:te,saldo_poin:m,poin_ditukar:ne,diskon_tier:h,diskon_partai:g,diskon_poin:_,diskon:v,diskon_persen:y,diskon_nominal:re,pajak:b,ppn_persen:x,ongkir:S,subtotal:C,subtotal_retail:ie,hemat_total:w,is_piutang:T,jatuh_tempo:E,sisa_piutang:ae,komisi_mitra_nominal:D,no_tiket:O,jenis_hp:k,kurir:A,is_pickup:oe,resi:j,sumber:M,garansi:N,label_struk:P,no_transaksi_asal:F,goodwill:I,voucher:L,komplain:R,footer_text:z,kebijakan_retur:B,catatan_kaki:V,nama_toko:se,slogan:ce,alamat:H,telepon:U,website_medsos:le,show_cabang:W,show_kasir:G=!0,show_pelanggan:K=!0,show_waktu:q=!0,show_subtotal_breakdown:J=!0,show_qr_retur:Y=!0,qr_caption:ue,qr_url:de,qr_svg:fe,paper_width:pe=58}=e,X=e=>`Rp `+Number(e||0).toLocaleString(`id-ID`),me=e=>Number(e||0).toLocaleString(`id-ID`),he=e=>{let t=String(e||`PCS`);return t.length<=4?t.toUpperCase():t},Z=e=>String(e||``).replace(/&/g,`&amp;`).replace(/</g,`&lt;`).replace(/>/g,`&gt;`).replace(/"/g,`&quot;`),Q=Number(t??pe)===80?80:58,ge=Q===80?`paper-80`:`paper-58`,$=o||`-`,_e=le||de||`www.bremspace.com`,ve=q!==!1&&q!==0&&q!==`0`&&(l||u)||``,ye=String(s||c||`TUNAI`).toUpperCase(),be=fe||`
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
    `,xe=y&&Number(y)>0?`Diskon (`+y+`%)`:`Diskon Kasir`,Se=x?`PPN (`+x+`%)`:`PPN`;return`
<div class="thermal-receipt-doc ${ge}" style="width: 100%; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: ${Q===80?`11px`:`10px`}; line-height: 1.25; color: #000; background: #fff;">
  <!-- HEADER (Centered) -->
  <div style="text-align: center; width: 100%; padding-bottom: 2px;">
    <div style="font-size: 14px; font-weight: 900; letter-spacing: 0.05em; text-transform: uppercase; text-align: center;">${Z(se||`BREMSPACE`)}</div>
    <div style="font-size: 9px; font-weight: 600; text-transform: uppercase; margin-top: 2px; text-align: center;">${Z(ce||`Pusat Sparepart & Servis Gadget`)}</div>
    ${H?`<div style="font-size: 8px; color: #374151; margin-top: 2px; line-height: 1.2; text-align: center;">${Z(H)}</div>`:``}
    ${U?`<div style="font-size: 8.5px; color: #1f2937; text-align: center;">Telp/WA: ${Z(U)}</div>`:``}
    ${W!==!1&&W!==0&&W!==`0`&&f?`<div style="font-size: 8.5px; font-weight: 600; text-align: center;">${Z(f)}</div>`:``}
  </div>

  <!-- 2-COL: QR (Left) + URL/Date/Warranty Note (Right) -->
  <div style="display: flex; flex-direction: row; align-items: center; justify-content: space-between; width: 100%; gap: 8px; padding: 4px 0;">
    ${Y&&$!==`-`?`
      <div style="width: 68px; height: 68px; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
        ${be}
      </div>
    `:``}
    <div style="flex: 1; min-width: 0; text-align: left; font-size: 8.5px; line-height: 1.25;">
      <div style="font-weight: 600; color: #111827; word-break: break-all;">${Z(_e)}</div>
      ${ve?`<div style="color: #374151;">${Z(ve)}</div>`:``}
      ${Y?`<div style="font-size: 8px; color: #4b5563; margin-top: 2px;">${Z(ue||`Scan QR ini untuk retur & klaim garansi`)}</div>`:``}
    </div>
  </div>

  <div style="border-top: 1px dashed #000; width: 100%; margin: 4px 0;"></div>

  <!-- TRANSACTION INFO (Left-Right) -->
  <div style="width: 100%; font-size: 9px; line-height: 1.35;">
    ${P?`
      <div style="text-align: center; font-weight: 800; font-size: 9.5px; padding: 2px 0; color: #111827; text-transform: uppercase;">
        ${Z(P)}
      </div>
    `:``}
    <div style="display: flex; justify-content: space-between; width: 100%;">
      <span style="color: #4b5563;">No. TRX:</span>
      <span style="font-weight: 700; color: #111827;">${Z($)}</span>
    </div>
    ${F&&F!==`-`?`
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">Asal TRX:</span>
        <span style="font-weight: 600;">${Z(F)}</span>
      </div>
    `:``}
    ${M&&![`pos`,`offline`].includes(M.toLowerCase())?`
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">Pesanan:</span>
        <span>${Z(M.toUpperCase())}</span>
      </div>
    `:``}
    ${O?`
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">No. Tiket:</span>
        <span style="font-weight: 700;">${Z(O)}</span>
      </div>
    `:``}
    ${k?`
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">Unit:</span>
        <span>${Z(k)}</span>
      </div>
    `:``}
    ${A?`
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">${oe?`Ambil:`:`Kurir:`}</span>
        <span>${Z(A+(j&&j!==`-`?` (`+j+`)`:``))}</span>
      </div>
    `:``}
    ${G!==!1&&G!==0&&G!==`0`&&d?`
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">Kasir:</span>
        <span>${Z(d)}</span>
      </div>
    `:``}
    ${K!==!1&&K!==0&&K!==`0`?`
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">Pelanggan:</span>
        <span>${Z(ee||`Umum`)}</span>
      </div>
      ${p&&p!==`Retail`&&p!==`Umum`?`
        <div style="display: flex; justify-content: space-between; width: 100%;">
          <span style="color: #4b5563;">${[`reseller`,`agen`].includes(te)?`Mitra:`:`Tier:`}</span>
          <span style="font-weight: 700;">${Z(p)}</span>
        </div>
      `:``}
    `:``}
  </div>

  <!-- ITEMS -->
  <div style="width: 100%; margin: 4px 0;">
    ${n.map(e=>{let t=e.nama||`Item`,n=(e.qty||1)+` `+he(e.satuan),r=X(e.harga),i=me(e.subtotal),a=Number(e.harga_retail??0),o=Number(e.harga??0);return`
        <div style="width: 100%; margin-bottom: 4px;">
          <div style="font-weight: 700; font-size: 9.5px; line-height: 1.25; word-break: break-word;">${Z(t)}</div>
          <div style="display: flex; justify-content: space-between; align-items: baseline; width: 100%; font-size: ${Q===80?`9.5px`:`9px`}; margin-top: 1px; gap: 4px;">
            <span style="flex: 1; min-width: 0; word-break: break-word;">
              ${n} x ${a>o?`
                <span style="text-decoration: line-through; opacity: 0.75; margin-right: 2px;">${X(a)}</span>
                <span style="font-weight: 700;">${X(o)}</span>
              `:`<span>${r}</span>`}
            </span>
            <span style="font-weight: 700; text-align: right; white-space: nowrap; flex-shrink: 0;" class="tabular-nums">${i}</span>
          </div>
          ${e.catatan?`<div style="font-size: 8px; color: #4b5563; font-style: italic; margin-top: 1px;">* ${Z(e.catatan)}</div>`:``}
        </div>
      `}).join(``)}
  </div>

  <!-- SUBTOTAL BREAKDOWN & TOTALS (Directly follows items without extra dashed line) -->
  <div style="width: 100%; font-size: 9px; line-height: 1.35;">
    ${J!==!1&&J!==0&&J!==`0`&&h&&Number(h)>0?`
      <div style="display: flex; justify-content: space-between; width: 100%; gap: 4px;">
        <span style="color: #4b5563;">Subtotal Retail:</span>
        <span style="white-space: nowrap; flex-shrink: 0;">${X(Number(ie??0)||Number(C??0)+Number(h))}</span>
      </div>
      <div style="display: flex; justify-content: space-between; width: 100%; gap: 4px;">
        <span style="color: #4b5563;">${p&&p!==`Retail`?`Pot `+Z(p)+`:`:`Pot Mitra:`}</span>
        <span style="color: #dc2626; white-space: nowrap; flex-shrink: 0;">-${X(h)}</span>
      </div>
      <div style="display: flex; justify-content: space-between; width: 100%; gap: 4px;">
        <span style="color: #4b5563;">Subtotal:</span>
        <span style="white-space: nowrap; flex-shrink: 0;">${X(C??r)}</span>
      </div>
    `:`
      <div style="display: flex; justify-content: space-between; width: 100%; gap: 4px;">
        <span style="color: #4b5563;">Subtotal:</span>
        <span style="white-space: nowrap; flex-shrink: 0;">${X(C??r)}</span>
      </div>
    `}

    ${g&&Number(g)>0?`
      <div style="display: flex; justify-content: space-between; width: 100%; gap: 4px;">
        <span style="color: #4b5563;">Diskon Partai:</span>
        <span style="color: #dc2626; white-space: nowrap; flex-shrink: 0;">-${X(g)}</span>
      </div>
    `:``}
    ${_&&Number(_)>0?`
      <div style="display: flex; justify-content: space-between; width: 100%; gap: 4px;">
        <span style="color: #4b5563;">Tukar Poin (${ne||0}pts):</span>
        <span style="color: #dc2626; white-space: nowrap; flex-shrink: 0;">-${X(_)}</span>
      </div>
    `:``}
    ${v&&Number(v)>0?`
      <div style="display: flex; justify-content: space-between; width: 100%; gap: 4px;">
        <span style="color: #4b5563;">${Z(xe)}:</span>
        <span style="color: #dc2626; white-space: nowrap; flex-shrink: 0;">-${X(v)}</span>
      </div>
    `:``}
    ${b&&Number(b)>0?`
      <div style="display: flex; justify-content: space-between; width: 100%; gap: 4px;">
        <span style="color: #4b5563;">${Z(Se)}:</span>
        <span style="white-space: nowrap; flex-shrink: 0;">+${X(b)}</span>
      </div>
    `:``}
    ${S&&Number(S)>0?`
      <div style="display: flex; justify-content: space-between; width: 100%; gap: 4px;">
        <span style="color: #4b5563;">Ongkir:</span>
        <span style="white-space: nowrap; flex-shrink: 0;">+${X(S)}</span>
      </div>
    `:``}

    <div style="display: flex; justify-content: space-between; align-items: baseline; width: 100%; font-size: 13px; font-weight: 900; padding: 2px 0; gap: 4px;">
      <span>TOTAL:</span>
      <span style="white-space: nowrap; flex-shrink: 0;">${X(r)}</span>
    </div>

    ${T?`
      <div style="display: flex; justify-content: space-between; width: 100%; font-weight: 700; color: #b45309; gap: 4px;">
        <span>Status:</span>
        <span style="white-space: nowrap; flex-shrink: 0;">BELUM LUNAS (PIUTANG)</span>
      </div>
      ${E?`
        <div style="display: flex; justify-content: space-between; width: 100%; gap: 4px;">
          <span style="color: #4b5563;">Jatuh Tempo:</span>
          <span style="white-space: nowrap; flex-shrink: 0;">${Z(E)}</span>
        </div>
      `:``}
      <div style="display: flex; justify-content: space-between; width: 100%; font-weight: 700; gap: 4px;">
        <span style="color: #4b5563;">Sisa Tag:</span>
        <span style="white-space: nowrap; flex-shrink: 0;">${X(ae??r)}</span>
      </div>
    `:`
      <div style="display: flex; justify-content: space-between; width: 100%; gap: 4px;">
        <span style="color: #4b5563;">BAYAR (${Z(ye)}):</span>
        <span style="font-weight: 600; white-space: nowrap; flex-shrink: 0;">${X(i||r)}</span>
      </div>
      <div style="display: flex; justify-content: space-between; width: 100%; gap: 4px;">
        <span style="color: #4b5563;">KEMBALI:</span>
        <span style="font-weight: 600; white-space: nowrap; flex-shrink: 0;">${X(a??0)}</span>
      </div>
    `}

    ${m===void 0?``:`
      <div style="display: flex; justify-content: space-between; width: 100%; gap: 4px;">
        <span style="color: #4b5563;">Sisa Saldo Poin:</span>
        <span style="white-space: nowrap; flex-shrink: 0;">${Number(m).toLocaleString(`id-ID`)} pts</span>
      </div>
    `}
    ${w&&Number(w)>0?`
      <div style="display: flex; justify-content: space-between; width: 100%; font-weight: 700; color: #059669; gap: 4px;">
        <span>HEMAT:</span>
        <span style="white-space: nowrap; flex-shrink: 0;">${X(w)}</span>
      </div>
    `:``}
    ${D&&Number(D)>0?`
      <div style="display: flex; justify-content: space-between; width: 100%; color: #2563eb; gap: 4px;">
        <span style="color: #4b5563;">Komisi:</span>
        <span style="white-space: nowrap; flex-shrink: 0;">+${X(D)} (Saldo)</span>
      </div>
    `:``}
    ${I?`
      <div style="display: flex; justify-content: space-between; width: 100%; color: #059669; gap: 4px;">
        <span style="color: #4b5563;">Goodwill:</span>
        <span style="white-space: nowrap; flex-shrink: 0;">${Z(I)}</span>
      </div>
    `:``}
    ${L?`
      <div style="display: flex; justify-content: space-between; width: 100%; color: #059669; gap: 4px;">
        <span style="color: #4b5563;">Voucher:</span>
        <span style="white-space: nowrap; flex-shrink: 0;">${Z(L)}</span>
      </div>
    `:``}
    ${R?`
      <div style="font-size: 8px; color: #b45309; margin-top: 2px;">* ${Z(R)}</div>
    `:``}
  </div>

  <div style="border-top: 1px dashed #000; width: 100%; margin: 4px 0;"></div>

  <!-- FOOTER (Centered) -->
  <div style="text-align: center; width: 100%; font-size: 8.5px; color: #374151; line-height: 1.3; padding-top: 2px;">
    ${N?`<div style="font-weight: 700; color: #111827; text-align: center;">Garansi: ${Z(N)}</div>`:``}
    ${z?`<div style="text-align: center; font-weight: 500; color: #374151;">${Z(z)}</div>`:``}
    ${B?`<div style="font-size: 8px; color: #4b5563; text-align: center;">${Z(B)}</div>`:``}
    ${V?`<div style="font-size: 8px; color: #6b7280; margin-top: 2px; text-align: center;">${Z(V)}</div>`:``}
    ${T?`
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
    `:``}
  </div>
</div>
`}},t=class{constructor(){this.device=null,this.characteristic=null,this.reconnectTimer=null,this.isConnecting=!1,this.onStatusChange=null,this.onError=null,this.status=`unpaired`,this.serviceUuids=[`000018f0-0000-1000-8000-00805f9b34fb`,`e7810a71-73ae-499d-8c15-faa9aef0c3f2`,`49535343-fe7d-4ae5-8fa9-9fafd205e455`,`0000ff00-0000-1000-8000-00805f9b34fb`,`0000fee7-0000-1000-8000-00805f9b34fb`],this.loadSavedDevice()}async loadSavedDevice(){try{let e=localStorage.getItem(`thermal_printer_device`);if(!e){this.setStatus(`unpaired`);return}let{name:t,id:n}=JSON.parse(e);if(navigator.bluetooth&&typeof navigator.bluetooth.getDevices==`function`){let e=(await navigator.bluetooth.getDevices()).find(e=>e.id===n||e.name===t);if(e){this.device=e,this.device.addEventListener(`gattserverdisconnected`,()=>this.scheduleReconnect()),await this.connectGatt();return}}this.setStatus(`saved`)}catch(e){console.warn(`Silent BLE reconnect skipped:`,e),this.setStatus(`unpaired`)}}async connectGatt(){if(this.device?.gatt){if(this.device.gatt.connected&&this.characteristic){this.setStatus(`connected`);return}this.isConnecting=!0,this.setStatus(`connecting`);try{let e=await this.device.gatt.connect();this.characteristic=await this.discoverWritableCharacteristic(e),localStorage.setItem(`thermal_printer_device`,JSON.stringify({name:this.device.name,id:this.device.id})),localStorage.setItem(`ute_ble_printer_name`,this.device.name||`Bluetooth Printer`),this.setStatus(`connected`)}catch(e){throw console.error(`GATT connection error:`,e),this.setStatus(`error`),this.onError?.(e),e}finally{this.isConnecting=!1}}}async discoverWritableCharacteristic(e){for(let t of this.serviceUuids)try{let n=await(await e.getPrimaryService(t)).getCharacteristics();for(let e of n)if(e.properties.write||e.properties.writeWithoutResponse)return e}catch{}try{let t=await e.getPrimaryServices();for(let e of t){let t=await e.getCharacteristics();for(let e of t)if(e.properties.write||e.properties.writeWithoutResponse)return e}}catch{}throw Error(`Tidak ditemukan karakteristik write pada printer thermal ini`)}async connect(e=!1){if(!this.isConnecting){if(!e&&this.device?.gatt?.connected&&this.characteristic)return this.setStatus(`connected`);if(!navigator.bluetooth)throw Error(`Web Bluetooth tidak didukung browser ini (gunakan Chrome/Edge)`);this.isConnecting=!0,this.setStatus(`connecting`);try{(e||!this.device)&&(this.device=await navigator.bluetooth.requestDevice({filters:[{services:[`000018f0-0000-1000-8000-00805f9b34fb`]},{namePrefix:`POS`},{namePrefix:`Thermal`},{namePrefix:`Printer`},{namePrefix:`MPT`},{namePrefix:`RP`},{namePrefix:`PTP`},{namePrefix:`InnerPrinter`},{namePrefix:`BlueTooth`},{namePrefix:`MTP`}],optionalServices:this.serviceUuids}),this.device.addEventListener(`gattserverdisconnected`,()=>this.scheduleReconnect())),await this.connectGatt()}catch(e){throw this.setStatus(`error`),this.onError?.(e),e}finally{this.isConnecting=!1}}}scheduleReconnect(){this.reconnectTimer&&clearTimeout(this.reconnectTimer),this.characteristic=null,this.setStatus(`disconnected`),this.reconnectTimer=setTimeout(async()=>{if(this.device)try{await this.connectGatt()}catch{}},3e3)}async print(e){if(this.characteristic||await this.connect(),!this.characteristic)throw Error(`Printer tidak tersambung`);let t=e instanceof Uint8Array?e:new Uint8Array(e);for(let e=0;e<t.length;e+=64){let n=t.slice(e,e+64);this.characteristic.properties.writeWithoutResponse?await this.characteristic.writeValueWithoutResponse(n):await this.characteristic.writeValue(n),await new Promise(e=>setTimeout(e,15))}}setStatus(e){this.status=e,this.onStatusChange?.(e)}getStatus(){return this.status}disconnect(){this.reconnectTimer&&clearTimeout(this.reconnectTimer),this.device?.gatt?.connected&&this.device.gatt.disconnect(),this.device=null,this.characteristic=null,localStorage.removeItem(`thermal_printer_device`),localStorage.removeItem(`ute_ble_printer_name`),this.setStatus(`unpaired`)}};window.EscPos=e,window.ThermalPrinter=t,window.UteThermalPrint={async printReceipt(n={}){let r=Number(localStorage.getItem(`ute_pos_paper_size`))===80?80:58,i={...n,total:n.total??n.total_akhir??0,bayar:n.bayar??n.total_akhir??n.total??0,kembali:n.kembali??0,paper_width:r,font_size:`small`},a=new e(r),o=a.buildReceipt(i,r);try{return await new t().print(o),!0}catch(e){console.warn(`UteThermalPrint: BLE tidak tersedia, fallback browser print`,e);let t=window.open(``,`_blank`);return t?(t.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8"><title>Struk</title>
        <style>@media print{@page{margin:0 size:${r}mm auto}body{margin:0;padding:1mm;background:#fff}}</style>
        </head><body>${a.buildPreviewHtml(i,r)}</body></html>`),t.document.close(),t.focus(),setTimeout(()=>t.print(),250),!0):!1}}};function n(e={}){let n=new t,r=e.thermal_bluetooth_mac||``,i=e.thermal_printer_name||``,a=!!(e.thermal_auto_print||e.auto_print),o=localStorage.getItem(`ute_ble_printer_name`),s=null;return o?s=o:r?s=`BT: ${r}`:i&&(s=`CUPS: ${i}`),{data:{...e},paperSize:`58`,isPrinting:!1,autoDirectPrint:localStorage.getItem(`ute_pos_auto_direct_print`)===`true`||a,pairedPrinterName:s,printer:n,init(){let e=this.data.default_width?.includes(`80`)?`80`:`58`;localStorage.getItem(`ute_pos_template_width`)===e?this.paperSize=localStorage.getItem(`ute_pos_paper_size`)||e:(localStorage.setItem(`ute_pos_template_width`,e),localStorage.setItem(`ute_pos_paper_size`,e),this.paperSize=e),n.onStatusChange=e=>{if(e===`connected`)this.pairedPrinterName=this.printer.device?.name||localStorage.getItem(`ute_ble_printer_name`)||`Bluetooth Printer`;else if(e===`disconnected`){let e=localStorage.getItem(`ute_ble_printer_name`)||(r?`BT: ${r}`:null);this.pairedPrinterName=e?`${e} (terputus)`:null}else e===`unpaired`&&(this.pairedPrinterName=null)},this.$nextTick(()=>{this.autoDirectPrint&&setTimeout(()=>{this.directPrint(this.paperSize)},350)})},async directPrint(e){if(!this.isPrinting){this.isPrinting=!0;try{let t=Number(e)===80?80:58,n={...this.data,paper_width:t,font_size:this.data.font_size||`small`},r=new window.EscPos(t).buildReceipt(n,t);if(i)try{await this.printViaCups(i,r),this.showToast(`Struk dikirim ke printer CUPS: ${i}`);return}catch(e){console.warn(`CUPS print gagal, fallback ke Bluetooth/Browser:`,e)}this.printer.device||localStorage.getItem(`thermal_printer_device`)?(await this.printer.print(r),this.showToast(`Struk berhasil dicetak via Bluetooth`)):this.printViaBrowser(e)}catch(t){console.error(`Print failed:`,t),confirm(`Gagal cetak (`+t.message+`). Buka cetak browser?`)&&this.printViaBrowser(e)}finally{this.isPrinting=!1}}},async printViaCups(e,t){let n=await fetch(`/api/pos/print-raw`,{method:`POST`,headers:{"Content-Type":`application/json`,"X-CSRF-TOKEN":document.querySelector(`meta[name="csrf-token"]`)?.content||``},body:JSON.stringify({printer:e,data:Array.from(t)})});if(!n.ok)throw Error(`CUPS print failed: `+n.status)},printViaBrowser(e){let t=window.open(``,`_blank`);if(!t){window.print();return}let n=Number(e)===80?80:58,r=new window.EscPos(n),i=r.buildPreviewHtml({...this.data,paper_width:n,font_size:this.data.font_size||`small`},n),a=this.data.nama_toko||`Bremspace`;t.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="utf-8">
          <title>Struk ${r(a)}</title>
          <style>
            @media print {
              @page { margin: 0; size: ${n}mm auto; }
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
          ${i}
        </body>
        </html>
      `),t.document.close(),t.focus(),setTimeout(()=>{t.print()},250)},async printStruk58(e=!1){if(e){try{await this.printer.connect(!0),this.pairedPrinterName=this.printer.device?.name||`Bluetooth Printer`,this.showToast(`Printer Bluetooth terhubung`)}catch(e){this.showToast(`Gagal menyambung: `+e.message)}return}await this.directPrint(`58`)},async printFaktur80(e=!1){if(e){try{await this.printer.connect(!0),this.pairedPrinterName=this.printer.device?.name||`Bluetooth Printer`,this.showToast(`Printer Bluetooth terhubung`)}catch(e){this.showToast(`Gagal menyambung: `+e.message)}return}await this.directPrint(`80`)},disconnectPrinter(){this.printer.disconnect(),this.pairedPrinterName=null,this.showToast(`Printer diputuskan`)},toggleAutoDirectPrint(){this.autoDirectPrint=!this.autoDirectPrint,localStorage.setItem(`ute_pos_auto_direct_print`,this.autoDirectPrint)},showToast(e){this.$dispatch(`toast`,{message:e,type:`info`})}}}window.thermalPrinter=n;var r=`modulepreload`,i=function(e){return`/build/`+e},a={},o=function(e,t,n){let o=Promise.resolve();if(t&&t.length>0){let e=document.getElementsByTagName(`link`),s=document.querySelector(`meta[property=csp-nonce]`),c=s?.nonce||s?.getAttribute(`nonce`);function l(e){return Promise.all(e.map(e=>Promise.resolve(e).then(e=>({status:`fulfilled`,value:e}),e=>({status:`rejected`,reason:e}))))}function u(e){return import.meta.resolve?import.meta.resolve(e):new URL(e,import.meta.url).href}o=l(t.map(t=>{if(t=i(t,n),t=u(t),t in a)return;a[t]=!0;let o=t.endsWith(`.css`);for(let n=e.length-1;n>=0;n--){let r=e[n];if(r.href===t&&(!o||r.rel===`stylesheet`))return}let s=document.createElement(`link`);if(s.rel=o?`stylesheet`:r,o||(s.as=`script`),s.crossOrigin=``,s.href=t,c&&s.setAttribute(`nonce`,c),document.head.appendChild(s),o)return new Promise((e,n)=>{s.addEventListener(`load`,e),s.addEventListener(`error`,()=>n(Error(`Unable to preload CSS for ${t}`)))})}).filter(e=>e!==void 0))}function s(e){let t=new Event(`vite:preloadError`,{cancelable:!0});if(t.payload=e,window.dispatchEvent(t),!t.defaultPrevented)throw e}return o.then(t=>{for(let e of t||[])e.status===`rejected`&&s(e.reason);return e().catch(s)})};export{o as t};