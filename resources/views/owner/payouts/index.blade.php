<x-layouts.app title="Pencairan Dana Booking" subtitle="Pantau pemasukan booking online QRIS dan riwayat pencairan dana dari Superadmin" role="owner">
    <!-- 1. KARTU RINGKASAN METRIK PENCAIRAN DANA (RESPONSIVE: 1 KOLOM MOBILE, 2 KOLOM TABLET/LAPTOP, 4 KOLOM DESKTOP LUAS) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3.5 sm:gap-4 mb-6">
        <!-- Total Masuk dari Booking QRIS -->
        <div class="rounded-2xl border border-zinc-200/90 bg-white p-4 sm:p-5 shadow-2xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between text-zinc-400 mb-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">Total Masuk (Booking)</span>
                    <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                        <x-ui.icon name="qr-code" class="w-4 h-4 text-amber-600" />
                    </div>
                </div>
                <div class="text-xl sm:text-2xl font-black text-zinc-950 num">
                    Rp{{ number_format($totalBookingRevenue, 0, ',', '.') }}
                </div>
            </div>
            <p class="text-[11px] text-zinc-500 mt-2">Seluruh akumulasi booking lunas QRIS</p>
        </div>

        <!-- Saldo Siap Dicairkan (Mengendap di Superadmin) -->
        <div class="rounded-2xl border border-amber-300 bg-amber-50/50 p-4 sm:p-5 shadow-2xs relative overflow-hidden flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between text-amber-700 mb-2">
                    <span class="text-[11px] font-black uppercase tracking-wider text-amber-900">Saldo Siap Dicairkan</span>
                    <div class="w-8 h-8 rounded-xl bg-amber-500 text-zinc-950 flex items-center justify-center font-bold shrink-0">
                        <x-ui.icon name="wallet" class="w-4 h-4" />
                    </div>
                </div>
                <div class="text-xl sm:text-2xl font-black text-amber-950 num">
                    Rp{{ number_format($pendingBalance, 0, ',', '.') }}
                </div>
            </div>
            <p class="text-[11px] text-amber-800 font-medium mt-2">{{ count($unpaidBookings) }} booking menunggu ditransfer Superadmin</p>
        </div>

        <!-- Total Telah Dicairkan oleh Superadmin -->
        <div class="rounded-2xl border border-zinc-200/90 bg-white p-4 sm:p-5 shadow-2xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between text-zinc-400 mb-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">Telah Dicairkan</span>
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                        <x-ui.icon name="check" class="w-4 h-4 text-emerald-600" />
                    </div>
                </div>
                <div class="text-xl sm:text-2xl font-black text-emerald-700 num">
                    Rp{{ number_format($disbursedAmount, 0, ',', '.') }}
                </div>
            </div>
            <p class="text-[11px] text-zinc-500 mt-2">Dana yang sudah masuk ke rekening/e-wallet Anda</p>
        </div>

        <!-- Status Rekening / E-Wallet Penerima -->
        <div class="rounded-2xl border border-zinc-800 bg-zinc-950 text-white p-4 sm:p-5 shadow-2xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between text-zinc-400 mb-2 gap-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-amber-400 truncate">Tujuan Pencairan</span>
                    @if($tenant->payout_account_number)
                        <span class="px-2 py-0.5 rounded-md bg-emerald-500/20 text-emerald-400 text-[10px] font-black uppercase shrink-0">Siap Digunakan</span>
                    @else
                        <span class="px-2 py-0.5 rounded-md bg-rose-500/20 text-rose-400 text-[10px] font-black uppercase shrink-0">Belum Diisi</span>
                    @endif
                </div>
                @if($tenant->payout_account_number)
                    <div class="text-base font-black text-white truncate">
                        {{ $tenant->payout_bank_name }} · <span class="font-mono text-amber-400">{{ $tenant->payout_account_number }}</span>
                    </div>
                    <p class="text-[11px] text-zinc-400 mt-0.5 truncate">a.n {{ $tenant->payout_account_holder }}</p>
                @else
                    <div class="text-sm font-bold text-zinc-300">
                        Rekening Belum Diatur
                    </div>
                    <p class="text-[11px] text-rose-300 mt-1 line-clamp-2">Lengkapi rekening atau e-wallet di bawah agar dana dapat dicairkan.</p>
                @endif
            </div>
            <div class="pt-2 text-[10px] text-zinc-500 font-mono">
                Akun Escrow Barbershop
            </div>
        </div>
    </div>

    <!-- NOTIFIKASI SUKSES / ERROR -->
    @if(session('status'))
        <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-300 text-emerald-950 flex items-center gap-3 shadow-xs">
            <x-ui.icon name="check-circle" class="w-5 h-5 text-emerald-600 shrink-0" />
            <span class="text-xs font-bold">{{ session('status') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-300 text-rose-950 space-y-1 shadow-xs">
            <div class="flex items-center gap-2 font-black text-xs text-rose-800">
                <x-ui.icon name="alert-triangle" class="w-4 h-4 text-rose-600 shrink-0" />
                <span>Terjadi Kesalahan:</span>
            </div>
            <ul class="text-xs list-disc list-inside space-y-0.5 text-rose-800">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- 2. GRID PENGATURAN REKENING & INFORMASI MEKANISME ESCROW (STACK PADA TABLET/MOBILE AGAR LEGA, SIDE-BY-SIDE PADA DESKTOP XL) -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-5 sm:gap-6 mb-6 sm:mb-8 items-start">
        <!-- FORM PENGATURAN REKENING BANK / E-WALLET TUJUAN (5 Kolom di XL, Full di Tablet/Mobile) -->
        <div class="xl:col-span-5 rounded-3xl border border-zinc-200/90 bg-white p-5 sm:p-6 shadow-2xs">
            <div class="flex items-center gap-2.5 mb-4 pb-3 border-b border-zinc-100">
                <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                    <x-ui.icon name="credit-card" class="w-4 h-4 text-amber-600" />
                </div>
                <div>
                    <h2 class="font-black text-sm text-zinc-950">Rekening / E-Wallet Pencairan</h2>
                    <p class="text-[11px] text-zinc-500">Menerima transfer Bank atau saldo E-Wallet</p>
                </div>
            </div>

            <form method="POST" action="{{ route('owner.payouts.update-bank-account') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block font-bold text-xs text-zinc-700 mb-1">
                        Nama Bank / E-Wallet <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="payout_bank_name"
                        value="{{ old('payout_bank_name', $tenant->payout_bank_name) }}"
                        required
                        list="destination-list"
                        placeholder="Contoh: BCA, Mandiri, GoPay, OVO, DANA"
                        class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2.5 text-xs text-zinc-900 focus:bg-white focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:outline-none transition uppercase"
                    />
                    <datalist id="destination-list">
                        <!-- Rekening Bank -->
                        <option value="BCA">Bank Central Asia (BCA)</option>
                        <option value="Mandiri">Bank Mandiri</option>
                        <option value="BRI">Bank Rakyat Indonesia (BRI)</option>
                        <option value="BNI">Bank Negara Indonesia (BNI)</option>
                        <option value="BSI">Bank Syariah Indonesia (BSI)</option>
                        <option value="CIMB Niaga">Bank CIMB Niaga</option>
                        <option value="Bank Jago">Bank Jago</option>
                        <option value="SeaBank">SeaBank Indonesia</option>
                        <option value="Permata">Bank Permata</option>
                        <option value="Danamon">Bank Danamon</option>
                        <!-- E-Wallet -->
                        <option value="GoPay">GoPay (Gojek)</option>
                        <option value="OVO">OVO</option>
                        <option value="DANA">DANA</option>
                        <option value="ShopeePay">ShopeePay</option>
                        <option value="LinkAja">LinkAja</option>
                    </datalist>
                    <span class="text-[10px] text-zinc-400 mt-1 block">Bisa transfer ke Bank (BCA, Mandiri, dll) atau E-Wallet (GoPay, OVO, DANA, ShopeePay)</span>
                </div>

                <div>
                    <label class="block font-bold text-xs text-zinc-700 mb-1">
                        Nomor Rekening / No. HP E-Wallet <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="payout_account_number"
                        value="{{ old('payout_account_number', $tenant->payout_account_number) }}"
                        required
                        placeholder="Nomor rekening bank atau nomor HP (contoh: 08123456789)"
                        class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2.5 text-xs font-mono font-bold text-zinc-900 focus:bg-white focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:outline-none transition"
                    />
                </div>

                <div>
                    <label class="block font-bold text-xs text-zinc-700 mb-1">
                        Nama Pemilik Rekening / Akun E-Wallet <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="payout_account_holder"
                        value="{{ old('payout_account_holder', $tenant->payout_account_holder) }}"
                        required
                        placeholder="Harus sesuai dengan buku tabungan atau profil akun e-wallet"
                        class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2.5 text-xs text-zinc-900 focus:bg-white focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:outline-none transition"
                    />
                </div>

                <div class="pt-2">
                    <button
                        type="submit"
                        class="w-full py-2.5 px-4 rounded-xl bg-zinc-950 hover:bg-zinc-800 text-amber-400 font-black text-xs shadow-md transition cursor-pointer flex items-center justify-center gap-1.5"
                    >
                        <x-ui.icon name="check" class="w-4 h-4" />
                        <span>Simpan Rekening / E-Wallet</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- INFO CARA KERJA SISTEM PEMBAYARAN PLATFORM ESCROW (7 Kolom di XL, Full di Tablet/Mobile) -->
        <div class="xl:col-span-7 rounded-3xl border border-zinc-200/90 bg-white p-5 sm:p-6 shadow-2xs flex flex-col justify-between space-y-5">
            <div>
                <div class="flex items-center gap-2.5 mb-4 pb-3 border-b border-zinc-100">
                    <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                        <x-ui.icon name="info" class="w-4 h-4 text-blue-600" />
                    </div>
                    <div>
                        <h2 class="font-black text-sm text-zinc-950">Cara Kerja Pembayaran Booking &amp; Pencairan (Escrow)</h2>
                        <p class="text-[11px] text-zinc-500">Mekanisme otomatis Xendit QRIS &amp; transfer dana ke rekening/e-wallet Anda</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-3 gap-3 sm:gap-3.5 text-xs text-zinc-600">
                    <div class="p-3.5 rounded-2xl bg-zinc-50 border border-zinc-200/60 flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-amber-500 text-zinc-950 text-[11px] grid place-items-center font-black shrink-0 mt-0.5">1</span>
                        <div class="min-w-0">
                            <h3 class="font-black text-zinc-900 text-xs mb-1">Customer Bayar QRIS</h3>
                            <p class="text-[11px] leading-relaxed text-zinc-600">
                                Customer membayar booking online via scan QRIS resmi Xendit platform. Tiket antrean langsung terbit instan.
                            </p>
                        </div>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-zinc-50 border border-zinc-200/60 flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-amber-500 text-zinc-950 text-[11px] grid place-items-center font-black shrink-0 mt-0.5">2</span>
                        <div class="min-w-0">
                            <h3 class="font-black text-zinc-900 text-xs mb-1">Dana Ditampung Platform</h3>
                            <p class="text-[11px] leading-relaxed text-zinc-600">
                                Dana pembayaran aman tersimpan di sistem escrow Xendit dan otomatis tercatat sebagai <strong class="text-zinc-900">Saldo Siap Dicairkan</strong>.
                            </p>
                        </div>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-zinc-50 border border-zinc-200/60 flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-emerald-500 text-white text-[11px] grid place-items-center font-black shrink-0 mt-0.5">3</span>
                        <div class="min-w-0">
                            <h3 class="font-black text-zinc-900 text-xs mb-1">Superadmin Mentransfer</h3>
                            <p class="text-[11px] leading-relaxed text-zinc-600">
                                Saldo ditransfer berkala ke rekening bank atau akun e-wallet Anda lengkap dengan struk bukti transfer resmi.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-3 rounded-2xl bg-amber-50/70 border border-amber-200/80 flex items-center gap-3">
                <x-ui.icon name="shield-check" class="w-5 h-5 text-amber-600 shrink-0" />
                <p class="text-[11px] text-amber-900 font-medium leading-relaxed">
                    Pencairan fleksibel: Anda bebas menentukan tujuan pencairan ke Rekening Bank resmi maupun akun E-Wallet (GoPay, OVO, DANA, ShopeePay) terdaftar.
                </p>
            </div>
        </div>
    </div>

    <!-- 3. TABEL DAFTAR BOOKING BELUM DICAIRKAN (HAK DANA SIAP DITRANSFER) -->
    <div class="rounded-3xl border border-zinc-200/90 bg-white shadow-2xs mb-6 sm:mb-8 overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-zinc-100 flex flex-col lg:flex-row lg:items-center justify-between gap-3 sm:gap-4">
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="font-black text-base text-zinc-950">Rincian Booking Belum Dicairkan</h2>
                    <span class="px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-900 font-black text-[10px] shrink-0">
                        {{ count($unpaidBookings) }} Transaksi
                    </span>
                </div>
                <p class="text-xs text-zinc-500 mt-0.5 max-w-xl">Daftar booking lunas yang dananya mengendap di Superadmin dan siap untuk dicairkan</p>
            </div>
            <div class="flex items-center justify-between lg:flex-col lg:items-end gap-1.5 px-3.5 py-2.5 lg:p-0 rounded-2xl lg:rounded-none bg-amber-50/70 lg:bg-transparent border border-amber-200/70 lg:border-0 shrink-0">
                <span class="text-[11px] text-amber-900 lg:text-zinc-400 font-bold lg:font-medium">Total Akumulasi Siap Cair</span>
                <span class="text-base sm:text-lg font-black text-amber-600 num">Rp{{ number_format($pendingBalance, 0, ',', '.') }}</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-zinc-100 bg-zinc-50/70 text-zinc-500 font-bold uppercase text-[10px] tracking-wider whitespace-nowrap">
                        <th class="py-3 px-4">Kode Booking</th>
                        <th class="py-3 px-4">Customer</th>
                        <th class="py-3 px-4">Jadwal Booking</th>
                        <th class="py-3 px-4">Barber &amp; Layanan</th>
                        <th class="py-3 px-4 text-right">Nominal</th>
                        <th class="py-3 px-4 text-center">Status Pembayaran</th>
                        <th class="py-3 px-4 text-center">Status Pencairan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 font-medium text-zinc-800">
                    @forelse($unpaidBookings as $b)
                        <tr class="hover:bg-zinc-50/60 transition">
                            <td class="py-3.5 px-4 font-mono font-black text-zinc-900 num whitespace-nowrap">
                                #{{ $b->code }}
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <p class="font-bold text-zinc-900">{{ $b->customer_name }}</p>
                                <span class="text-[10px] text-zinc-400 font-mono">{{ $b->customer_phone }}</span>
                            </td>
                            <td class="py-3.5 px-4 text-zinc-700 whitespace-nowrap">
                                <p class="font-bold">{{ $b->scheduled_at ? $b->scheduled_at->translatedFormat('d M Y') : '-' }}</p>
                                <span class="text-[10px] text-zinc-400 font-mono">{{ $b->scheduled_at ? $b->scheduled_at->format('H:i') : '' }} WIB</span>
                            </td>
                            <td class="py-3.5 px-4 min-w-[150px]">
                                <p class="font-bold text-zinc-900">{{ $b->barber?->name ?? 'Barber' }}</p>
                                <span class="text-[10px] text-zinc-500 line-clamp-1">
                                    {{ $b->items->pluck('service.name')->filter()->join(', ') ?: 'Layanan Cukur' }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right font-black text-zinc-950 text-sm num whitespace-nowrap">
                                Rp{{ number_format($b->estimated_total, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 border border-emerald-300">
                                    <x-ui.icon name="check-circle" class="w-3 h-3 text-emerald-600" />
                                    <span>Lunas QRIS</span>
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-100 text-amber-900 border border-amber-300">
                                    <x-ui.icon name="clock" class="w-3 h-3 text-amber-600" />
                                    <span>Mengendap di Superadmin</span>
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-zinc-400 space-y-2">
                                <div class="w-10 h-10 rounded-xl bg-zinc-100 text-zinc-400 mx-auto grid place-items-center">
                                    <x-ui.icon name="wallet" class="w-5 h-5 text-zinc-400" />
                                </div>
                                <p class="text-xs font-bold text-zinc-700">Tidak Ada Saldo Mengendap</p>
                                <p class="text-[11px] text-zinc-400 max-w-sm mx-auto">Semua booking lunas telah berhasil dicairkan oleh Superadmin.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- 4. TABEL RIWAYAT TRANSFER PENCAIRAN DANA DARI SUPERADMIN -->
    <div class="rounded-3xl border border-zinc-200/90 bg-white shadow-2xs overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-zinc-100 flex flex-col lg:flex-row lg:items-center justify-between gap-3 sm:gap-4">
            <div class="min-w-0">
                <h2 class="font-black text-base text-zinc-950">Riwayat Transfer Pencairan dari Superadmin</h2>
                <p class="text-xs text-zinc-500 mt-0.5 max-w-xl">Daftar transfer dana booking yang telah berhasil diselesaikan oleh Superadmin ke rekening/e-wallet Anda</p>
            </div>
            <div class="flex items-center justify-between lg:flex-col lg:items-end gap-1.5 px-3.5 py-2.5 lg:p-0 rounded-2xl lg:rounded-none bg-emerald-50/70 lg:bg-transparent border border-emerald-200/70 lg:border-0 shrink-0">
                <span class="text-[11px] text-emerald-900 lg:text-zinc-400 font-bold lg:font-medium">Total Dana Diterima</span>
                <span class="text-base sm:text-lg font-black text-emerald-600 num">Rp{{ number_format($disbursedAmount, 0, ',', '.') }}</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[850px] text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-zinc-100 bg-zinc-50/70 text-zinc-500 font-bold uppercase text-[10px] tracking-wider whitespace-nowrap">
                        <th class="py-3 px-4">No. Referensi</th>
                        <th class="py-3 px-4">Tanggal Dicairkan</th>
                        <th class="py-3 px-4 text-right">Nominal Ditransfer</th>
                        <th class="py-3 px-4">Rekening / E-Wallet Penerima</th>
                        <th class="py-3 px-4 text-center">Jumlah Booking</th>
                        <th class="py-3 px-4">Catatan Superadmin</th>
                        <th class="py-3 px-4 text-center">Bukti Transfer</th>
                        <th class="py-3 px-4 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 font-medium text-zinc-800">
                    @forelse($payouts as $p)
                        <tr class="hover:bg-zinc-50/60 transition">
                            <td class="py-3.5 px-4 font-mono font-black text-zinc-950 num whitespace-nowrap">
                                {{ $p->reference_no }}
                            </td>
                            <td class="py-3.5 px-4 text-zinc-600 whitespace-nowrap">
                                {{ $p->processed_at ? $p->processed_at->translatedFormat('d M Y, H:i') : $p->created_at->translatedFormat('d M Y, H:i') }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-black text-emerald-700 text-sm num whitespace-nowrap">
                                Rp{{ number_format($p->amount, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="font-bold text-zinc-800 uppercase">{{ $p->bank_name }}</span> · <span class="font-mono">{{ $p->account_number }}</span>
                                <p class="text-[10px] text-zinc-400">a.n {{ $p->account_holder }}</p>
                            </td>
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded-md bg-zinc-100 text-zinc-700 font-bold text-[11px] num">
                                    {{ $p->items->count() }} Booking
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-zinc-600 max-w-[200px] truncate" title="{{ $p->notes }}">
                                {{ $p->notes ?: '-' }}
                            </td>
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                @if($p->transfer_proof_path)
                                    <a
                                        href="{{ \Illuminate\Support\Facades\Storage::url($p->transfer_proof_path) }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 font-bold text-[11px] inline-flex items-center gap-1 transition shadow-2xs"
                                    >
                                        <x-ui.icon name="external-link" class="w-3 h-3 text-zinc-600" />
                                        <span>Lihat Bukti</span>
                                    </a>
                                @else
                                    <span class="text-zinc-400">-</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 border border-emerald-300">
                                    {{ $p->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-10 text-center text-zinc-400 space-y-2">
                                <div class="w-10 h-10 rounded-xl bg-zinc-100 text-zinc-400 mx-auto grid place-items-center">
                                    <x-ui.icon name="check-circle" class="w-5 h-5 text-zinc-400" />
                                </div>
                                <p class="text-xs font-bold text-zinc-700">Belum Ada Riwayat Pencairan</p>
                                <p class="text-[11px] text-zinc-400 max-w-sm mx-auto">Riwayat transfer dana dari Superadmin akan muncul di sini setelah diproses.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($payouts->hasPages())
            <div class="p-4 border-t border-zinc-100">
                {{ $payouts->links() }}
            </div>
        @endif
    </div>
</x-layouts.app>
