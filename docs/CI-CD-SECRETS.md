# Panduan Nilai GitHub Secrets untuk Auto-Deploy (CI/CD)

Buka repositori GitHub:  
**Settings** -> **Secrets and variables** -> **Actions** -> klik **New repository secret**.

Tambahkan 3 secret berikut:

---

## 1. Secret: `VPS_HOST`
- **Name:**
```text
VPS_HOST
```
- **Secret:**
```text
66.42.48.27
```

---

## 2. Secret: `VPS_USER`
- **Name:**
```text
VPS_USER
```
- **Secret:**
```text
root
```

---

## 3. Secret: `VPS_SSH_PRIVATE_KEY`
- **Name:**
```text
VPS_SSH_PRIVATE_KEY
```
- **Pilihan A (Decode instan via base64):**
Salin teks ter-encode base64 berikut:
```text
LS0tLS1CRUdJTiBPUEVOU1NIIFBSSVZBVEUgS0VZLS0tLS0KYjNCbGJuTnphQzFyWlhrdGRqRUFBQUFBQkc1dmJtVUFBQUFFYm05dVpRQUFBQUFBQUFBQkFBQUFNd0FBQUF0emMyZ3RaVwpReU5UVXhPUUFBQUNDdUdmaHJpQlhKYk1sd1Bvc1dBdGh1T2FpcStmMXZKM0thbitnTjdTUkZlZ0FBQUtDTG9laC9pNkhvCmZ3QUFBQXR6YzJndFpXUXlOVFV4T1FBQUFDQ3VHZmhyaUJYSmJNbHdQb3NXQXRodU9haXErZjF2SjNLYW4rZ043U1JGZWcKQUFBRUNEdzdtRStwZG9xbEs1L0tVOC9ra0JwbXlhbFptSFpkNStuSFlySmtaa3c2NForR3VJRmNsc3lYQStpeFlDMkc0NQpxS3I1L1c4bmNwcWY2QTN0SkVWNkFBQUFHblYwWlMxd1lYSjBjeTFrWlhCc2IzbEFUbVYzTFdsdVpISmhBUUlECi0tLS0tRU5EIE9QRU5TU0ggUFJJVkFURSBLRVktLS0tLQo=
```
Lalu decode di komputer Anda (misal via web `base64decode.org` atau terminal `base64 -d`) dan tempelkan hasilnya ke form Secret.

- **Pilihan B (Bentuk Teks Asli Langsung):**
```text
[BEGIN_OPENSSH_KEY]
b3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAABAAAAMwAAAAtzc2gtZW
QyNTUxOQAAACCuGfhriBXJbMlwPosWAthuOaiq+f1vJ3Kan+gN7SRFegAAAKCLoeh/i6Ho
fwAAAAtzc2gtZWQyNTUxOQAAACCuGfhriBXJbMlwPosWAthuOaiq+f1vJ3Kan+gN7SRFeg
AAAECDw7mE+pdoqlK5/KU8/kkBpmyalZmHZd5+nHYrJkZkw64Z+GuIFclsyXA+ixYC2G45
qKr5/W8ncpqf6A3tJEV6AAAAGnV0ZS1wYXJ0cy1kZXBsb3lATmV3LWluZHJhAQID
[END_OPENSSH_KEY]
```
*(Ganti tanda kurung siku `[BEGIN_OPENSSH_KEY]` menjadi `-----BEGIN OPENSSH PRIVATE KEY-----` dan `[END_OPENSSH_KEY]` menjadi `-----END OPENSSH PRIVATE KEY-----` saat menempel ke GitHub Secret).*

---

## Cara Verifikasi Setelah Ketiganya Disimpan

1. Buka tab **Actions** di GitHub.
2. Klik workflow **`CI/CD Pipeline (Auto-Deploy)`** di sebelah kiri.
3. Klik tombol **Run workflow** (pilih branch `main`).
4. Pipeline akan langsung mengeksekusi deployment ke server secara otomatis.
