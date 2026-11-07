# BarberOS — Platform Manajemen Barbershop Multi-Tenant SaaS

BarberOS adalah platform SaaS multi-tenant modern untuk manajemen operasional barbershop, sistem antrean (*queue*), reservasi (*booking*), kasir & POS, TV display antrean per cabang, penggajian & komisi (*payroll*), integrasi pembayaran otomatis **Xendit QRIS (Escrow)**, serta pencairan dana (*payout*) ke owner barbershop.

---

## 🌟 Fitur Utama Sistem

1. **Multi-Tenant Terisolasi**: Setiap barbershop memiliki outlet, staf, layanan, antrean, omzet, dan rekening bank masing-masing yang terpisah aman.
2. **Booking & Antrean Fleksibel**: Pelanggan dapat memesan jadwal potong rambut online (*booking*) atau langsung mengambil antrean di tempat (*walk-in*).
3. **Pembayaran Gateway Xendit Eksklusif QRIS**:
   - Seluruh pembayaran booking online diproses via QRIS (dapat di-scan dengan m-Banking BCA, Mandiri, BRI, BNI, maupun E-Wallet GoPay, OVO, DANA, ShopeePay).
   - Menggunakan model penampungan terpusat (*Platform Escrow*): uang masuk ke akun Xendit resmi platform (Superadmin).
   - Verifikasi otomatis instan via webhook tanpa perlu unggah struk transfer.
   - Tiket antrean resmi (`B-xxx`) langsung terbit otomatis begitu QRIS dibayar.
   - Livewire real-time auto-sync (polling per 5 detik) di layar pelanggan.
4. **Modul Pencairan Dana (*Platform & Owner Payout*)**:
   - Superadmin dapat memantau akumulasi saldo mengendap per tenant dan mencairkan dana (*payout*) ke rekening bank / e-wallet owner.
   - Owner dapat memantau saldo mengendap, mengatur rekening tujuan pencairan, serta melihat riwayat transfer dan foto bukti struk dari Superadmin.
5. **Papan Antrean Kasir (*Cashier Kanban Board*)**: Manajemen antrean livewire interaktif (Menunggu, Dipanggil, Sedang Dicukur, Selesai).
6. **TV Display Antrean Publik**: Tampilan display nomor antrean untuk layar TV di ruang tunggu cabang barbershop.
7. **Penggajian & Komisi (*Payroll*)**: Skema gaji pokok, komisi layanan persentase/flat, pencatatan kasbon, potongan, dan cetak slip gaji.

---

## 📋 Prasyarat Sistem

* **PHP**: Versi 8.2 atau 8.3 (dengan ekstensi `pdo_mysql`, `curl`, `mbstring`, `openssl`, `bcmath`, `fileinfo`)
* **Database**: MySQL 8.0+ atau MariaDB 10.5+
* **Composer**: Versi 2.x
* **Node.js & NPM**: Versi 18+ atau 20+
* **Web Server**: Nginx atau Apache

---

## 🛠️ Panduan Instalasi Lokal (Local Development)

### 1. Kloning Repositori & Install Dependensi
```bash
git clone <url-repo-anda> barberos
cd barberos

# Install dependensi PHP & Node.js
composer install
npm install
npm run build
```

### 2. Konfigurasi File Environment
Salin file `.env.example` menjadi `.env`:
```bash
cp .env.example .env
php artisan key:generate
```

Sesuaikan koneksi database MySQL Anda di `.env`:
```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=barberdb
DB_USERNAME=root
DB_PASSWORD=
```

### 3. Migrasi & Seeder Database
```bash
php artisan migrate --seed
php artisan storage:link
```

### 4. Jalankan Server Lokal
```bash
php artisan serve
```
Akses aplikasi melalui browser di: `http://localhost:8000`

---

## 💳 Panduan Konfigurasi Xendit Payment Gateway (QRIS)

Sistem menggunakan gateway pembayaran resmi **Xendit eksklusif QRIS**. Ikuti panduan berikut untuk menghubungkan sistem dengan Xendit:

