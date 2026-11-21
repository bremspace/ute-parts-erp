UTE PARTS ERP - Docker Installer (Windows)

Cara pakai:
1. Pastikan Docker Desktop terinstall dan berjalan.
2. Buka folder ini.
3. Double-click install.bat (atau jalankan install.ps1).
4. Pilih sumber instalasi:
   - [1] Gunakan folder lokal (default, offline)
   - [2] Git pull/clone dari URL (online, ambil/update dari GitHub)
5. Tunggu build selesai.
6. Buka http://localhost

Catatan:
- Nama container Docker tidak boleh mengandung spasi, jadi container dinamai UTE_PARTS_ERP.
- Port host: 80.
- Database MySQL tersimpan di volume Docker (ute_mysql).
- Untuk reset data: docker compose down -v lalu install ulang.