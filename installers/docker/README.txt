UTE PARTS ERP - Docker Installer (Windows & Linux/VPS)

Cara pakai:
1. Pastikan Docker & Docker Compose terinstall dan berjalan.
2. Buka folder ini (boleh dicopy ke PC baru / VPS / flashdisk).
3. Windows: Double-click install.bat (atau jalankan install.ps1).
   Linux/VPS: Jalankan bash install.sh (untuk Cloud VPS gunakan SYNC_NODE_ROLE=cloud_storefront di .env).
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
- Hybrid Sync: Container sudah otomatis menjalankan background worker antrean (default,sync) dan scheduler. Untuk menghubungkan node ke server remote, isi `SYNC_REMOTE_URL` & `SYNC_API_SECRET` di `.env` atau menu Pengaturan Integrasi (/app/settings).
- Restore database produksi ke Docker lokal: gunakan FULL SNAPSHOT (.zip) dari menu Backup, bukan .sql.gz biasa, karena ZIP membawa APP_KEY di manifest.json. Jika terpaksa pakai .sql.gz, isi kolom "APP_KEY produksi" di modal restore dengan APP_KEY dari .env produksi. Setelah restore selesai, HAPUS COOKIE browser lalu login ulang. Jika masih muncul "The MAC is invalid", samakan APP_KEY di /var/www/html/storage/.env dengan APP_KEY produksi lalu jalankan: docker compose exec app php artisan optimize:clear && docker compose exec app php artisan optimize && docker compose restart app