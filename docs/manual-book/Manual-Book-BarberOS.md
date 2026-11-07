# BUKU PANDUAN PENGGUNA LENGKAP (FULL SYSTEM MANUAL BOOK)
## BarberOS - SaaS Platform Manajemen Barbershop Multi-Tenant

**Edisi:** Multi-Role (Superadmin, Owner Barbershop, Kasir, Barber, dan Pelanggan)  
**Versi:** 1.0.0 (November 2026)  
**Dokumen Cetak PDF (A4 Print-Ready):** `docs/manual-book/Manual-Book-BarberOS.html`

---

## DAFTAR ISI MODUL
1. [MODUL 1: Panduan Superadmin Platform SaaS](#modul-1-panduan-superadmin-platform-saas)
   - 1.1 Dashboard Utama Platform (`/platform`)
   - 1.2 Manajemen Tenant Barbershop (`/platform/tenants`)
   - 1.3 Paket Langganan SaaS & Konfirmasi Invoice (`/platform/plans` & `/platform/subscriptions`)
   - 1.4 Pencairan Dana Escrow Booking ke Owner Barbershop (`/platform/payouts`)
   - 1.5 Verifikasi & Persetujuan Penarikan Saldo Customer (`/platform/withdrawals`)
   - 1.6 Monitoring Transaksi, Pengumuman Broadcast, & Laporan Global (`/platform/transactions`, `/platform/announcements`, `/platform/reports`)
2. [MODUL 2: Panduan Owner Barbershop](#modul-2-panduan-owner-barbershop)
   - 2.1 Manajemen Cabang / Multi-Outlet (`/owner/branches`)
   - 2.2 Manajemen Staf & Hak Akses Kasir/Barber (`/owner/staff`)
   - 2.3 Sistem Presensi QR, Shift Kerja, Toleransi, & Izin Libur (`/owner/schedules`, `/owner/time-offs`, `/owner/attendances`, `/owner/schedule-settings`)
   - 2.4 Rekap Payroll Bulanan, Standar Komisi, & Cetak Slip Gaji (`/owner/payroll`, `/owner/payroll/staff`, `/owner/payroll/rules`)
   - 2.5 Master Katalog Layanan & Setting Stok Pewarnaan Rambut (`/owner/services`, `/owner/colors`)
   - 2.6 CRM Database Pelanggan & Analitik Laporan (`/owner/customers`, `/owner/reports`)
   - 2.7 Rekening Bank, Unggah QRIS Outlet, & Saldo Escrow (`/owner/settings`, `/owner/payouts`, `/owner/subscription`)
3. [MODUL 3: Panduan Kasir & Staf Front-Office](#modul-3-panduan-kasir--staf-front-office)
   - 3.1 Prosedur Melayani Pelanggan Walk-In (Datang Langsung) (`/cashier`)
   - 3.2 Pemanggilan Suara Text-to-Speech & Kontrol Antrean
   - 3.3 Check-In Tamu Booking Online & Penempatan Kursi
   - 3.4 Proses Checkout POS, Pembayaran Tunai/QRIS, & Cetak Struk Kasir
4. [MODUL 4: Panduan Kapster / Barber](#modul-4-panduan-kapster--barber)
   - 4.1 Mengatur Status Ketersediaan Kursi (Ready, On Service, Break) (`/barber`)
   - 4.2 Pelayanan Antrean & Notifikasi Selesai Pangkas
   - 4.3 Rekap Komisi Pendapatan Pribadi & Presensi QR Mandiri (`/barber/earnings`, `/barber/attendance`)
5. [MODUL 5: Panduan Pelanggan (Customer)](#modul-5-panduan-pelanggan-customer)
   - 5.1 Reservasi Booking Online & Pembayaran QRIS Instan (`/b/{tenant}`)
   - 5.2 Pembatalan Reservasi & Garansi Saldo Dompet Refund 100%
   - 5.3 Penggunaan Saldo Refund untuk Reservasi Booking Baru
   - 5.4 Penarikan Tunai Saldo ke Rekening Bank / E-Wallet Bebas Biaya Admin (`/customer/profile`)
6. [SOP & Protokol Penyelesaian Masalah (Troubleshooting)](#sop--protokol-penyelesaian-masalah-troubleshooting)

---

## MODUL 1: Panduan Superadmin Platform SaaS

Superadmin memegang kendali tertinggi atas platform SaaS multi-tenant, mengelola tenant barbershop, verifikasi invoice langganan, settlement dana escrow, dan monitoring transaksi lintas outlet.

### 1.1 Dashboard Utama Platform (`/platform`)
Menampilkan metrik bisnis platform secara real-time:
- **Total GMV (Gross Merchandise Value)**: Akumulasi perputaran uang seluruh transaksi di ekosistem platform.
- **Tenant Aktif & Pertumbuhan**: Jumlah barbershop berlangganan aktif vs expired/trial.
- **Pending Actions**: Notifikasi permohonan penarikan dana customer dan konfirmasi invoice langganan yang memerlukan verifikasi manual.

### 1.2 Manajemen Tenant Barbershop (`/platform/tenants`)
- **Pendaftaran Tenant Baru**: Klik `+ Tambah Barbershop`, tentukan nama brand, subdomain slug unik (`domain.com/b/nama-barbershop`), email, dan nomor kontak pemilik.
- **Reset Password Owner**: Fitur darurat jika pemilik barbershop kehilangan akses masuk ke sistem.
- **Suspend / Aktifkan Tenant**: Menangguhkan operasional tenant yang menunggak atau melanggar ketentuan layanan.

### 1.3 Paket Langganan SaaS & Konfirmasi Invoice (`/platform/plans` & `/platform/subscriptions`)
- **Konfigurasi Paket Harga**: Membuat dan mengedit paket berlangganan bulanan/tahunan (Starter, Growth, Enterprise) dengan kuota cabang dan batasan barber.
- **Konfirmasi Pembayaran Invoice**: Saat Owner mengunggah resi transfer pembayaran langganan, Superadmin memverifikasi bukti bayar dan menekan tombol **Konfirmasi Pembayaran**. Status barbershop otomatis aktif.

### 1.4 Pencairan Dana Escrow Booking ke Owner Barbershop (`/platform/payouts`)
Setiap pembayaran reservasi booking online via QRIS platform ditampung di rekening escrow platform.
1. Masuk ke menu `Pencairan Owner` (`/platform/payouts`).
2. Tinjau total saldo omzet booking outlet yang layanannya telah selesai (*completed*).
3. Lakukan transfer bank dari rekening platform ke rekening bank resmi Owner barbershop.
4. Klik **ACC Pencairan** dan input nomor referensi transfer.

### 1.5 Verifikasi & Persetujuan Penarikan Saldo Customer (`/platform/withdrawals`)
Digunakan untuk mencairkan saldo dompet customer hasil refund booking:
1. **Salin Rekening/E-Wallet**: Klik icon **Salin** 1-klik di samping nomor tujuan penerima (DANA, GoPay, OVO, BCA, dll).
2. **Transfer Manual Bebas Biaya Admin**: Buka aplikasi perbankan atau e-wallet, kirim dana utuh sesuai nominal yang diajukan tanpa potongan admin.
3. **Persetujuan ACC (Centang Hijau Solid)**: Unggah bukti transfer (opsional) pada modal pop-up, lalu tekan **ACC & Selesaikan Transfer**. Saldo dompet customer resmi terdebit saat tombol ini ditekan.
4. **Penolakan (Silang Merah Solid)**: Jika nama rekening atau nomor salah, klik tolak dan isi alasan penolakan. Saldo customer tetap aman dan tidak terpotong.

### 1.6 Monitoring Transaksi, Pengumuman Broadcast, & Laporan Global
- **Laporan Transaksi Global (`/platform/transactions`)**: Audit seluruh riwayat pembayaran beserta ekspor data ke file CSV/Excel.
- **Pengumuman Global (`/platform/announcements`)**: Publikasikan informasi maintenance, tips bisnis, atau pembaruan sistem yang akan langsung tampil di dashboard seluruh tenant.

---

## MODUL 2: Panduan Owner Barbershop

Owner mengelola seluruh aspek operasional bisnis, staf karyawan, pengaturan outlet, dan integrasi pembayaran.

### 2.1 Manajemen Cabang / Multi-Outlet (`/owner/branches`)
- Menambah outlet cabang baru lengkap dengan jam buka-tutup dan alamat fisik.
- Pengaturan koordinat Google Maps untuk pembatasan radius presensi staf.

### 2.2 Manajemen Staf & Hak Akses Kasir/Barber (`/owner/staff`)
- Mendaftarkan akun login staf: Tentukan nama, username/email, password, dan hak peran (**Kasir** atau **Barber**).
- Mengatur penugasan cabang tugas untuk masing-masing karyawan.

### 2.3 Sistem Presensi QR, Shift Kerja, Toleransi, & Izin Libur
- **Jadwal Shift Kerja (`/owner/schedules`)**: Buat jadwal kerja mingguan/bulanan untuk tiap kapster.
- **Pengajuan Cuti / Izin Libur (`/owner/time-offs`)**: Tinjau pengajuan izin staf. Staf yang disetujui cuti otomatis tidak dapat dipilih pada slot reservasi pelanggan hari tersebut.
- **Scanner QR Absen Karyawan (`/owner/attendances/scanner`)**: Fitur kamera in-app bagi owner untuk memindai kode QR identitas staf.
- **Pengaturan Toleransi & QR Dinamis (`/owner/schedule-settings`)**: Atur batas toleransi terlambat (misal: 15 menit). Cetak QR Code unik cabang untuk ditempel di meja kasir.

### 2.4 Rekap Payroll Bulanan, Standar Komisi, & Cetak Slip Gaji
- **Standar Skema Gaji & Komisi (`/owner/payroll/rules`)**: Menentukan persentase bagi hasil pangkas rambut atau komisi penjualan produk (misal: 35% untuk barber).
- **Gaji Pokok & Tunjangan (`/owner/payroll/staff`)**: Mengatur nominal gaji dasar dan uang makan harian.
- **Generate Payroll Otomatis (`/owner/payroll`)**: Sistem menghitung total gaji: Gaji Pokok + Total Komisi Layanan &minus; Denda Keterlambatan Absensi.
- **Cetak Slip Gaji**: Unduh slip gaji berformat PDF untuk arsip akuntansi dan diserahkan ke staf.

### 2.5 Master Katalog Layanan & Setting Stok Pewarnaan Rambut
- **Daftar Layanan & Harga (`/owner/services`)**: Menambah paket potong rambut, shaving, cuci rambut, hair spa, durasi pengerjaan, dan variasi harga per cabang.
- **Setting Warna & Stok Cat Rambut (`/owner/colors`)**: Mengelola inventaris cat rambut (nomor seri formula warna, merk, stok tube) yang terhubung langsung saat kasir melakukan checkout POS.

### 2.6 CRM Database Pelanggan & Analitik Laporan
- **CRM Customer (`/owner/customers`)**: Database kontak pelanggan, frekuensi kunjungan, dan preferensi gaya rambut langganan.
- **Laporan Bisnis & Review (`/owner/reports`)**: Grafik omzet, peringkat barber terlaris, ekspor data, dan fitur membalas feedback review dari pelanggan.
- **Refund Transaksi Langsung di Tempat**: Kasir/owner dapat memproses pengembalian uang tunai untuk transaksi walk-in jika terjadi pembatalan sebelum dicukur.

### 2.7 Rekening Bank, Unggah QRIS Outlet, & Saldo Escrow
- **Pengaturan Barbershop & QRIS (`/owner/settings`)**: Input rekening bank penerimaan pencairan dan unggah stiker barcode QRIS outlet Anda pada kotak dropzone interaktif agar pelanggan dapat langsung men-scan kode dari HP.
- **Saldo Booking Escrow (`/owner/payouts`)**: Memantau dana reservasi booking online yang ditampung platform dan status transfer pencairan dari Superadmin.
- **Langganan SaaS Barbershop (`/owner/subscription`)**: Mengelola tagihan paket aplikasi, memperpanjang masa aktif, atau upgrade paket.

---

## MODUL 3: Panduan Kasir & Staf Front-Office

Kasir bertugas mengelola antrean harian, memanggil pelanggan, melayani check-in booking, dan memproses kasir POS.

### 3.1 Prosedur Melayani Pelanggan Walk-In (Datang Langsung) (`/cashier`)
1. Buka menu **Papan Antrean & POS** (`/cashier`).
2. Klik tombol **+ Antrean Baru**.
3. Masukkan nama pelanggan dan nomor telepon.
4. Pilih paket layanan pangkas dan pilih barber yang tersedia (atau opsi siapa saja).
5. Klik **Simpan Tiket**. Tiket antrean (misal: A-005) otomatis masuk ke antrean aktif.

### 3.2 Pemanggilan Suara Text-to-Speech & Kontrol Antrean
- **Panggil Antrean (Voice Call)**: Klik tombol **Panggil**. Sistem memutar suara audio text-to-speech otomatis (*"Nomor antrean A-005 silakan menuju kursi 2"*).
- **Panggil Ulang (Recall)**: Untuk memanggil kembali jika tamu belum masuk.
- **Lewati / Tunda (Skip)**: Menunda nomor antrean ke urutan belakang jika tamu sedang meninggalkan ruang tunggu.

### 3.3 Check-In Tamu Booking Online
1. Pada papan antrean kasir, buka tab **Booking Hari Ini**.
2. Cari nama atau kode tiket reservasi pelanggan (misal: `BK-SDR-8921`).
3. Klik tombol **Check-In**. Tiket langsung masuk ke antrean kursi barber yang telah dipesan secara prioritas.

### 3.4 Proses Checkout POS, Pembayaran Tunai/QRIS, & Cetak Struk Kasir
1. Setelah pangkas rambut selesai, klik tombol **Checkout / Bayar**.
2. Tambahkan produk tambahan (pomade, hair tonic, atau cat rambut) jika ada.
3. Pilih metode pembayaran:
   - **Tunai**: Masukkan nominal uang yang diserahkan pelanggan, sistem otomatis menghitung uang kembalian.
   - **QRIS Kasir**: Tampilkan barcode QRIS outlet kepada pelanggan untuk dipindai.
4. Klik **Selesaikan Transaksi** dan cetak nota kasir thermal ukuran 58mm/80mm.

---

## MODUL 4: Panduan Kapster / Barber

Aplikasi khusus kapster dirancang ringkas untuk pengoperasian cepat melalui smartphone atau tablet di samping kursi pangkas.

### 4.1 Mengatur Status Ketersediaan Kursi (Work Status) (`/barber`)
- **Siap Melayani (Ready)**: Aktifkan status saat siap menerima pelanggan berikutnya.
- **Sedang Melayani (On Service)**: Menandakan kursi sedang sibuk mencukur rambut.
- **Istirahat (Break / Off)**: Menonaktifkan giliran Anda saat istirahat makan/sholat agar tidak ditugaskan antrean baru.

### 4.2 Pelayanan Antrean & Notifikasi Selesai Pangkas
1. Periksa detail tiket pelanggan di layar kursi Anda (nama tamu, referensi potongan rambut).
2. Setelah proses mencukur dan merapikan rambut tuntas, klik tombol **Selesai Pangkas**.
3. Sistem otomatis mengabari layar kasir untuk memproses pembayaran checkout.

### 4.3 Rekap Komisi Pendapatan Pribadi & Presensi QR Mandiri (`/barber/earnings` & `/barber/attendance`)
- **Pantau Komisi Harian**: Melihat akumulasi rupiah komisi bagi hasil yang didapat secara transparan per pelanggan yang selesai dipangkas.
- **Presensi Masuk & Pulang**: Arahkan kamera HP ke barcode QR Cabang di meja kasir untuk Clock-In dan Clock-Out.
- **Pengajuan Cuti / Izin**: Mengajukan hari libur langsung lewat aplikasi HP.

---

## MODUL 5: Panduan Pelanggan (Customer)

Customer dapat mengakses portal web barbershop untuk melakukan reservasi tanpa antre, menikmati refund instan ke saldo dompet, dan tarik tunai bebas biaya admin.

### 5.1 Reservasi Booking Online & Pembayaran QRIS Instan (`/b/{tenant}`)
1. Buka tautan barbershop melalui browser HP atau komputer (misal: `domain.com/b/gentleman`).
2. Pilih cabang, jenis paket pangkas rambut, dan kapster pilihan.
3. Tentukan tanggal dan jam slot kedatangan yang tersedia.
4. Lakukan pembayaran instan via pemindaian QRIS dari aplikasi m-banking atau e-wallet Anda. Tiket reservasi digital otomatis terbit.

### 5.2 Pembatalan Reservasi & Garansi Saldo Dompet Refund 100%
- Jika memiliki keperluan mendadak, buka menu **Riwayat Booking** lalu klik **Batalkan Reservasi**.
- Uang pembayaran Anda 100% langsung kembali detik itu juga ke **Saldo Dompet Akun** tanpa potongan biaya admin dan tanpa perlu repot mengisi rekening manual.

### 5.3 Penggunaan Saldo Refund untuk Reservasi Booking Baru
- Saat memesan jadwal pangkas berikutnya, pada tahap pembayaran checkout cukup centang opsi **Gunakan Saldo Dompet**.
- Tagihan langsung terbayar lunas seketika dari saldo dompet Anda tanpa transfer uang baru.

### 5.4 Penarikan Tunai Saldo ke Rekening Bank / E-Wallet Bebas Biaya Admin (`/customer/profile`)
1. Buka menu **Profil Pelanggan** (`/customer/profile`).
2. Pada kartu saldo dompet, klik tombol **Tarik Dana**.
3. Masukkan nominal penarikan (minimal Rp10.000).
4. Pilih bank tujuan (BCA, Mandiri, BRI, BNI) atau dompet digital (DANA, GoPay, OVO, ShopeePay).
5. Masukkan nomor rekening atau nomor HP akun e-wallet Anda.
6. Klik **Ajukan Penarikan Dana**. Tim Admin Platform akan mentransfer dana utuh tanpa biaya admin (Rp0) ke rekening Anda dalam 1x24 jam.

---

## SOP & Protokol Penyelesaian Masalah (Troubleshooting)

| Kendala Teknis | Penyebab Umum | Solusi Operasional |
| :--- | :--- | :--- |
| **Audio Panggilan Kasir Tidak Bersuara** | Browser memblokir izin autoplay audio atau volume komputer kasir dalam kondisi mute. | Klik sembarang area di layar browser kasir sekali untuk mengaktifkan izin audio, dan pastikan kabel speaker aktif terhubung. |
| **Karyawan Gagal Scan QR Presensi** | Lensa kamera kotor atau browser belum diizinkan mengakses kamera / GPS lokasi. | Bersihkan kamera HP dan berikan izin (*Allow*) akses kamera serta izin lokasi perangkat di browser. |
| **Permohonan Tarik Dana Customer Ditolak** | Nomor rekening atau e-wallet salah ketik atau atas nama rekening tidak ditemukan di bank. | Superadmin memberikan catatan penolakan. Saldo customer tetap aman dan customer dapat mengajukan kembali dengan nomor rekening yang benar. |
| **Layar TV Antrean Tidak Update** | Koneksi internet terputus atau mode browser standby. | Muat ulang (*Refresh*) halaman browser TV dan tekan tombol **F11** untuk kembali ke mode layar penuh (*Fullscreen*). |
