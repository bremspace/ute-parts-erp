# Implement Plan: Standardisasi Akunting Industri & Kesiapan Audit ISO (9001 / 27001 / SAK)

Rencana implementasi teknis pemenuhan standar tata kelola keuangan, jejak audit, dan pengendalian periode buku untuk sertifikasi ISO dan audit eksternal.

---

## Acceptance Criteria Checklist

### Fase 1: Periode Akuntansi & Fitur Tutup Buku
- [x] T-01 — Skema Database Periode Akuntansi: Migrasi tabel `periode_akuntansi` dengan constraint unique per cabang, tahun, bulan, dan foreign key cabang serta user penutup.
- [x] T-02 — Guard Immutabilitas Periode Closed: `JurnalService` menolak posting jurnal baru maupun backdating bila tanggal transaksi berada dalam periode buku yang berstatus `closed`.
- [x] T-03 — Service Tutup Buku & Jurnal Penutup Otomatis: `TutupBukuService` memverifikasi keseimbangan neraca, menghasilkan jurnal penutup double-entry (menutup akun pendapatan & beban ke akun Laba Ditahan `310-02`), serta mengunci periode. Mendukung pembukaan kembali (re-open) khusus super-admin dengan audit trail.
- [x] T-04 — Antarmuka UI Tab Tutup Buku: Komponen Livewire `AkuntingDashboard` menyediakan tab "Tutup Buku" dengan kartu ringkasan status periode aktif, daftar riwayat penutupan buku, modal konfirmasi pra-cek neraca, dan tombol eksekusi tutup/buka buku.
- [x] T-05 — Pengujian Otomatis (Automated Tests): Test suite memvalidasi kalkulasi jurnal penutup, penolakan posting pada periode terkunci, dan alur buka-tutup periode buku.

### Fase 2: Immutabilitas Jurnal & Jurnal Pembalik (Reversal/Storno)
- [x] T-06 — Immutabilitas Model Jurnal Eloquent: Model `JurnalAkuntansi` dan `JurnalHeader` memblokir mutasi update/delete langsung di level Eloquent.
- [x] T-07 — Fitur Jurnal Pembalik (Storno): Kemampuan membalik jurnal yang salah dengan 1-klik, menandai status reversal, dan menautkan referensi nomor jurnal asal.

### Fase 3: Rekonsiliasi Bank Multi-Format (CSV, XLSX, PDF BCA/Mandiri) & Neraca Saldo 4 Kolom
- [x] T-08 — Rekonsiliasi Rekening Koran Bank Multi-Format: Engine parser cerdas mutasi bank (mendukung CSV, XLSX, dan PDF e-statement BCA/Mandiri/dll dengan ekstraksi tanggal, deskripsi, DB/CR, dan nominal), auto-matching terhadap buku besar akun Bank, serta fitur pembuatan jurnal penyesuaian (biaya admin/bunga) 1-klik.
- [x] T-09 — Laporan Neraca Saldo (Trial Balance 4 Kolom): Penyajian format neraca saldo 4 kolom (Saldo Awal, Mutasi Debit, Mutasi Kredit, Saldo Akhir) sesuai standar kertas kerja audit ISO/KAP, lengkap dengan export Excel/CSV dan UI viewer.

### Fase 4: Governance Maker-Checker & Lampiran Bukti Transaksi
- [x] T-10 — Segregation of Duties Jurnal Manual: Integrasi workflow approval maker-checker untuk posting jurnal manual.
- [x] T-11 — Lampiran Dokumen Bukti Transaksi: Dukungan upload berkas fisik/PDF kuitansi/invoice pada jurnal manual.
