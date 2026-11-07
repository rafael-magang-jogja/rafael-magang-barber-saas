# BUSINESS REQUIREMENTS DOCUMENT (BRD)
## BarberOS &mdash; Multi-Tenant Barbershop Operating System & SaaS Platform

* **Dokumen**: Spesifikasi Kebutuhan Bisnis & Arsitektur Sistem
* **Versi**: 1.0 (Production-Ready)
* **Tanggal**: November 2026
* **Klasifikasi**: Official Technical Architecture & Internship Project Deliverables

---

## 1. Ringkasan Eksekutif (Executive Summary)

**BarberOS** adalah platform Software-as-a-Service (SaaS) Multi-Tenant berbasis web yang dirancang untuk mendigitalisasi seluruh rantai operasional bisnis pangkas rambut modern (*barbershop*). Sistem ini menyediakan solusi terpadu mulai dari manajemen antrean (*smart queue*), kasir pintar POS, reservasi jadwal online berbasis scan QRIS resmi Xendit, dompet refund saldo pelanggan (*Customer Wallet*), pembatasan kuota cabang multi-tenant, hingga perhitungan otomatis komisi dan penggajian (*payroll*) staf.

### Tujuan Utama Sistem:
1. Menghilangkan antrean fisik yang menumpuk melalui integrasi antrean walk-in kasir dan reservasi online berbasis slot waktu.
2. Menghapus risiko kerugian finansial (*tekor*) kasir/owner barbershop saat terjadi pembatalan booking melalui arsitektur **Escrow Platform & Saldo Dompet Customer**.
3. Menerapkan model bisnis langganan SaaS berbasis kuota cabang (*Starter vs Pro*) dengan proteksi akses operasional otomatis.
4. Menyediakan transparansi data operasional, absensi bersertifikasi QR, dan komisi barber secara real-time.

---

## 2. Matriks Aktor & Peran Pengguna (Role-Based Access Control)

| Peran (Role) | Lingkup Akses | Tanggung Jawab & Fitur Utama |
| :--- | :--- | :--- |
| **Superadmin** | Platform Global | Mengelola pendaftaran tenant (barbershop), memonitor pendapatan SaaS, meng-ACC dan mentransfer penarikan saldo refund customer (Withdrawal), serta mentransfer pencairan saldo hak tenant. |
| **Owner (Tenant)** | Barbershop (Semua Cabang) | Konfigurasi brand toko, kelola cabang, layanan & harga, manajemen staf, jadwal shift & rekap absensi, persetujuan cuti/izin, slip gaji payroll, serta memantau saldo booking escrow. |
| **Kasir (Cashier)** | Cabang Tertentu | Operasional POS kasir, input antrean walk-in tamu, panggil nomor antrean, check-in tiket booking pelanggan, penerimaan pembayaran tunai/QRIS, serta scan absensi kehadiran harian. |
| **Barber (Staff)** | Cabang & Akun Pribadi | Melihat antrean pelanggan yang ditugaskan ke kursinya, memperbarui status pengerjaan pangkas (*in-service* ke *completed*), cek rekap komisi pangkas, serta scan absen mandiri. |
| **Customer** | Portal Publik & Profil Akun | Reservasi booking jadwal, bayar via scan QRIS Xendit, tracking posisi nomor antrean, Saldo Dompet Refund, pembayaran booking menggunakan saldo, dan penarikan saldo (*Withdrawal*). |

---

## 3. Arsitektur Escrow Booking & Saldo Dompet Customer

Sistem BarberOS menerapkan pemisahan rekening penampung antara Superadmin (Platform Escrow) dan Kas Operasional Barbershop (Tenant), guna melindungi kedua belah pihak dari perselisihan finansial saat customer membatalkan jadwal.

```
[1. CUSTOMER]
      │ (Bayar Booking Online QRIS)
      ▼
[2. ESCROW SUPERADMIN (Xendit Platform)]
      │
      ├───────────────────────────────┐
      │ (Layanan Selesai Dikerjakan)   │ (Customer Batalkan Booking)
      ▼                               ▼
[3. PENCAIRAN KE OWNER]         [4. SALDO DOMPET CUSTOMER]
Saldo Siap Dicairkan ke Rekening  100% Refund Otomatis Masuk Saldo
Bank / E-Wallet Resmi Barbershop  Dapat Dipakai Booking / Ditarik
                                      │
                                      ▼
                                [5. PENARIKAN DANA (WITHDRAWAL)]
                                Superadmin ACC & Transfer (Bebas Admin Rp0)
```

