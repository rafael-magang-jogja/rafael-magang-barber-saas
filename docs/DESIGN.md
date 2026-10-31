# DESIGN.md — Desain UI Barbershop SaaS

> Panduan visual & UX untuk seluruh role. Target: **modern, rapi, mobile-first, mudah dipahami**.
> Stack UI: Tailwind CSS 4 + Livewire 3 + Alpine.js + Lucide Icons.

---

## 1. Prinsip Desain

1. **Mobile-first** — desain untuk lebar 375px dahulu, lalu diperluas ke tablet (768px) & desktop (1280px).
2. **Satu layar, satu tujuan** — setiap halaman punya satu aksi utama yang jelas (tombol primer).
3. **Ibu jari dulu** — aksi utama berada di bagian bawah layar HP (sticky bottom bar / bottom nav).
4. **Angka penting dibuat besar** — nomor antrean, omzet, komisi, estimasi waktu.
5. **Warna = makna** — status selalu menggunakan warna yang sama di semua halaman.
6. **Bahasa sederhana** — "Ambil Antrean", "Mulai Cukur", bukan istilah teknis.
7. **Feedback instan** — toast, skeleton, animasi halus pada setiap aksi.

---

## 2. Design Tokens

### 2.1 Warna

Tema default: **"Charcoal & Gold"** — nuansa barbershop klasik dengan sentuhan modern. Warna brand dapat diganti per tenant (white-label) melalui CSS variable.

| Token | Light | Dark | Kegunaan |
|---|---|---|---|
| `--color-brand` | `#D97706` (amber-600) | `#F59E0B` (amber-500) | Tombol primer, aksen, link aktif |
| `--color-brand-soft` | `#FEF3C7` | `#451A03` | Latar badge/highlight brand |
| `--color-ink` | `#18181B` (zinc-900) | `#FAFAFA` | Teks utama |
| `--color-muted` | `#71717A` (zinc-500) | `#A1A1AA` | Teks sekunder |
| `--color-surface` | `#FFFFFF` | `#18181B` | Kartu |
| `--color-bg` | `#F4F4F5` (zinc-100) | `#09090B` | Latar halaman |
| `--color-border` | `#E4E4E7` | `#27272A` | Garis/pemisah |

**Warna status (konsisten di semua role)**

| Status | Warna | Tailwind | Ikon |
|---|---|---|---|
| Menunggu (`waiting`) | Abu | `zinc` | `clock` |
| Dipanggil (`called`) | Biru | `sky` | `megaphone` |
| Sedang dicukur (`in_service`) | Amber | `amber` | `scissors` |
| Selesai (`completed`) | Hijau | `emerald` | `check-circle` |
| Lunas (`paid`) | Hijau | `emerald` | `wallet` |
| Belum bayar (`unpaid`) | Oranye | `orange` | `receipt` |
| Batal / No-show | Merah | `rose` | `x-circle` |
| Booking (`confirmed`) | Ungu | `violet` | `calendar-check` |

### 2.2 Tipografi
- Font: **Plus Jakarta Sans** (fallback: Inter, system-ui).
- Angka: `font-variant-numeric: tabular-nums` agar angka sejajar.

| Peran | Ukuran | Weight |
|---|---|---|
| Display (nomor antrean) | 56–72px | 800 |
| H1 | 24px (mobile) / 30px (desktop) | 700 |
| H2 | 20px | 700 |
| H3 / judul kartu | 16px | 600 |
| Body | 14–15px | 400–500 |
| Caption | 12px | 500 |

### 2.3 Spasi, radius, bayangan
- Grid spasi 4px (`gap-2`, `gap-4`, `p-4`, `p-6`).
- Radius: tombol & input `rounded-xl` (12px), kartu `rounded-2xl` (16px), bottom sheet `rounded-t-3xl`.
- Bayangan: `shadow-sm` untuk kartu, `shadow-lg` untuk floating button & modal.
- Tinggi minimum target sentuh: **44px**.

### 2.4 Ikon
- **Lucide Icons**, stroke 1.75–2px, ukuran 20px (navigasi 24px).

