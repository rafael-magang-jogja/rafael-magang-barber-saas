<x-layouts.app title="Pencairan Dana Escrow Booking" subtitle="Kelola dan cairkan saldo booking online QRIS ke masing-masing owner barbershop" role="superadmin">
    <!-- 1. KARTU METRIK GLOBAL ESCROW (RESPONSIVE: 1 KOLOM MOBILE, 2 KOLOM TABLET/LAPTOP, 4 KOLOM DESKTOP LUAS) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3.5 sm:gap-4 mb-6">
        <!-- Total Masuk dari Booking QRIS -->
        <div class="rounded-2xl border border-zinc-200/90 bg-white p-4 sm:p-5 shadow-2xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between text-zinc-400 mb-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">Total Masuk (QRIS)</span>
                    <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                        <x-ui.icon name="qr-code" class="w-4 h-4 text-amber-600" />
                    </div>
                </div>
                <div class="text-xl sm:text-2xl font-black text-zinc-950 num">
                    Rp{{ number_format($totalPlatformRevenue, 0, ',', '.') }}
                </div>
            </div>
            <p class="text-[11px] text-zinc-500 mt-2">{{ number_format($totalPlatformBookingsCount, 0, ',', '.') }} booking terkonfirmasi</p>
        </div>

        <!-- Total Saldo Siap Dicairkan (Mengendap di Rekening Superadmin) -->
        <div class="rounded-2xl border border-amber-300 bg-amber-50/50 p-4 sm:p-5 shadow-2xs relative overflow-hidden flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between text-amber-700 mb-2">
                    <span class="text-[11px] font-black uppercase tracking-wider text-amber-900">Saldo Siap Dicairkan</span>
                    <div class="w-8 h-8 rounded-xl bg-amber-500 text-zinc-950 flex items-center justify-center font-bold shrink-0">
                        <x-ui.icon name="wallet" class="w-4 h-4" />
                    </div>
                </div>
                <div class="text-xl sm:text-2xl font-black text-amber-950 num">
                    Rp{{ number_format($totalPendingBalance, 0, ',', '.') }}
                </div>
            </div>
            <p class="text-[11px] text-amber-800 font-medium mt-2">Hak tenant yang belum ditransfer</p>
        </div>

        <!-- Total Telah Ditransfer ke Owner -->
        <div class="rounded-2xl border border-zinc-200/90 bg-white p-4 sm:p-5 shadow-2xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between text-zinc-400 mb-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">Telah Dicairkan</span>
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                        <x-ui.icon name="check" class="w-4 h-4 text-emerald-600" />
                    </div>
                </div>
                <div class="text-xl sm:text-2xl font-black text-emerald-700 num">
                    Rp{{ number_format($totalDisbursedAmount, 0, ',', '.') }}
                </div>
            </div>
            <p class="text-[11px] text-zinc-500 mt-2">Total transfer sukses ke owner</p>
        </div>

        <!-- Rekening Penampung Platform -->
        <div class="rounded-2xl border border-zinc-800 bg-zinc-950 text-white p-4 sm:p-5 shadow-2xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between text-zinc-400 mb-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-amber-400">Gateway Platform</span>
                    <div class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center shrink-0">
                        <x-ui.icon name="shield-check" class="w-4 h-4 text-amber-400" />
                    </div>
                </div>
                <div class="text-base font-black text-white truncate">
                    Rekening Penampung
                </div>
            </div>
            <p class="text-[11px] text-zinc-400 mt-2">Seluruh QRIS booking masuk ke platform &amp; dicairkan manual</p>
        </div>
    </div>

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

    <!-- 2. TABEL DAFTAR SALDO BOOKING PER BARBERSHOP (RESPONSIVE MIN-WIDTH DENGAN SCROLL HALUS) -->
    <div class="rounded-3xl border border-zinc-200/90 bg-white shadow-2xs mb-6 sm:mb-8 overflow-hidden">
                <div class="p-4 sm:p-5 border-b border-zinc-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="font-black text-base text-zinc-950">Saldo Siap Dicairkan per Barbershop</h2>
                        <p class="text-xs text-zinc-500 mt-0.5">Daftar akumulasi booking lunas QRIS yang hak dananya siap ditransfer ke owner barbershop</p>
                    </div>
                </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[780px] text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-zinc-100 bg-zinc-50/70 text-zinc-500 font-bold uppercase text-[10px] tracking-wider whitespace-nowrap">
                        <th class="py-3 px-4">Barbershop / Tenant</th>
                        <th class="py-3 px-4">Owner</th>
                        <th class="py-3 px-4">Rekening / E-Wallet Tujuan</th>
                        <th class="py-3 px-4 text-center">Booking Belum Cair</th>
                        <th class="py-3 px-4 text-right">Saldo Siap Cair</th>
                        <th class="py-3 px-4 text-right">Total Telah Dicairkan</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 font-medium text-zinc-800">
                    @forelse($tenants as $item)
                        @php
                            $t = $item['tenant'];
                            $canPayout = $item['available_balance'] > 0;
                        @endphp
                        <tr class="hover:bg-zinc-50/60 transition">
                            <td class="py-3.5 px-4 font-bold text-zinc-900 min-w-[180px]">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-xl bg-zinc-950 text-amber-400 font-black grid place-items-center shrink-0">
                                        {{ strtoupper(substr($t->name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate font-extrabold text-zinc-900">{{ $t->name }}</p>
                                        <span class="text-[10px] text-zinc-400 font-normal">ID #{{ $t->id }} · {{ $t->slug }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <p class="font-bold text-zinc-900">{{ $t->owner?->name ?? '-' }}</p>
                                <span class="text-[10px] text-zinc-400 font-mono">{{ $t->owner?->phone ?? $t->owner?->email ?? '-' }}</span>
                            </td>
                            <td class="py-3.5 px-4 min-w-[170px]">
                                @if($item['has_bank_info'])
                                    <div class="space-y-0.5">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-zinc-100 font-bold text-zinc-800 text-[10px] uppercase">
                                            <x-ui.icon name="credit-card" class="w-3 h-3 text-zinc-500" />
                                            <span>{{ $item['bank_name'] }}</span>
                                        </span>
                                        <p class="font-mono font-bold text-zinc-900 num select-all">{{ $item['account_number'] }}</p>
                                        <p class="text-[10px] text-zinc-500 truncate">a.n {{ $item['account_holder'] }}</p>
                                    </div>
                                @else
                                    <span class="inline-flex items-center gap-1 text-[11px] text-amber-700 font-bold bg-amber-50 px-2.5 py-1 rounded-xl border border-amber-200 whitespace-nowrap">
                                        <x-ui.icon name="alert-triangle" class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                                        <span>Belum diatur owner</span>
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                @if($item['unpaid_count'] > 0)
                                    <span class="px-2.5 py-1 rounded-full bg-amber-100 text-amber-900 font-black text-xs num">
                                        {{ $item['unpaid_count'] }} Booking
                                    </span>
                                @else
                                    <span class="text-zinc-400 font-normal">0 Booking</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <span class="text-sm font-black {{ $item['available_balance'] > 0 ? 'text-amber-600' : 'text-zinc-400' }} num">
                                    Rp{{ number_format($item['available_balance'], 0, ',', '.') }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <span class="text-xs font-bold text-zinc-600 num">
                                    Rp{{ number_format($item['total_disbursed'], 0, ',', '.') }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                @if($canPayout)
                                    @if($item['has_bank_info'])
                                        <button
                                            type="button"
                                            onclick="openPayoutModal({{ json_encode([
                                                'id' => $t->id,
                                                'name' => $t->name,
                                                'amount' => $item['available_balance'],
                                                'amount_formatted' => number_format($item['available_balance'], 0, ',', '.'),
                                                'count' => $item['unpaid_count'],
                                                'has_bank_info' => true,
                                                'bank_name' => $item['bank_name'],
                                                'account_number' => $item['account_number'],
                                                'account_holder' => $item['account_holder'],
                                            ]) }})"
                                            class="px-3 py-1.5 rounded-xl bg-zinc-950 hover:bg-zinc-800 text-amber-400 font-extrabold text-xs shadow-xs transition cursor-pointer inline-flex items-center gap-1.5"
                                        >
                                            <x-ui.icon name="send" class="w-3.5 h-3.5 shrink-0" />
                                            <span>Proses Transfer</span>
                                        </button>
                                    @else
                                        <button
                                            type="button"
                                            onclick="openPayoutModal({{ json_encode([
                                                'id' => $t->id,
                                                'name' => $t->name,
                                                'amount' => $item['available_balance'],
                                                'amount_formatted' => number_format($item['available_balance'], 0, ',', '.'),
                                                'count' => $item['unpaid_count'],
                                                'has_bank_info' => false,
                                                'bank_name' => '',
                                                'account_number' => '',
                                                'account_holder' => '',
                                            ]) }})"
                                            class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-700 hover:text-amber-800 hover:underline transition cursor-pointer"
                                            title="Terkunci: Owner belum mengatur rekening tujuan pencairan"
                                        >
                                            <x-ui.icon name="lock" class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                                            <span>Terkunci</span>
                                        </button>
                                    @endif
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-zinc-100 text-zinc-500 font-bold text-xs">
                                        <x-ui.icon name="check-circle" class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                        <span>Semua Lunas</span>
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-zinc-400 space-y-2">
                                <div class="w-10 h-10 rounded-xl bg-zinc-100 text-zinc-400 mx-auto grid place-items-center">
                                    <x-ui.icon name="store" class="w-5 h-5 text-zinc-400" />
                                </div>
                                <p class="text-xs font-bold text-zinc-700">Belum Ada Barbershop</p>
                                <p class="text-[11px] text-zinc-400 max-w-sm mx-auto">Barbershop terdaftar akan muncul di tabel ini beserta ringkasan saldo booking.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- 3. RIWAYAT TRANSFER PENCAIRAN (RESPONSIVE MIN-WIDTH & OVERFLOW HALUS) -->
    <div class="rounded-3xl border border-zinc-200/90 bg-white shadow-2xs overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-zinc-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="font-black text-base text-zinc-950">Riwayat Transfer Pencairan</h2>
                <p class="text-xs text-zinc-500 mt-0.5">Daftar semua bukti transfer dan pencairan dana yang telah diselesaikan ke owner</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[850px] text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-zinc-100 bg-zinc-50/70 text-zinc-500 font-bold uppercase text-[10px] tracking-wider whitespace-nowrap">
                        <th class="py-3 px-4">No. Referensi</th>
                        <th class="py-3 px-4">Barbershop</th>
                        <th class="py-3 px-4">Tanggal Dicairkan</th>
                        <th class="py-3 px-4 text-right">Nominal Ditransfer</th>
                        <th class="py-3 px-4">Rekening / E-Wallet Tujuan</th>
                        <th class="py-3 px-4">Diproses Oleh</th>
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
                            <td class="py-3.5 px-4 font-bold text-zinc-900 whitespace-nowrap">
                                {{ $p->tenant?->name ?? 'Barbershop' }}
                            </td>
                            <td class="py-3.5 px-4 text-zinc-600 whitespace-nowrap">
                                {{ $p->processed_at ? $p->processed_at->translatedFormat('d M Y, H:i') : $p->created_at->translatedFormat('d M Y, H:i') }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-black text-emerald-700 text-sm num whitespace-nowrap">
                                Rp{{ number_format($p->amount, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4 min-w-[160px]">
                                <span class="font-bold text-zinc-800 uppercase">{{ $p->bank_name }}</span> · <span class="font-mono">{{ $p->account_number }}</span>
                                <p class="text-[10px] text-zinc-400 truncate">a.n {{ $p->account_holder }}</p>
                            </td>
                            <td class="py-3.5 px-4 text-zinc-600 whitespace-nowrap">
                                {{ $p->processedByUser?->name ?? 'Superadmin' }}
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
                                <p class="text-[11px] text-zinc-400 max-w-sm mx-auto">Riwayat transfer dana ke owner akan tercatat otomatis di sini setelah berhasil diproses.</p>
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

    <!-- MODAL FORM PROSES PENCAIRAN DANA (PAYOUT DIALOG) -->
    <dialog
        id="modal-process-payout"
        onclick="if(event.target===this)this.close()"
        class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-3xl bg-white text-zinc-900 p-0 shadow-2xl border border-zinc-200 backdrop:bg-zinc-950/60 backdrop:backdrop-blur-xs"
    >
        <form method="POST" action="{{ route('platform.payouts.store') }}" enctype="multipart/form-data" class="p-5 sm:p-6 space-y-4">
            @csrf
            <input type="hidden" name="tenant_id" id="payout-tenant-id" />

            <div class="flex items-center justify-between border-b border-zinc-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-2xl bg-amber-500 text-zinc-950 grid place-items-center shrink-0">
                        <x-ui.icon name="wallet" class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="font-extrabold text-sm text-zinc-950">Proses Transfer Pencairan</h3>
                        <p class="text-xs text-zinc-500" id="payout-modal-tenant-name">Barbershop</p>
                    </div>
                </div>
                <button
                    type="button"
                    onclick="document.getElementById('modal-process-payout').close()"
                    class="p-1 rounded-xl text-zinc-400 hover:text-zinc-700 cursor-pointer"
                >
                    <x-ui.icon name="x" class="w-5 h-5" />
                </button>
            </div>

            <!-- Ringkasan Nominal -->
            <div class="p-4 rounded-2xl bg-zinc-950 text-white flex items-center justify-between shadow-xs">
                <div>
                    <span class="text-[10px] text-zinc-400 uppercase tracking-wider block font-bold">Total Saldo Dicairkan</span>
                    <span class="text-xs text-amber-400 font-semibold" id="payout-modal-count">0 Booking Lunas Terpilih</span>
                </div>
                <div class="text-right">
                    <span class="text-2xl font-black text-amber-400 num" id="payout-modal-amount">Rp0</span>
                </div>
            </div>

            <!-- KONDISI A: ALERT JIKA REKENING BELUM DIISI OWNER (TERKUNCI) -->
            <div id="payout-alert-unconfigured" class="hidden p-4 rounded-2xl bg-amber-50 border border-amber-300 text-amber-950 space-y-2.5">
                <div class="flex items-center gap-2 font-black text-xs text-amber-900">
                    <x-ui.icon name="alert-triangle" class="w-4 h-4 text-amber-600 shrink-0" />
                    <span>Rekening / E-Wallet Belum Diatur Owner!</span>
                </div>
                <p class="text-xs text-amber-800 leading-relaxed">
                    Owner barbershop belum mengisi rekening bank atau akun e-wallet pencairan dana di dashboard mereka.
                </p>
                <div class="p-3 rounded-xl bg-white/80 border border-amber-200 text-[11px] text-amber-950 space-y-1">
                    <div class="flex items-center gap-1.5 font-bold text-amber-900">
                        <x-ui.icon name="lock" class="w-3.5 h-3.5 text-amber-700 shrink-0" />
                        <span>Kolom Terkunci untuk Superadmin</span>
                    </div>
                    <p class="text-zinc-600">
                        Demi keamanan, Superadmin tidak dapat menginput atau mengubah rekening pencairan. Data rekening harus diisi langsung oleh <strong>Owner</strong> melalui menu <em>Saldo Booking &amp; Pencairan</em>.
                    </p>
                </div>
            </div>

            <!-- KONDISI B: KARTU REKENING TUJUAN TERDAFTAR RESMI (LOCKED / READONLY) -->
            <div id="payout-card-configured" class="hidden p-4 rounded-2xl bg-zinc-50 border border-zinc-200/90 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-500">Rekening Tujuan Pencairan</span>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800 text-[10px] font-black uppercase">
                        <x-ui.icon name="lock" class="w-3 h-3 text-emerald-700 shrink-0" />
                        <span>Resmi Terdaftar oleh Owner</span>
                    </span>
                </div>

                <div class="flex items-center gap-3 pt-1">
                    <div class="w-10 h-10 rounded-xl bg-zinc-950 text-amber-400 flex items-center justify-center font-black text-xs shrink-0 uppercase tracking-wider" id="payout-modal-bank-badge">
                        BANK
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-bold text-xs text-zinc-900 flex items-center gap-1.5">
                            <span id="payout-modal-bank-name" class="uppercase font-black text-zinc-900">BCA</span>
                            <span class="text-zinc-300">·</span>
                            <span id="payout-modal-account-no" class="font-mono text-amber-600 font-black text-sm select-all">1234567890</span>
                        </p>
                        <p class="text-[11px] text-zinc-500 truncate" id="payout-modal-account-holder">a.n Pemilik Rekening</p>
                    </div>
                </div>

                <p class="text-[10px] text-zinc-400 italic pt-1.5 border-t border-zinc-200/60">
                    * Rekening ini dikunci dan tidak dapat diubah oleh Superadmin. Transfer wajib ditujukan ke rekening/e-wallet resmi di atas.
                </p>
            </div>

            <!-- AREA FORM TRANSFER: HANYA AKTIF JIKA REKENING SUDAH DIATUR OWNER -->
            <div id="payout-form-transfer-area" class="space-y-3.5 text-xs">
                <!-- KOTAK UNGGAH BUKTI TRANSFER DIPERJELAS (PROMINENT DROPZONE BOX) -->
                <div>
                    <label class="block font-bold text-xs text-zinc-800 mb-1.5 flex items-center justify-between">
                        <span>Unggah Bukti Transfer (Bank / E-Wallet) <span class="text-rose-500">*</span></span>
                        <span class="text-[10px] text-zinc-400 font-normal">Maks. 3 MB (JPG, PNG, WEBP, PDF)</span>
                    </label>

                    <label
                        for="payout-input-proof-file"
                        class="relative block w-full rounded-2xl border-2 border-dashed border-zinc-300 bg-zinc-50/80 hover:bg-amber-50/40 hover:border-amber-500 p-5 text-center cursor-pointer transition group shadow-2xs"
                    >
                        <input
                            type="file"
                            name="transfer_proof"
                            id="payout-input-proof-file"
                            required
                            accept="image/png,image/jpeg,image/webp,application/pdf"
                            onchange="handlePayoutProofPreview(this)"
                            class="sr-only"
                        />

                        <!-- State Awal: Belum Pilih File -->
                        <div id="payout-proof-placeholder" class="space-y-2">
                            <div class="w-11 h-11 mx-auto rounded-xl bg-amber-500/10 text-amber-600 group-hover:bg-amber-500 group-hover:text-zinc-950 flex items-center justify-center transition">
                                <x-ui.icon name="upload" class="w-5 h-5" />
                            </div>
                            <div>
                                <p class="text-xs font-bold text-zinc-800 group-hover:text-zinc-950">
                                    <span class="text-amber-700 underline underline-offset-2">Pilih berkas struk transfer</span> atau seret ke sini
                                </p>
                                <p class="text-[11px] text-zinc-500 mt-0.5">Screenshot m-Banking, struk ATM, atau resi transfer e-wallet</p>
                            </div>
                        </div>

                        <!-- State Selesai: File Terpilih -->
                        <div id="payout-proof-preview" class="hidden items-center justify-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                <x-ui.icon name="check-circle" class="w-5 h-5" />
                            </div>
                            <div class="text-left min-w-0">
                                <p class="text-xs font-black text-zinc-900 truncate" id="payout-proof-filename">bukti-transfer.jpg</p>
                                <p class="text-[10px] text-emerald-700 font-bold" id="payout-proof-filesize">Berkas siap diunggah · Klik untuk mengganti</p>
                            </div>
                        </div>
                    </label>
                </div>

                <div>
                    <label class="block font-bold text-zinc-700 mb-1">
                        Catatan Internal / Referensi Transfer <span class="text-zinc-400 font-normal">(Opsional)</span>
                    </label>
                    <textarea
                        name="notes"
                        rows="2"
                        placeholder="Contoh: Transfer via KlikBCA Bisnis jam 14:00..."
                        class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs text-zinc-900 focus:bg-white focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:outline-none transition"
                    ></textarea>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 pt-2 border-t border-zinc-100">
                <button
                    type="button"
                    onclick="document.getElementById('modal-process-payout').close()"
                    class="py-2.5 px-4 rounded-xl border border-zinc-200 bg-white hover:bg-zinc-50 text-zinc-700 font-bold text-xs transition cursor-pointer"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    id="payout-btn-submit"
                    class="py-2.5 px-4 rounded-xl bg-zinc-950 hover:bg-zinc-800 text-amber-400 font-black text-xs shadow-md transition cursor-pointer flex items-center justify-center gap-1.5"
                >
                    <x-ui.icon name="check" class="w-4 h-4" />
                    <span id="payout-btn-text">Konfirmasi &amp; Selesaikan</span>
                </button>
            </div>
        </form>
    </dialog>

    <script>
        function handlePayoutProofPreview(input) {
            const file = input.files?.[0];
            const placeholder = document.getElementById('payout-proof-placeholder');
            const preview = document.getElementById('payout-proof-preview');
            const filename = document.getElementById('payout-proof-filename');
            const filesize = document.getElementById('payout-proof-filesize');

            if (file) {
                placeholder.classList.add('hidden');
                preview.classList.remove('hidden');
                preview.classList.add('flex');
                filename.textContent = file.name;
                filesize.textContent = (file.size / 1024).toFixed(1) + ' KB · Berkas siap diunggah';
            } else {
                placeholder.classList.remove('hidden');
                preview.classList.add('hidden');
                preview.classList.remove('flex');
            }
        }

        function openPayoutModal(data) {
            document.getElementById('payout-tenant-id').value = data.id;
            document.getElementById('payout-modal-tenant-name').textContent = data.name;
            document.getElementById('payout-modal-amount').textContent = 'Rp' + data.amount_formatted;
            document.getElementById('payout-modal-count').textContent = data.count + ' Booking Lunas Terpilih';

            // Reset file preview
            const fileInput = document.getElementById('payout-input-proof-file');
            if (fileInput) fileInput.value = '';
            handlePayoutProofPreview({ files: [] });

            const alertUnconfigured = document.getElementById('payout-alert-unconfigured');
            const cardConfigured = document.getElementById('payout-card-configured');
            const formTransferArea = document.getElementById('payout-form-transfer-area');
            const btnSubmit = document.getElementById('payout-btn-submit');
            const btnText = document.getElementById('payout-btn-text');

            if (data.has_bank_info) {
                // Owner SUDAH mengatur rekening -> Tampilkan kartu terkunci & aktifkan tombol
                alertUnconfigured.classList.add('hidden');
                cardConfigured.classList.remove('hidden');
                formTransferArea.classList.remove('hidden');

                document.getElementById('payout-modal-bank-name').textContent = data.bank_name;
                document.getElementById('payout-modal-bank-badge').textContent = (data.bank_name || 'BANK').substring(0, 4);
                document.getElementById('payout-modal-account-no').textContent = data.account_number;
                document.getElementById('payout-modal-account-holder').textContent = 'a.n ' + data.account_holder;

                btnSubmit.disabled = false;
                btnSubmit.className = 'py-2.5 px-4 rounded-xl bg-zinc-950 hover:bg-zinc-800 text-amber-400 font-black text-xs shadow-md transition cursor-pointer flex items-center justify-center gap-1.5';
                btnText.textContent = 'Konfirmasi & Selesaikan';
                if (fileInput) fileInput.required = true;
            } else {
                // Owner BELUM mengatur rekening -> Tampilkan alert terkunci & disable tombol
                alertUnconfigured.classList.remove('hidden');
                cardConfigured.classList.add('hidden');
                formTransferArea.classList.add('hidden');

                btnSubmit.disabled = true;
                btnSubmit.className = 'py-2.5 px-4 rounded-xl bg-zinc-200 text-zinc-400 font-bold text-xs cursor-not-allowed flex items-center justify-center gap-1.5';
                btnText.textContent = 'Terkunci (Rekening Belum Diatur)';
                if (fileInput) fileInput.required = false;
            }

            document.getElementById('modal-process-payout').showModal();
        }
    </script>
</x-layouts.app>
