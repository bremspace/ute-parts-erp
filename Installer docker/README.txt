UTE PARTS ERP - Docker Installer (Windows)

Cara pakai:
1. Pastikan Docker Desktop terinstall dan berjalan.
2. Buka folder ini (boleh dicopy ke PC baru / flashdisk).
3. Double-click install.bat (atau jalankan install.ps1).
4. Pilih aksi: Fresh install atau Update.
5. Pilih sumber:
   - Git: clone/pull dari URL (folder target bisa dipilih; installer clone dan salin folder Installer ke repo)
   - Folder lokal: offline, pakai folder repo yang sudah ada
6. Jika Git: link dan branch bisa diisi manual (default tersedia, daftar branch auto-detect).
7. Opsional: buka akses LAN (firewall port 80) agar PC client lain bisa akses.
8. Tunggu build selesai.
9. Installer akan menampilkan IP LAN otomatis untuk akses PC client.
10. Buka http://localhost (server) atau http://IP-LAN (client)

Catatan:
- Nama container Docker tidak boleh mengandung spasi, jadi container dinamai UTE_PARTS_ERP.
- Port host: 80.
- Database MySQL tersimpan di volume Docker (ute_mysql).
- Untuk reset data: pilih Fresh install (hapus volume) atau docker compose down -v lalu install ulang.