### 2.5 Implementasi Tailwind 4 (rencana `resources/css/app.css`)
```css
@import "tailwindcss";

@theme {
  --font-sans: "Plus Jakarta Sans", Inter, ui-sans-serif, system-ui, sans-serif;
  --color-brand: var(--tenant-brand, #D97706);
  --color-brand-soft: #FEF3C7;
  --radius-card: 1rem;
}

@custom-variant dark (&:where(.dark, .dark *));
```
Warna tenant diinjeksi di layout: `<html style="--tenant-brand: {{ $tenant->brand_color }}">`.

---

## 3. Komponen UI (Blade Components)

Lokasi: `resources/views/components/ui/`

| Komponen | Varian / props | Catatan |
|---|---|---|
| `<x-ui.button>` | `primary`, `secondary`, `ghost`, `danger`; `size`; `loading` | Spinner otomatis saat `wire:loading` |
| `<x-ui.card>` | `padding`, `header`, `footer` | |
| `<x-ui.stat>` | `label`, `value`, `trend`, `icon` | Kartu KPI dashboard |
| `<x-ui.badge>` | `status` (auto warna dari enum) | |
| `<x-ui.input>`, `select`, `textarea` | `label`, `hint`, `error` | Label di atas, error merah di bawah |
| `<x-ui.money-input>` | | Format `Rp` otomatis |
| `<x-ui.modal>` | | Desktop: modal tengah |
| `<x-ui.bottom-sheet>` | | Mobile: muncul dari bawah |
| `<x-ui.toast>` | `success`, `error`, `info` | Pojok atas (mobile), kanan bawah (desktop) |
| `<x-ui.empty-state>` | `icon`, `title`, `action` | |
| `<x-ui.skeleton>` | | Loading placeholder |
| `<x-ui.avatar>` | `src`, `name` | Inisial jika tanpa foto |
| `<x-ui.tabs>` / `segmented` | | Filter Hari ini / Minggu / Bulan |
| `<x-ui.stepper>` | | Wizard booking |
| `<x-ui.time-chip>` | `available`, `selected`, `disabled` | Pilih jam booking |
| `<x-ui.queue-card>` | | Kartu tiket antrean |
| `<x-ui.bottom-nav>` | | Navigasi mobile |
| `<x-ui.sidebar>` | | Navigasi desktop |
| `<x-ui.fab>` | | Floating action button |

---

## 4. Layout & Navigasi

| Layout | Dipakai oleh | Mobile | Desktop (≥1024px) |
|---|---|---|---|
| `layouts.customer` | Customer | Header ringkas + bottom nav | Kontainer tengah max-w-md/lg (tetap seperti aplikasi) |
| `layouts.app` | Owner, Admin, Barber | Topbar + bottom nav (4 menu + "Lainnya") | Sidebar kiri (bisa diciutkan) + topbar |
| `layouts.platform` | Superadmin | – (desktop utama) | Sidebar + topbar |
| `layouts.display` | TV antrean | – | Fullscreen, font sangat besar |
| `layouts.guest` | Login, register | Kartu tengah | Split screen (ilustrasi + form) |

### Topbar (app)
`[☰] [Logo Tenant]   [▼ Cabang: Semua]   [🔔] [Avatar]`

### Navigasi per role

| Role | Bottom nav (mobile) | Sidebar (desktop) |
|---|---|---|
| Customer | Beranda · Antrean · Riwayat · Profil | – |
| Barber | Antrean · Penghasilan · Jadwal · Profil | sama (sidebar) |
| Admin/Kasir | Antrean · Kasir · Booking · Lainnya | Dashboard, Antrean, Booking, Kasir, Transaksi, Customer, Absensi, Shift |
| Owner | Beranda · Antrean · Laporan · Lainnya | Dashboard, Antrean & Booking, Transaksi, Layanan & Harga, Cabang, Karyawan, Gaji & Komisi, Absensi & Jadwal, Customer, Laporan, Audit Log, Pengaturan |
| Superadmin | – | Dashboard, Tenant, Paket, Langganan, Tagihan, Komplain, Konfigurasi |

---

## 5. Wireframe Halaman