### 1. Pendaftaran Akun Xendit
1. Buka [dashboard.xendit.co](https://dashboard.xendit.co) dan buat akun platform Anda.
2. Gunakan **Mode Uji Coba (*Test Mode*)** untuk tahap pengembangan lokal.

### 2. Mengambil Kunci API (*Secret Key*)
1. Masuk ke **Pengaturan (*Settings*) → Kunci API (*API Keys*)**.
2. Klik **Buat Kunci Rahasia (*Generate Secret Key*)**:
   - Nama Kunci: `BarberOS Gateway`
   - Izin **Money-in products (Invoices, QRIS)**: Pilih **`WRITE`** *(Wajib agar sistem bisa menerbitkan invoice QRIS)*.
   - Izin **Money-out products (Disbursements)**: Pilih **`NONE`** *(Pencairan dana ke owner dilakukan via m-banking Superadmin)*.
3. Salin Secret Key yang muncul (diawali `xnd_development_...` untuk test mode).

### 3. Mengatur Webhook Callback URL & Salin Token Verifikasi
1. Masuk ke **Pengaturan (*Settings*) → Webhooks** (atau *Callbacks*).
2. Salin **Token Verifikasi Callback (*Verification Token*)**.
3. Pada baris **Faktur Terbayar (*Invoice Paid / Invoices*)**, masukkan URL webhook:
   - **Server Produksi (Online):**
     ```text
     https://namadomainanda.com/api/webhooks/xendit/invoice
     ```
   - **Pengujian Lokal (Localhost menggunakan Ngrok):**
     Jalankan di terminal laptop: `ngrok http 8000`
     Lalu masukkan URL publik Ngrok:
     ```text
     https://xxxx-xx.ngrok-free.app/api/webhooks/xendit/invoice
     ```
4. Klik **Simpan (*Save*)** atau **Test and Save**.

### 4. Masukkan Kredensial ke `.env`
Buka file `.env` dan isi variabel berikut:
```dotenv
# ==========================================
# XENDIT PAYMENT GATEWAY
# ==========================================
XENDIT_SECRET_KEY=xnd_development_xxxxxxxxxxxxxxxxxxxx
XENDIT_WEBHOOK_TOKEN=token_verifikasi_dari_menu_webhooks
```

Setelah disimpan, jalankan:
```bash
php artisan config:clear
```

### 5. Beralih ke Mode Live (Uang Rupiah Asli)
Jika sudah siap menerima pembayaran asli dari pelanggan:
1. Selesaikan verifikasi identitas (KYC) di Xendit (unggah KTP/NPWP perorangan atau badan usaha).
2. Aktifkan metode pembayaran **QRIS** di menu *Payment Methods*.
3. Pindahkan toggle dashboard Xendit ke **Mode Live**.
4. Buat Secret Key dan ambil Webhook Token versi Live (diawali `xnd_production_...`).
5. Ganti variabel di file `.env` server produksi Anda.

---

## 🚀 Panduan Deployment ke Server Produksi (VPS / Cloud)

Aplikasi menggunakan single-domain standar (tanpa kebutuhan wildcard DNS). Cukup arahkan domain utama ke server Anda.

### 1. Konfigurasi DNS Standar
| Tipe | Nama / Host | Target / Nilai | Keterangan |
| :--- | :--- | :--- | :--- |
| **A** | `@` | `IP_SERVER_ANDA` | Domain utama platform |
| **CNAME** | `www` | `domainanda.com` | Alias www (opsional) |

### 2. Konfigurasi Nginx (Contoh: `/etc/nginx/sites-available/barberos.conf`)
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name domainanda.com www.domainanda.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name domainanda.com www.domainanda.com;

    root /var/www/barberos/public;
    index index.php index.html;

    # SSL Certbot Let's Encrypt
    ssl_certificate /etc/letsencrypt/live/domainanda.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/domainanda.com/privkey.pem;

    charset utf-8;
    client_max_body_size 15M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 3. Sertifikat SSL Gratis (Certbot)
```bash
sudo certbot --nginx -d domainanda.com -d www.domainanda.com
```

### 4. Checklist Perintah Deploy
```bash
# 1. Update source code
git pull origin main

# 2. Install dependensi
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 3. Migrasi database
php artisan migrate --force

# 4. Storage link & optimasi cache
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Restart worker & PHP-FPM
sudo systemctl restart php8.2-fpm
php artisan queue:restart
```

---

## 👥 Pembagian Role & Hak Akses

| Role | Akses URL | Deskripsi |
| :--- | :--- | :--- |
| **Superadmin** | `/platform/*` | Operator SaaS: Manajemen barbershop/tenant, paket langganan, **pencairan dana booking (Payouts)**, dan konfigurasi platform. |
| **Owner** | `/owner/*` | Pemilik Barbershop: Manajemen cabang, karyawan & barber, layanan & harga, shift kerja, komisi & payroll, dan **pantau saldo & rekening pencairan**. |
| **Kasir (Admin)** | `/cashier` & `/cashier/board` | Kasir Operasional: Papan antrean Kanban, input walk-in, check-in booking, pembayaran kasir, dan absensi cabang. |
| **Barber** | `/barber` | Staf Barber: Papan antrean pribadi di kursi, panggil/mulai/selesai potong, dan pantau komisi harian. |
| **Customer** | `/customer/*` | Pelanggan Publik: Booking jadwal potong, pembayaran via QRIS otomatis, tracking nomor tiket antrean (`B-xxx`), dan riwayat booking. |
| **Display TV** | `/b/{tenant}/tv/{branchCode}` | Layar TV Antrean Publik di ruang tunggu cabang barbershop. |

---

## 🧪 Menjalankan Pengujian Otomatis (Testing)

Aplikasi memiliki pengujian fitur otomatis menyeluruh menggunakan PHPUnit:

```bash
# Menjalankan seluruh rangkaian tes
php artisan test

# Menjalankan pengujian integrasi Xendit QRIS Webhook
php artisan test --filter=XenditQrisWebhookTest

# Menjalankan pengujian pencairan dana platform & owner (Payout)
php artisan test --filter=PlatformAndOwnerPayoutTest

# Menjalankan pengujian portal pelanggan & auto-refresh Livewire
php artisan test --filter=CustomerBookingTrackerLivewireTest
```

---

## 📄 Lisensi

Aplikasi ini dikembangkan untuk penggunaan komersial platform SaaS Barbershop. Hak cipta dilindungi undang-undang.
