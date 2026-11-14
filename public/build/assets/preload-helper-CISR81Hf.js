var e=class{constructor(e=58){this.paperWidth=Number(e)===80?80:58,this.compressed=!0,this.columns=this.compressed?this.paperWidth===80?64:42:this.paperWidth===80?48:32,this.buf=[],this.init()}init(){return this.buf=[27,64],this.compressed&&this.buf.push(29,33,1),this}alignCenter(){return this.buf.push(27,97,1),this}alignLeft(){return this.buf.push(27,97,0),this}alignRight(){return this.buf.push(27,97,2),this}bold(e){return this.buf.push(27,69,+!!e),this}size(e){return this.buf.push(29,33,e),this}sizeDoubleWidth(e){return this.size(e?128:0),this}sizeDoubleHeight(e){return this.size(e?16:0),this}text(e){return this.buf.push(...this.ascii(e),10),this}rule(){return this.text(`-`.repeat(this.columns))}feed(e){return this.buf.push(...Array(e).fill(10)),this}cut(){return this.buf.push(29,86,66,0),this}qrcode(e,t=3){let n=this.ascii(e);if(!n||!n.length)return this;let r=n.length+3,i=r&255,a=r>>8&255,o=Math.max(2,Math.min(8,t));return this.buf.push(29,40,107,4,0,49,65,50,0),this.buf.push(29,40,107,3,0,49,67,o),this.buf.push(29,40,107,3,0,49,69,49),this.buf.push(29,40,107,i,a,49,80,48,...n),this.buf.push(29,40,107,3,0,49,81,48),this}textStrikethrough(e){return this.buf.push(27,66,1),this.buf.push(...this.ascii(e),10),this.buf.push(27,66,0),this}ascii(e){return e?String(e).replace(/[éèêë]/g,`e`).replace(/[áàâä]/g,`a`).replace(/[íìîï]/g,`i`).replace(/[óòôö]/g,`o`).replace(/[úùûü]/g,`u`).replace(/ñ/g,`n`).replace(/ç/g,`c`).replace(/[±×÷]/g,e=>({"±":`+/-`,"×":`x`,"÷":`/`})[e]).replace(/[–—]/g,`-`).replace(/[’‘]/g,`'`).replace(/[“”]/g,`"`).replace(/→/g,`->`).replace(/[^\x20-\x7E]/g,``).split(``).map(e=>e.charCodeAt(0)):[]}money(e){return`Rp `+Number(e||0).toLocaleString(`id-ID`)}pad(e,t){e=this.clean(e),t=this.clean(t);let n=Math.max(1,this.columns-e.length-t.length);return e+` `.repeat(n)+t}clean(e){return e?String(e).replace(/[éèêë]/g,`e`).replace(/[áàâä]/g,`a`).replace(/[íìîï]/g,`i`).replace(/[óòôö]/g,`o`).replace(/[úùûü]/g,`u`).replace(/ñ/g,`n`).replace(/ç/g,`c`).replace(/[±×÷]/g,e=>({"±":`+/-`,"×":`x`,"÷":`/`})[e]).replace(/[–—]/g,`-`).replace(/[’‘]/g,`'`).replace(/[“”]/g,`"`).replace(/→/g,`->`).replace(/[^\x20-\x7E]/g,``):``}wrap(e){let t=this.clean(e),n=[],r=``;for(let e of t.split(` `))(r+e).length>this.columns?(r&&n.push(r),r=e):r+=(r?` `:``)+e;return r&&n.push(r),n}buildReceipt(e){let{items:t=[],total:n=0,bayar:r=0,kembali:i=0,no_transaksi:a=`-`,metode_bayar:o=`TUNAI`,metode:s=`TUNAI`,tanggal:c,waktu:l,kasir:u,cabang:d,pelanggan:ee,tier:f,tipe_konsumen:p,saldo_poin:m,poin_ditukar:h,diskon_tier:g,diskon_partai:_,diskon_poin:v,diskon:y,diskon_persen:b,diskon_nominal:te,pajak:x,ppn_persen:S,ongkir:C,subtotal:w,subtotal_retail:ne,hemat_total:T,is_piutang:E,jatuh_tempo:D,sisa_piutang:re,komisi_mitra_nominal:O,no_tiket:k,jenis_hp:A,kurir:j,is_pickup:M,resi:N,sumber:P,garansi:F,footer_text:ie,kebijakan_retur:ae,catatan_kaki:I,nama_toko:L,slogan:R,alamat:z,telepon:B,website_medsos:V,show_cabang:H,show_kasir:U=!0,show_pelanggan:W=!0,show_waktu:oe,show_qr_retur:G=!0,qr_caption:K,qr_size:se=3,qr_url:ce,paper_width:q=58,font_size:J=`small`,strikethrough_mode:Y=`ascii`}=e;this.paperWidth=Number(q)===80?80:58,this.compressed=J===`small`,this.columns=this.compressed?this.paperWidth===80?64:42:this.paperWidth===80?48:32,this.buf=[],this.init(),this.alignCenter(),this.bold(!0).sizeDoubleWidth(!0).text(L||`UTEParts`).sizeDoubleWidth(!1).bold(!1),this.text(R||`SPAREPARTS/ACCESORIES/SERVICE`),z&&this.text(z),B&&this.text(`Telp/WA: `+B),H&&d&&this.text(d);let X=a||`-`,Z=ce||V||`www.uteparts.id`,Q=c||l||``,$=K||`Scan QR ini untuk retur & klaim garansi`;if(G&&X!==`-`?(this.alignCenter(),this.qrcode(X,se||3),this.bold(!0).text(X).bold(!1),this.text($),this.alignLeft(),this.text(`Web: `+Z),Q&&this.text(`Tgl: `+Q),F&&this.text(`Garansi: `+F),this.text(`Scan QR ini untuk retur & klaim garansi`)):(this.alignLeft(),this.text(`Web: `+Z),Q&&this.text(`Tgl: `+Q),F&&this.text(`Garansi: `+F),this.text(`Scan QR ini untuk retur & klaim garansi`)),this.rule(),this.alignLeft(),this.text(this.pad(`No. TRX:`,X)),P&&![`pos`,`offline`].includes(P.toLowerCase())&&this.text(this.pad(`Pesanan:`,P.toUpperCase())),k&&this.bold(!0).text(this.pad(`No. Tiket:`,k)).bold(!1),A&&this.text(this.pad(`Unit:`,A)),j){let e=M?`Ambil:`:`Kurir:`,t=N&&N!==`-`?` (`+N+`)`:``;this.text(this.pad(e,j+t))}if(U&&u&&this.text(this.pad(`Kasir:`,u)),W){let e=ee||`Umum`;if(this.text(this.pad(`Pelanggan:`,e)),f&&f!==`Retail`&&f!==`Umum`){let e=[`reseller`,`agen`].includes(p)?`Mitra:`:`Tier:`;this.bold(!0).text(this.pad(e,f)).bold(!1)}}for(let e of t){let t=e.nama||`Item`;for(let e of this.wrap(t))this.bold(!0).text(e).bold(!1);let n=(e.qty||1)+(e.satuan?` `+e.satuan:` PCS`),r=this.money(e.harga),i=this.money(e.subtotal),a=Number(e.harga_retail??0),o=Number(e.harga??0),s=``;if(a>o){let e=this.money(a),t=this.money(o);if(Y===`overprint`){let r=n+` x `,i=` `.repeat(r.length)+`-`.repeat(e.length);this.buf.push(...i.split(``).map(e=>e.charCodeAt(0)),13),s=`${r}${e} ${t}`}else s=Y===`combining`?`${n} x ${e.split(``).map(e=>e+`̶`).join(``)} ${t}`:`${n} x (${e}) -> ${t}`}else s=`${n} x ${r}`;if(this.text(this.pad(s,i)),e.catatan)for(let t of this.wrap(`  * `+e.catatan))this.text(t)}if(g&&Number(g)>0){let e=Number(ne??0)||Number(w??0)+Number(g);this.text(this.pad(`Subtotal Retail:`,this.money(e)));let t=f&&f!==`Retail`?`Pot `+f+`:`:[`reseller`,`agen`].includes(p)?`Pot Mitra:`:`Pot Tier:`;this.text(this.pad(t,`-`+this.money(g))),this.text(this.pad(`Subtotal:`,this.money(w??n)))}else this.text(this.pad(`Subtotal:`,this.money(w??n)));if(_&&Number(_)>0&&this.text(this.pad(`Diskon Partai:`,`-`+this.money(_))),v&&Number(v)>0&&this.text(this.pad(`Tukar Poin (`+(h||0)+`pts):`,`-`+this.money(v))),y&&Number(y)>0){let e=b&&Number(b)>0?`Diskon (`+b+`%):`:`Diskon Kasir:`;this.text(this.pad(e,`-`+this.money(y)))}if(x&&Number(x)>0){let e=S?`PPN (`+S+`%):`:`PPN:`;this.text(this.pad(e,`+`+this.money(x)))}if(C&&Number(C)>0&&this.text(this.pad(`Ongkir:`,`+`+this.money(C))),this.bold(!0).sizeDoubleHeight(!0).text(this.pad(`TOTAL:`,this.money(n))).sizeDoubleHeight(!1).bold(!1),E)this.text(this.pad(`Status:`,`BELUM LUNAS (PIUTANG)`)),D&&this.text(this.pad(`Jatuh Tempo:`,D)),this.text(this.pad(`Sisa Tag:`,this.money(re??n)));else{let e=String(o||s||`TUNAI`).toUpperCase();this.text(this.pad(`BAYAR (${e}):`,this.money(r||n))),this.text(this.pad(`KEMBALI:`,this.money(i??0)))}if(m!==void 0&&this.text(this.pad(`Sisa Saldo Poin:`,Number(m).toLocaleString(`id-ID`)+` pts`)),T&&Number(T)>0&&this.bold(!0).text(this.pad(`HEMAT:`,this.money(T))).bold(!1),O&&Number(O)>0&&this.text(this.pad(`Komisi:`,`+`+this.money(O)+` (Saldo)`)),this.rule(),this.alignCenter(),F&&this.text(`Garansi: `+F),this.text(ie||`Terima kasih atas kunjungan Anda!`),this.text(ae||`Segel toko wajib utuh & fisik barang tidak cacat.`),I)for(let e of this.wrap(I))this.text(e);return E&&(this.feed(1),this.text(`"Barang diterima & berjanji melunasi sblm jatuh tempo"`),this.feed(1),this.text(`Penerima,              Kasir,`),this.feed(2),this.text(`(............)        (............)`)),this.feed(1).cut(),new Uint8Array(this.buf)}build(e){return this.buildReceipt(e)}renderPreviewHtml(e){return this.buildPreviewHtml(e)}buildPreviewHtml(e){let{items:t=[],total:n=0,bayar:r=0,kembali:i=0,no_transaksi:a=`-`,metode_bayar:o=`TUNAI`,metode:s=`TUNAI`,tanggal:c,waktu:l,kasir:u,cabang:d,pelanggan:ee,tier:f,tipe_konsumen:p,saldo_poin:m,poin_ditukar:h,diskon_tier:g,diskon_partai:_,diskon_poin:v,diskon:y,diskon_persen:b,diskon_nominal:te,pajak:x,ppn_persen:S,ongkir:C,subtotal:w,subtotal_retail:ne,hemat_total:T,is_piutang:E,jatuh_tempo:D,sisa_piutang:re,komisi_mitra_nominal:O,no_tiket:k,jenis_hp:A,kurir:j,is_pickup:M,resi:N,sumber:P,garansi:F,footer_text:ie,kebijakan_retur:ae,catatan_kaki:I,nama_toko:L,slogan:R,alamat:z,telepon:B,website_medsos:V,show_cabang:H,show_kasir:U=!0,show_pelanggan:W=!0,show_qr_retur:oe=!0,qr_caption:G,qr_url:K,qr_svg:se,paper_width:ce=58}=e,q=e=>`Rp `+Number(e||0).toLocaleString(`id-ID`),J=e=>String(e||``).replace(/&/g,`&amp;`).replace(/</g,`&lt;`).replace(/>/g,`&gt;`).replace(/"/g,`&quot;`),Y=Number(ce)===80?80:58,X=Y===80?`paper-80`:`paper-58`,Z=a||`-`,Q=K||V||`www.uteparts.id`,$=c||l||``,le=String(o||s||`TUNAI`).toUpperCase(),ue=se||`
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
    `,de=b&&Number(b)>0?`Diskon (`+b+`%)`:`Diskon Kasir`,fe=S?`PPN (`+S+`%)`:`PPN`;return`
<div class="thermal-receipt-doc ${X}" style="width: 100%; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: ${Y===80?`11px`:`10px`}; line-height: 1.25; color: #000; background: #fff;">
  <!-- HEADER (Centered) -->
  <div style="text-align: center; width: 100%; padding-bottom: 2px;">
    <div style="font-size: 14px; font-weight: 900; letter-spacing: 0.05em; text-transform: uppercase; text-align: center;">${J(L||`UTEParts`)}</div>
    <div style="font-size: 9px; font-weight: 600; text-transform: uppercase; margin-top: 2px; text-align: center;">${J(R||`SPAREPARTS/ACCESORIES/SERVICE`)}</div>
    ${z?`<div style="font-size: 8px; color: #374151; margin-top: 2px; line-height: 1.2; text-align: center;">${J(z)}</div>`:``}
    ${B?`<div style="font-size: 8.5px; color: #1f2937; text-align: center;">Telp/WA: ${J(B)}</div>`:``}
    ${H&&d?`<div style="font-size: 8.5px; font-weight: 600; text-align: center;">${J(d)}</div>`:``}
  </div>

  <!-- 2-COL: QR (Left) + URL/Date/Warranty Note (Right) -->
  <div style="display: flex; flex-direction: row; align-items: center; justify-content: space-between; width: 100%; gap: 8px; padding: 4px 0;">
    ${oe&&Z!==`-`?`
      <div style="width: 68px; height: 68px; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
        ${ue}
      </div>
    `:``}
    <div style="flex: 1; min-width: 0; text-align: left; font-size: 8.5px; line-height: 1.25;">
      <div style="font-weight: 600; color: #111827; word-break: break-all;">${J(Q)}</div>
      ${$?`<div style="color: #374151;">${J($)}</div>`:``}
      <div style="font-size: 8px; color: #4b5563; margin-top: 2px;">${J(G||`Scan QR ini untuk retur & klaim garansi`)}</div>
    </div>
  </div>

  <div style="border-top: 1px dashed #000; width: 100%; margin: 4px 0;"></div>

  <!-- TRANSACTION INFO (Left-Right) -->
  <div style="width: 100%; font-size: 9px; line-height: 1.35;">
    <div style="display: flex; justify-content: space-between; width: 100%;">
      <span style="color: #4b5563;">No. TRX:</span>
      <span style="font-weight: 700; color: #111827;">${J(Z)}</span>
    </div>
    ${P&&![`pos`,`offline`].includes(P.toLowerCase())?`
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">Pesanan:</span>
        <span>${J(P.toUpperCase())}</span>
      </div>
    `:``}
    ${k?`
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">No. Tiket:</span>
        <span style="font-weight: 700;">${J(k)}</span>
      </div>
    `:``}
    ${A?`
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">Unit:</span>
        <span>${J(A)}</span>
      </div>
    `:``}
    ${j?`
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">${M?`Ambil:`:`Kurir:`}</span>
        <span>${J(j+(N&&N!==`-`?` (`+N+`)`:``))}</span>
      </div>
    `:``}
    ${U&&u?`
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">Kasir:</span>
        <span>${J(u)}</span>
      </div>
    `:``}
    ${W?`
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">Pelanggan:</span>
        <span>${J(ee||`Umum`)}</span>
      </div>
      ${f&&f!==`Retail`&&f!==`Umum`?`
        <div style="display: flex; justify-content: space-between; width: 100%;">
          <span style="color: #4b5563;">${[`reseller`,`agen`].includes(p)?`Mitra:`:`Tier:`}</span>
          <span style="font-weight: 700;">${J(f)}</span>
        </div>
      `:``}
    `:``}
  </div>

  <!-- ITEMS -->
  <div style="width: 100%; margin: 4px 0;">
    ${t.map(e=>{let t=e.nama||`Item`,n=(e.qty||1)+(e.satuan?` `+e.satuan:` PCS`),r=q(e.harga),i=Number(e.subtotal||0).toLocaleString(`id-ID`),a=Number(e.harga_retail??0),o=Number(e.harga??0);return`
        <div style="width: 100%; margin-bottom: 4px;">
          <div style="font-weight: 700; font-size: 9.5px; line-height: 1.25; word-break: break-word;">${J(t)}</div>
          <div style="display: flex; justify-content: space-between; align-items: baseline; width: 100%; font-size: 9px; margin-top: 1px;">
            <span>
              ${n} x ${a>o?`
                <span style="text-decoration: line-through; opacity: 0.6; margin-right: 2px;">${q(a)}</span>
                <span style="font-weight: 700;">${q(o)}</span>
              `:`<span>${r}</span>`}
            </span>
            <span style="font-weight: 700; text-align: right; margin-left: 4px;">${i}</span>
          </div>
          ${e.catatan?`<div style="font-size: 8px; color: #4b5563; font-style: italic; margin-top: 1px;">* ${J(e.catatan)}</div>`:``}
        </div>
      `}).join(``)}
  </div>

  <!-- SUBTOTAL BREAKDOWN & TOTALS (Directly follows items without extra dashed line) -->
  <div style="width: 100%; font-size: 9px; line-height: 1.35;">
    ${g&&Number(g)>0?`
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">Subtotal Retail:</span>
        <span>${q(Number(ne??0)||Number(w??0)+Number(g))}</span>
      </div>
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">${f&&f!==`Retail`?`Pot `+J(f)+`:`:`Pot Mitra:`}</span>
        <span style="color: #dc2626;">-${q(g)}</span>
      </div>
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">Subtotal:</span>
        <span>${q(w??n)}</span>
      </div>
    `:`
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">Subtotal:</span>
        <span>${q(w??n)}</span>
      </div>
    `}

    ${_&&Number(_)>0?`
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">Diskon Partai:</span>
        <span style="color: #dc2626;">-${q(_)}</span>
      </div>
    `:``}
    ${v&&Number(v)>0?`
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">Tukar Poin (${h||0}pts):</span>
        <span style="color: #dc2626;">-${q(v)}</span>
      </div>
    `:``}
    ${y&&Number(y)>0?`
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">${J(de)}:</span>
        <span style="color: #dc2626;">-${q(y)}</span>
      </div>
    `:``}
    ${x&&Number(x)>0?`
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">${J(fe)}:</span>
        <span>+${q(x)}</span>
      </div>
    `:``}
    ${C&&Number(C)>0?`
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">Ongkir:</span>
        <span>+${q(C)}</span>
      </div>
    `:``}

    <div style="display: flex; justify-content: space-between; align-items: baseline; width: 100%; font-size: 13px; font-weight: 900; padding: 2px 0;">
      <span>TOTAL:</span>
      <span>${q(n)}</span>
    </div>

    ${E?`
      <div style="display: flex; justify-content: space-between; width: 100%; font-weight: 700; color: #b45309;">
        <span>Status:</span>
        <span>BELUM LUNAS (PIUTANG)</span>
      </div>
      ${D?`
        <div style="display: flex; justify-content: space-between; width: 100%;">
          <span style="color: #4b5563;">Jatuh Tempo:</span>
          <span>${J(D)}</span>
        </div>
      `:``}
      <div style="display: flex; justify-content: space-between; width: 100%; font-weight: 700;">
        <span style="color: #4b5563;">Sisa Tag:</span>
        <span>${q(re??n)}</span>
      </div>
    `:`
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">BAYAR (${J(le)}):</span>
        <span style="font-weight: 600;">${q(r||n)}</span>
      </div>
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">KEMBALI:</span>
        <span style="font-weight: 600;">${q(i??0)}</span>
      </div>
    `}

    ${m===void 0?``:`
      <div style="display: flex; justify-content: space-between; width: 100%;">
        <span style="color: #4b5563;">Sisa Saldo Poin:</span>
        <span>${Number(m).toLocaleString(`id-ID`)} pts</span>
      </div>
    `}
    ${T&&Number(T)>0?`
      <div style="display: flex; justify-content: space-between; width: 100%; font-weight: 700; color: #059669;">
        <span>HEMAT:</span>
        <span>${q(T)}</span>
      </div>
    `:``}
    ${O&&Number(O)>0?`
      <div style="display: flex; justify-content: space-between; width: 100%; color: #2563eb;">
        <span style="color: #4b5563;">Komisi:</span>
        <span>+${q(O)} (Saldo)</span>
      </div>
    `:``}
  </div>

  <div style="border-top: 1px dashed #000; width: 100%; margin: 4px 0;"></div>

  <!-- FOOTER (Centered) -->
  <div style="text-align: center; width: 100%; font-size: 8.5px; color: #374151; line-height: 1.3; padding-top: 2px;">
    ${F?`<div style="font-weight: 700; color: #111827; text-align: center;">Garansi: ${J(F)}</div>`:``}
    <div style="text-align: center;">${J(ie||`Terima kasih atas kunjungan Anda!`)}</div>
    <div style="font-size: 8px; color: #4b5563; text-align: center;">${J(ae||`Segel toko wajib utuh & fisik barang tidak cacat.`)}</div>
    ${I?`<div style="font-size: 8px; color: #6b7280; margin-top: 2px; text-align: center;">${J(I)}</div>`:``}
    ${E?`
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
`}},t=class{constructor(){this.device=null,this.characteristic=null,this.reconnectTimer=null,this.isConnecting=!1,this.onStatusChange=null,this.onError=null,this.status=`unpaired`,this.serviceUuids=[`000018f0-0000-1000-8000-00805f9b34fb`,`e7810a71-73ae-499d-8c15-faa9aef0c3f2`,`49535343-fe7d-4ae5-8fa9-9fafd205e455`,`0000ff00-0000-1000-8000-00805f9b34fb`,`0000fee7-0000-1000-8000-00805f9b34fb`],this.loadSavedDevice()}async loadSavedDevice(){try{let e=localStorage.getItem(`thermal_printer_device`);if(!e){this.setStatus(`unpaired`);return}let{name:t,id:n}=JSON.parse(e);if(navigator.bluetooth&&typeof navigator.bluetooth.getDevices==`function`){let e=(await navigator.bluetooth.getDevices()).find(e=>e.id===n||e.name===t);if(e){this.device=e,this.device.addEventListener(`gattserverdisconnected`,()=>this.scheduleReconnect()),await this.connectGatt();return}}this.setStatus(`saved`)}catch(e){console.warn(`Silent BLE reconnect skipped:`,e),this.setStatus(`unpaired`)}}async connectGatt(){if(this.device?.gatt){if(this.device.gatt.connected&&this.characteristic){this.setStatus(`connected`);return}this.isConnecting=!0,this.setStatus(`connecting`);try{let e=await this.device.gatt.connect();this.characteristic=await this.discoverWritableCharacteristic(e),localStorage.setItem(`thermal_printer_device`,JSON.stringify({name:this.device.name,id:this.device.id})),localStorage.setItem(`ute_ble_printer_name`,this.device.name||`Bluetooth Printer`),this.setStatus(`connected`)}catch(e){throw console.error(`GATT connection error:`,e),this.setStatus(`error`),this.onError?.(e),e}finally{this.isConnecting=!1}}}async discoverWritableCharacteristic(e){for(let t of this.serviceUuids)try{let n=await(await e.getPrimaryService(t)).getCharacteristics();for(let e of n)if(e.properties.write||e.properties.writeWithoutResponse)return e}catch{}try{let t=await e.getPrimaryServices();for(let e of t){let t=await e.getCharacteristics();for(let e of t)if(e.properties.write||e.properties.writeWithoutResponse)return e}}catch{}throw Error(`Tidak ditemukan karakteristik write pada printer thermal ini`)}async connect(e=!1){if(!this.isConnecting){if(!e&&this.device?.gatt?.connected&&this.characteristic)return this.setStatus(`connected`);if(!navigator.bluetooth)throw Error(`Web Bluetooth tidak didukung browser ini (gunakan Chrome/Edge)`);this.isConnecting=!0,this.setStatus(`connecting`);try{(e||!this.device)&&(this.device=await navigator.bluetooth.requestDevice({filters:[{services:[`000018f0-0000-1000-8000-00805f9b34fb`]},{namePrefix:`POS`},{namePrefix:`Thermal`},{namePrefix:`Printer`},{namePrefix:`MPT`},{namePrefix:`RP`},{namePrefix:`PTP`},{namePrefix:`InnerPrinter`},{namePrefix:`BlueTooth`},{namePrefix:`MTP`}],optionalServices:this.serviceUuids}),this.device.addEventListener(`gattserverdisconnected`,()=>this.scheduleReconnect())),await this.connectGatt()}catch(e){throw this.setStatus(`error`),this.onError?.(e),e}finally{this.isConnecting=!1}}}scheduleReconnect(){this.reconnectTimer&&clearTimeout(this.reconnectTimer),this.characteristic=null,this.setStatus(`disconnected`),this.reconnectTimer=setTimeout(async()=>{if(this.device)try{await this.connectGatt()}catch{}},3e3)}async print(e){if(this.characteristic||await this.connect(),!this.characteristic)throw Error(`Printer tidak tersambung`);let t=e instanceof Uint8Array?e:new Uint8Array(e);for(let e=0;e<t.length;e+=64){let n=t.slice(e,e+64);this.characteristic.properties.writeWithoutResponse?await this.characteristic.writeValueWithoutResponse(n):await this.characteristic.writeValue(n),await new Promise(e=>setTimeout(e,15))}}setStatus(e){this.status=e,this.onStatusChange?.(e)}getStatus(){return this.status}disconnect(){this.reconnectTimer&&clearTimeout(this.reconnectTimer),this.device?.gatt?.connected&&this.device.gatt.disconnect(),this.device=null,this.characteristic=null,localStorage.removeItem(`thermal_printer_device`),localStorage.removeItem(`ute_ble_printer_name`),this.setStatus(`unpaired`)}};window.EscPos=e,window.ThermalPrinter=t;function n(e={}){let n=new t,r=e.thermal_bluetooth_mac||``,i=e.thermal_printer_name||``,a=!!(e.thermal_auto_print||e.auto_print),o=localStorage.getItem(`ute_ble_printer_name`),s=null;return o?s=o:r?s=`BT: ${r}`:i&&(s=`CUPS: ${i}`),{data:{...e},paperSize:`58`,isPrinting:!1,autoDirectPrint:localStorage.getItem(`ute_pos_auto_direct_print`)===`true`||a,pairedPrinterName:s,printer:n,init(){this.paperSize=localStorage.getItem(`ute_pos_paper_size`)||(this.data.default_width?.includes(`80`)?`80`:`58`),n.onStatusChange=e=>{if(e===`connected`)this.pairedPrinterName=this.printer.device?.name||localStorage.getItem(`ute_ble_printer_name`)||`Bluetooth Printer`;else if(e===`disconnected`){let e=localStorage.getItem(`ute_ble_printer_name`)||(r?`BT: ${r}`:null);this.pairedPrinterName=e?`${e} (terputus)`:null}else e===`unpaired`&&(this.pairedPrinterName=null)},this.$nextTick(()=>{this.autoDirectPrint&&setTimeout(()=>{this.directPrint(this.paperSize)},350)})},async directPrint(e){if(!this.isPrinting){this.isPrinting=!0;try{let t=Number(e)===80?80:58,n=new window.EscPos(t).buildReceipt(this.data);i?(await this.printViaCups(i,n),this.showToast(`Struk dikirim ke printer CUPS: ${i}`)):this.printer.device||localStorage.getItem(`thermal_printer_device`)?(await this.printer.print(n),this.showToast(`Struk berhasil dicetak via Bluetooth`)):this.printViaBrowser(e)}catch(t){console.error(`Print failed:`,t),confirm(`Gagal cetak via Bluetooth (`+t.message+`). Buka cetak browser?`)&&this.printViaBrowser(e)}finally{this.isPrinting=!1}}},async printViaCups(e,t){let n=await fetch(`/api/pos/print-raw`,{method:`POST`,headers:{"Content-Type":`application/json`,"X-CSRF-TOKEN":document.querySelector(`meta[name="csrf-token"]`)?.content||``},body:JSON.stringify({printer:e,data:Array.from(t)})});if(!n.ok)throw Error(`CUPS print failed: `+n.status)},printViaBrowser(e){let t=window.open(``,`_blank`);if(!t){window.print();return}let n=Number(e)===80?80:58,r=new window.EscPos(n).buildPreviewHtml(this.data);t.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="utf-8">
          <title>Struk UTEParts</title>
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
          ${r}
        </body>
        </html>
      `),t.document.close(),t.focus(),setTimeout(()=>{t.print()},250)},async printStruk58(e=!1){if(e){try{await this.printer.connect(!0),this.pairedPrinterName=this.printer.device?.name||`Bluetooth Printer`,this.showToast(`Printer Bluetooth terhubung`)}catch(e){this.showToast(`Gagal menyambung: `+e.message)}return}await this.directPrint(`58`)},async printFaktur80(e=!1){if(e){try{await this.printer.connect(!0),this.pairedPrinterName=this.printer.device?.name||`Bluetooth Printer`,this.showToast(`Printer Bluetooth terhubung`)}catch(e){this.showToast(`Gagal menyambung: `+e.message)}return}await this.directPrint(`80`)},disconnectPrinter(){this.printer.disconnect(),this.pairedPrinterName=null,this.showToast(`Printer diputuskan`)},toggleAutoDirectPrint(){this.autoDirectPrint=!this.autoDirectPrint,localStorage.setItem(`ute_pos_auto_direct_print`,this.autoDirectPrint)},showToast(e){this.$dispatch(`toast`,{message:e,type:`info`})}}}window.thermalPrinter=n;var r=`modulepreload`,i=function(e){return`/build/`+e},a={},o=function(e,t,n){let o=Promise.resolve();if(t&&t.length>0){let e=document.getElementsByTagName(`link`),s=document.querySelector(`meta[property=csp-nonce]`),c=s?.nonce||s?.getAttribute(`nonce`);function l(e){return Promise.all(e.map(e=>Promise.resolve(e).then(e=>({status:`fulfilled`,value:e}),e=>({status:`rejected`,reason:e}))))}function u(e){return import.meta.resolve?import.meta.resolve(e):new URL(e,import.meta.url).href}o=l(t.map(t=>{if(t=i(t,n),t=u(t),t in a)return;a[t]=!0;let o=t.endsWith(`.css`);for(let n=e.length-1;n>=0;n--){let r=e[n];if(r.href===t&&(!o||r.rel===`stylesheet`))return}let s=document.createElement(`link`);if(s.rel=o?`stylesheet`:r,o||(s.as=`script`),s.crossOrigin=``,s.href=t,c&&s.setAttribute(`nonce`,c),document.head.appendChild(s),o)return new Promise((e,n)=>{s.addEventListener(`load`,e),s.addEventListener(`error`,()=>n(Error(`Unable to preload CSS for ${t}`)))})}).filter(e=>e!==void 0))}function s(e){let t=new Event(`vite:preloadError`,{cancelable:!0});if(t.payload=e,window.dispatchEvent(t),!t.defaultPrevented)throw e}return o.then(t=>{for(let e of t||[])e.status===`rejected`&&s(e.reason);return e().catch(s)})};export{o as t};