### 5.1 Customer — Beranda
```
┌─────────────────────────────┐
│ ✂ Barber King        🔔  👤 │
│ Halo, Rafael 👋              │
│ Mau cukur hari ini?          │
├─────────────────────────────┤
│ ╭─ ANTREAN AKTIF ─────────╮ │
│ │  A-012        ● Menunggu │ │
│ │  2 antrean lagi          │ │
│ │  ▓▓▓▓▓▓▓░░░  ~20 menit   │ │
│ │  Cabang Sudirman · Andi  │ │
│ ╰──────────────────────────╯ │
│ ┌──────────┐ ┌──────────┐   │
│ │ 📅       │ │ 🎟        │   │
│ │ Booking  │ │ Ambil    │   │
│ │          │ │ Antrean  │   │
│ └──────────┘ └──────────┘   │
│ ┌──────────┐ ┌──────────┐   │
│ │ 🕘Riwayat│ │ 📍 Outlet │   │
│ └──────────┘ └──────────┘   │
│ Layanan populer        Semua│
│ [Haircut 50rb][Shave 30rb]→ │
│ Barber kami                 │
│ (A)Andi ⭐4.9 (B)Bima ⭐4.8 → │
├─────────────────────────────┤
│  🏠     🎟     🕘     👤      │
└─────────────────────────────┘
```
- Kartu antrean aktif hanya muncul jika ada tiket; progress bar & estimasi update real-time.
- Jika belum ada antrean → kartu promosi/CTA "Ambil antrean sekarang".

### 5.2 Customer — Wizard Booking (4 langkah)
```
┌─────────────────────────────┐
│ ←  Booking      Langkah 3/4 │
│ ●━━━━●━━━━●━━━━○             │
├─────────────────────────────┤
│ Pilih tanggal               │
│ [Sen 27][Sel 28][Rab 29] →  │
│                             │
│ Pilih jam                   │
│ Pagi                        │
│ [10:00][10:30][11:00]       │
│ [11:30][░12:00░][12:30]     │
│ Siang                       │
│ [13:00][13:30][14:00] ...   │
├─────────────────────────────┤
│ Haircut · Andi · 45 mnt     │
│ Rp50.000     [ Lanjut  → ]  │
└─────────────────────────────┘
```
Langkah: **1. Layanan** (multi-pilih, total harga & durasi) → **2. Barber** (atau "Siapa saja – tercepat") → **3. Tanggal & Jam** → **4. Konfirmasi** (ringkasan + catatan).
Slot penuh = abu-abu dicoret, slot terpilih = brand color.

### 5.3 Customer — Status Antrean (layar penuh)
```
┌─────────────────────────────┐
│ ←  Antrean Saya             │
│                             │
│        Nomor Anda           │
│         A-012               │  ← 72px, bold
│      ● Menunggu             │
│                             │
│  Sedang dilayani:  A-010    │
│  Di depan Anda:    2 orang  │
│  Estimasi:        ~20 menit │
│                             │
│  ○ Check-in   ✓ 09.40       │
│  ● Menunggu                 │
│  ○ Dipanggil                │
│  ○ Dicukur                  │
│  ○ Selesai                  │
│                             │
│ [ Batalkan antrean ]        │
└─────────────────────────────┘
```
Saat status `called`: layar berubah biru penuh + getar + notifikasi "Giliran Anda! Silakan menuju kursi Andi".

### 5.4 Barber — Antrean (layar utama)
```
┌─────────────────────────────┐
│ Halo, Andi! ✂     ● Aktif ▾ │
│ Senin, 27 Okt               │
├─────────────────────────────┤
│ ┌───────┐┌───────┐┌───────┐ │
│ │Dilayani││Komisi ││Antrean│ │
│ │  12   ││ 180rb ││   3   │ │
│ └───────┘└───────┘└───────┘ │
│                             │
│ ╭─ CUSTOMER BERIKUTNYA ───╮ │
│ │ A-013          📅Booking │ │
│ │ Rafael                   │ │
│ │ Haircut + Wash · 45 mnt  │ │
│ │ Catatan: tipis samping   │ │
│ │                          │ │
│ │ [  📣  PANGGIL         ] │ │  ← tombol besar penuh
│ ╰──────────────────────────╯ │
│ Antrean selanjutnya         │
│ A-014  Walk-in  Haircut  ›  │
│ A-016  Booking 11:30 Shave ›│
├─────────────────────────────┤
│  🎟     💰     📅     👤      │
└─────────────────────────────┘
```
Tombol utama berubah sesuai status:
`📣 Panggil` (biru) → `✂ Mulai Cukur` (amber, + timer berjalan) → `✓ Selesai` (hijau).
Toggle status barber: **Aktif / Istirahat / Off** (memengaruhi pembagian antrean).

