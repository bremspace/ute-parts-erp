# PANDUAN-DOMAIN-LOKAL-KE-INTERNET.md
## Menghubungkan Aplikasi Lokal ke Domain Sendiri (Bisa Diakses Dari Mana Saja)

Panduan ini digunakan jika aplikasi dan database dipasang di **komputer/server lokal (kantor/toko)**, namun ingin diakses lewat internet menggunakan **domain/subdomain sendiri** (misal: `pos.namatoko.com`) **tanpa perlu sewa IP Publik statis** dan **tanpa membuka port modem/router (bebas risiko peretasan port DB)**.

---

### Solusi Terbaik & Paling Aman: Cloudflare Tunnel (Zero Trust)
*Gratis, otomatis dapat SSL HTTPS, melewati blokir NAT/CGNAT provider internet (Indihome, Biznet, MyRepublic, FirstMedia, dll), dan tidak mengekspos IP asli server.*

---

### Langkah 1: Siapkan Domain di Cloudflare
1. Daftarkan akun gratis di [cloudflare.com](https://dash.cloudflare.com).
2. Arahkan Nameserver domain Anda ke Cloudflare.

---

### Langkah 2: Buat Tunnel di Dashboard Cloudflare
1. Buka menu **Zero Trust** > **Networks** > **Tunnels**.
2. Klik **Create a Tunnel** > Pilih **Cloudflared**.
3. Beri nama tunnel (contoh: `server-toko-pos`).
4. Pada pilihan OS:
   - **Windows:** Unduh installer `cloudflared-windows-amd64.msi` atau jalankan perintah instalasi PowerShell yang diberikan.
   - **Linux / Ubuntu:** Jalankan perintah install terminal yang ditampilkan:
     ```bash
     curl -L --output cloudflared.deb https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb
     sudo dpkg -i cloudflared.deb
     sudo cloudflared service install <TOKEN_DARI_CLOUDFLARE>
     ```

---

### Langkah 3: Arahkan Subdomain ke Port Aplikasi Lokal
Pada tab **Public Hostname** di Cloudflare Tunnel:
- **Subdomain:** Masukkan nama subdomain (contoh: `pos` atau `erp`).
- **Domain:** Pilih domain Anda (contoh: `namatoko.com`).
- **Type:** Pilih `HTTP`.
- **URL:** Masukkan alamat lokal aplikasi:
  - Jika pakai port built-in launcher (`start-install.bat` / `start-install.sh`):  
    `localhost:1741`
  - Jika pakai Nginx / XAMPP / Laragon standar:  
    `localhost:80`

Klik **Save hostname**.  
Cloudflare otomatis membuatkan DNS record dan sertifikat SSL HTTPS valid.

---

### Langkah 4: Sesuaikan APP_URL di Berkas `.env` Lokal
Buka berkas `.env` di folder project lokal:
```env
APP_URL=https://pos.namatoko.com
```

Lalu jalankan optimasi cache:
- **Windows:** Buka command prompt di folder project:
  ```cmd
  php artisan config:clear
  ```
- **Linux:**
  ```bash
  php artisan config:clear
  ```

---

### Keuntungan Arsitektur Ini:
1. **Keamanan 100%:** Port MySQL (3306) tetap terisolasi di jaringan lokal (`127.0.0.1`), tidak bisa disentuh bot internet/ransomware.
2. **Tanpa Port Forwarding:** Tidak perlu setting rumit di router / modem fiber optik.
3. **Gratis SSL HTTPS Resmi:** Tidak perlu setup Certbot lokal.
4. **Cepat & Terenkripsi:** Lalu lintas data melalui jaringan anycast global Cloudflare.
