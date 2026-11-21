UTE PARTS ERP - Docker Installer (Windows)

Cara pakai:
1. Pastikan Docker Desktop terinstall dan berjalan.
2. Buka folder ini.
3. Double-click install.bat (atau jalankan install.ps1).
4. Tunggu build selesai.
5. Buka http://localhost

Catatan:
- Nama container Docker tidak boleh mengandung spasi, jadi container dinamai UTE_PARTS_ERP.
- Port host: 80.
- Database MySQL tersimpan di volume Docker (ute_mysql).
- Untuk reset data: docker compose down -v lalu install ulang.