### 5.5 Barber — Penghasilan
```
┌─────────────────────────────┐
│ Penghasilan                 │
│ [Hari ini][Minggu][●Bulan]  │
├─────────────────────────────┤
│ Total periode berjalan      │
│ Rp3.800.000                 │
│ Dibayar Rp0 · Belum Rp3,8jt │
├─────────────────────────────┤
│ Layanan selesai      300    │
│ Gaji pokok     Rp2.000.000  │
│ Komisi         Rp1.500.000  │
│ Bonus target     Rp300.000  │
│ Potongan              Rp0   │
│ Tip               Rp120.000 │
├─────────────────────────────┤
│ 📈 Grafik layanan per hari   │
├─────────────────────────────┤
│ Riwayat slip gaji          ›│
│ Sep 2026  Rp3.650.000 ✓Paid │
└─────────────────────────────┘
```

### 5.6 Admin/Kasir — Papan Antrean (tablet/desktop)
```
┌────────┬──────────────────────────────────────────────────────┐
│ SIDEBAR│ Antrean · Cabang Sudirman   🔍   [+ Walk-in] [TV ↗]  │
│        ├─────────────┬─────────────┬─────────────┬────────────┤
│        │ MENUNGGU (4)│ DIPANGGIL(1)│ DICUKUR (3) │ BAYAR (2)  │
│        │┌───────────┐│┌───────────┐│┌───────────┐│┌──────────┐│
│        ││A-014 Walk ││││A-013      ││││A-010 Andi ││││A-008     ││
│        ││Haircut    ││││Rafael→Andi││││⏱ 18:32    ││││Rp75.000  ││
│        ││Barber: -  ││││           ││││           ││││[Bayar]   ││
│        ││[Assign ▾] ││└───────────┘│└───────────┘│└──────────┘│
│        │└───────────┘│             │             │            │
│        │ Booking hari ini (belum check-in)                     │
│        │ 11:30 Budi · Bima · Haircut     [Check-in] [No-show] │
└────────┴──────────────────────────────────────────────────────┘
```
- Mobile: kolom menjadi **tab** (Menunggu | Dipanggil | Dicukur | Bayar).
- Update real-time; kartu yang berubah diberi animasi highlight singkat.

### 5.7 Admin/Kasir — Checkout
```
┌───────────────────────────────┬─────────────────────────┐
│ Tiket A-010 · Andi            │ Ringkasan               │
│ Cari layanan/produk 🔍         │ Haircut      Rp50.000   │
│ [Haircut][Shave][Wash][Pomade]│ Hair Wash    Rp25.000   │
│ [Coloring][Kids][Creambath]   │ Diskon       -Rp5.000   │
│                               │ Tip           Rp10.000  │
│                               │ ─────────────────────── │
│                               │ TOTAL        Rp80.000   │
│                               │ [Tunai][QRIS][Transfer] │
│                               │ Uang diterima: 100.000  │
│                               │ Kembalian:     20.000   │
│                               │ [   ✓ BAYAR & CETAK   ] │
└───────────────────────────────┴─────────────────────────┘
```
Mobile: ringkasan menjadi sticky bottom bar, tap untuk membuka bottom sheet pembayaran.

### 5.8 Owner — Dashboard
```
┌────────┬────────────────────────────────────────────────────┐
│ SIDEBAR│ Dashboard      [▼ Semua cabang] [Hari ini ▾]  🔔 👤 │
│        ├────────────┬────────────┬────────────┬─────────────┤
│        │ Omzet      │ Customer   │ Booking    │ Barber aktif│
│        │Rp2.450.000 │ 38 orang   │ 7 aktif    │ 5 orang     │
│        │ ▲12% kemarin│ ▲5%       │            │             │
│        ├────────────┴────────────┴──────┬─────┴─────────────┤
│        │ 📈 Omzet 7 hari terakhir        │ 🏆 Performa barber│
│        │                                │ 1. Andi   12 ▓▓▓▓ │
│        │                                │ 2. Bima    9 ▓▓▓  │
│        │                                │ 3. Rizky   7 ▓▓   │
│        ├────────────────────────────────┼───────────────────┤
│        │ Layanan terlaris (donut)       │ Antrean live      │
│        │                                │ 4 menunggu · 3 ✂  │
│        ├────────────────────────────────┴───────────────────┤
│        │ Transaksi terbaru                          Semua › │
└────────┴────────────────────────────────────────────────────┘
```
Mobile: KPI menjadi grid 2×2, grafik & daftar ditumpuk vertikal.