### Mekanisme Saldo Dompet Customer (Customer Wallet):
1. **Kredit Otomatis Saat Pembatalan**: Dana booking lunas QRIS dikembalikan 100% ke saldo akun customer secara otomatis. Kasir/Owner tidak perlu transfer manual.
2. **Fleksibilitas Checkout**: Saldo dompet dapat langsung digunakan untuk bayar booking jadwal berikutnya tanpa scan QRIS lagi.
3. **Pengajuan Tarik Dana (Withdrawal)**: Pelanggan dapat menarik saldo ke Bank (BCA, Mandiri, BRI, BNI) atau E-Wallet (DANA, GoPay, OVO, ShopeePay) dengan minimal Rp10.000 dan bebas biaya admin (Rp0).
4. **Alur Verifikasi Superadmin**: Saat customer meminta penarikan dana, saldo di-hold sementara dengan status *Menunggu ACC*. Superadmin memverifikasi dan meng-ACC transfer di halaman `/platform/withdrawals`.

---

## 4. Spesifikasi Modul Fungsional Aplikasi

### 4.1. Modul Manajemen Kuota Cabang SaaS
* **Paket Starter**: Maksimal 1 cabang aktif. Cabang ke-2 otomatis terkunci (akses kasir & TV dinonaktifkan sampai upgrade ke Pro).
* **Paket Pro**: Kuota hingga 5 cabang aktif, bebas kustomisasi warna tema brand hex.
* **Paket Enterprise**: Cabang tanpa batas (*unlimited*).

### 4.2. Modul Antrean & POS Kasir (Queue & Walk-In)
* Penomoran tiket otomatis (A-001, B-002) direset harian.
* Transisi status antrean: *Menunggu* &rarr; *Dipanggil* &rarr; *Sedang Dilayani* &rarr; *Selesai*.
* Fitur panggil suara (*Voice Announcer*) otomatis melalui speaker browser.
* Integrasi Layar TV Display Fullscreen dengan pemutar video YouTube dan teks berjalan (*running text*).

### 4.3. Modul Absensi QR Scanner & Shift Kerja
* Pengaturan shift jam masuk, jam pulang, dan toleransi keterlambatan.
* QR Scanner dinamis untuk presensi masuk/pulang staf via kamera HP.
* Manajemen permohonan libur, izin, dan cuti dengan approval Owner.

### 4.4. Modul Payroll & Komisi Barber
* Gaji pokok (harian/bulanan berdasarkan kehadiran).
* Komisi persentase otomatis per tiket layanan cukur yang diselesaikan.
* Komisi pengerjaan warna rambut (*hair coloring*).
* Pemotongan denda keterlambatan per menit otomatis.

---

## 5. Ringkasan Skema Entitas Data (Database Schema)

* **`tenants`**: Profil usaha barbershop, status langganan, dan data rekening pencairan.
* **`branches`**: Outlet fisik dan status proteksi kuota paket (*locked/unlocked*).
* **`bookings`**: Reservasi jadwal customer, nominal transaksi, dan status escrow/refund.
* **`queue_tickets`**: Nomor tiket antrean, sequence urutan, dan barber yang bertugas.
* **`customer_wallet_transactions`**: Buku besar (*ledger*) mutasi saldo masuk/keluar customer.
* **`customer_withdrawals`**: Tiket permohonan penarikan saldo customer ke rekening bank/e-wallet.
* **`payouts`**: Rekap bukti transfer pencairan saldo booking platform ke Owner barbershop.

---

## 6. Pengesahan Dokumen (Sign-Off)

* **Diajukan oleh**: Lead Software Engineer & Technical Architect (Rafael & Tim Pengembang)
* **Disetujui oleh**: Direksi & Stakeholder Platform BarberOS SaaS