### 5.9 Owner — Gaji & Komisi
- Tab: **Periode Payroll** | **Aturan Komisi** | **Skema Gaji** | **Bonus & Potongan**.
- Periode payroll: daftar periode dengan status badge `Draft → Menunggu approval → Disetujui → Dibayar`.
- Detail periode: tabel barber (layanan, pokok, komisi, bonus, potongan, total) + tombol **Approve**, **Tandai dibayar**, **Unduh slip**.
- Aturan komisi: kartu per aturan ("Haircut → Rp5.000 / layanan", "Semua jasa → 30%"), dengan override per barber.

### 5.10 TV Display Antrean
```
┌──────────────────────────────────────────────────────────┐
│  ✂ BARBER KING — Cabang Sudirman              10:42      │
├──────────────────────────────┬───────────────────────────┤
│   SEDANG DIPANGGIL           │  ANTREAN BERIKUTNYA       │
│                              │  A-014                    │
│        A-013                 │  A-015                    │
│     → Kursi Andi             │  A-016  (Booking 11:30)   │
│                              │                           │
├──────────────────────────────┴───────────────────────────┤
│ Andi: A-010 ✂  │  Bima: A-011 ✂  │  Rizky: A-012 ✂        │
│  Scan QR untuk ambil antrean ▣                            │
└──────────────────────────────────────────────────────────┘
```
Bunyi "ding" + animasi saat nomor baru dipanggil.

### 5.11 Superadmin — Tenant
Tabel: Nama barbershop · Owner · Paket · Outlet · Status langganan (badge) · Berakhir · Aksi (Lihat, Suspend, Perpanjang).
Header KPI: Tenant aktif, Trial, MRR, Transaksi hari ini (semua tenant).

### 5.12 Login
- Staff: email/username + password, "Ingat saya".
- Customer: nomor HP + password / tombol "Masuk dengan Google"; nantinya OTP WhatsApp (input 6 kotak kode).
- Akses dari QR cabang: tampilkan logo tenant & nama cabang di atas form.

---

## 6. Pola Interaksi

| Situasi | Pola |
|---|---|
| Aksi berhasil | Toast hijau 3 detik |
| Aksi berbahaya (batal, refund, hapus) | Modal konfirmasi + input alasan |
| Form panjang di HP | Dipecah menjadi langkah (stepper) |
| Pilihan di HP | Bottom sheet, bukan dropdown kecil |
| Memuat data | Skeleton (bukan spinner layar penuh) |
| Data kosong | Empty state + ilustrasi/ikon + tombol aksi |
| Error jaringan | Banner "Koneksi terputus, mencoba lagi…" |
| Real-time update | Highlight sesaat pada item yang berubah |
| Tombol submit | Disabled + spinner saat proses (cegah klik ganda) |
| Pull-to-refresh (PWA) | Di halaman antrean customer & barber |

## 7. Format Data
- Uang: `Rp2.450.000` (ringkas di kartu kecil: `Rp2,4jt`, `180rb`).
- Tanggal: `Senin, 27 Okt 2026`; jam 24-jam `14.30`.
- Nomor antrean: `{prefix}-{3 digit}` → `A-012` (A = walk-in, B = booking; opsional).
- Durasi: `45 mnt`, `1 j 15 mnt`.

## 8. Aksesibilitas & Responsif
- Kontras teks minimal 4.5:1 (WCAG AA).
- Semua ikon tombol memiliki label (`aria-label`) atau teks.
- Fokus keyboard terlihat (`focus-visible:ring-2 ring-brand`).
- Breakpoint: `sm 640` · `md 768` · `lg 1024` · `xl 1280`.
- Uji minimal di 360×640, 390×844, 768×1024, 1366×768.
- `prefers-reduced-motion` dihormati.

## 9. White-label (per tenant)
| Item | Starter | Pro | Business |
|---|:-:|:-:|:-:|
| Logo & nama | ✅ | ✅ | ✅ |
| Warna brand | – | ✅ | ✅ |
| Ikon PWA & splash | – | ✅ | ✅ |
| Subdomain / custom domain | – | – | ✅ |
| Hilangkan "Powered by" | – | – | ✅ |
