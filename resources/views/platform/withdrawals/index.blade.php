<x-layouts.app title="Penarikan Saldo Customer" subtitle="Persetujuan dan log riwayat penarikan dana refund saldo akun customer" role="superadmin">
    <!-- 1. KARTU METRIK PENARIKAN CUSTOMER -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3.5 sm:gap-4 mb-6">
        <!-- Menunggu ACC / Transfer -->
        <div class="rounded-2xl border border-rose-300 bg-rose-50/50 p-4 sm:p-5 shadow-2xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between text-rose-700 mb-2">
                    <span class="text-[11px] font-black uppercase tracking-wider text-rose-900">Menunggu ACC</span>
                    <div class="w-8 h-8 rounded-xl bg-rose-500 text-white flex items-center justify-center font-bold shrink-0">
                        <x-ui.icon name="clock" class="w-4 h-4" />
                    </div>
                </div>
                <div class="text-xl sm:text-2xl font-black text-rose-950 num">
                    {{ number_format($pendingCount, 0, ',', '.') }} Pengajuan
                </div>
            </div>
            <p class="text-[11px] text-rose-800 font-medium mt-2">Customer yang menunggu transfer dana</p>
        </div>

        <!-- Total Nominal Menunggu Ditransfer -->
        <div class="rounded-2xl border border-zinc-200/90 bg-white p-4 sm:p-5 shadow-2xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between text-zinc-400 mb-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">Nominal Pending</span>
                    <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                        <x-ui.icon name="wallet" class="w-4 h-4 text-amber-600" />
                    </div>
                </div>
                <div class="text-xl sm:text-2xl font-black text-zinc-950 num">
                    Rp{{ number_format($pendingSum, 0, ',', '.') }}
                </div>
            </div>
            <p class="text-[11px] text-zinc-500 mt-2">Dana escrow yang perlu ditransfer</p>
        </div>

        <!-- Total Telah Ditransfer (Sukses) -->
        <div class="rounded-2xl border border-zinc-200/90 bg-white p-4 sm:p-5 shadow-2xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between text-zinc-400 mb-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">Telah Ditransfer</span>
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                        <x-ui.icon name="check-circle" class="w-4 h-4 text-emerald-600" />
                    </div>
                </div>
                <div class="text-xl sm:text-2xl font-black text-emerald-700 num">
                    Rp{{ number_format($completedSum, 0, ',', '.') }}
                </div>
            </div>
            <p class="text-[11px] text-zinc-500 mt-2">Total dana sukses dicairkan ke customer</p>
        </div>

        <!-- Total Seluruh Pengajuan -->
        <div class="rounded-2xl border border-zinc-800 bg-zinc-950 text-white p-4 sm:p-5 shadow-2xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between text-zinc-400 mb-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-amber-400">Total Log</span>
                    <div class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center shrink-0">
                        <x-ui.icon name="file-text" class="w-4 h-4 text-amber-400" />
                    </div>
                </div>
                <div class="text-xl sm:text-2xl font-black text-white num">
                    {{ number_format($totalCount, 0, ',', '.') }} Transaksi
                </div>
            </div>
            <p class="text-[11px] text-zinc-400 mt-2">Bebas biaya admin (Rp0) terpusat platform</p>
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

    <!-- 2. TABEL DAFTAR & LOG PENARIKAN CUSTOMER -->
    <div
        x-data="{
            tab: '{{ in_array($currentStatus, ['pending', 'completed', 'rejected'], true) ? $currentStatus : 'all' }}',
            setTab(newTab) {
                this.tab = newTab;
                const url = newTab === 'all'
                    ? window.location.pathname
                    : window.location.pathname + '?status=' + newTab;
                window.history.replaceState(null, '', url);
            },
            isVisible(status) {
                return this.tab === 'all' || this.tab === status;
            }
        }"
        class="rounded-3xl border border-zinc-200/90 bg-white shadow-2xs overflow-hidden"
    >
        <div class="p-4 sm:p-5 border-b border-zinc-100 flex flex-col md:flex-row md:items-center justify-between gap-3 sm:gap-4">
            <div>
                <h2 class="font-black text-base text-zinc-950">Log &amp; Persetujuan Penarikan Saldo Customer</h2>
                <p class="text-xs text-zinc-500 mt-0.5">Kelola verifikasi transfer dana refund dan pantau seluruh status permohonan customer</p>
            </div>

            <!-- Filter Status Tab (Tetap / Tanpa Scroll di Mobile & Tablet) -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-1 p-1 rounded-2xl bg-zinc-100 border border-zinc-200/80 w-full md:w-auto shrink-0">
                <button
                    type="button"
                    @click="setTab('all')"
                    :class="tab === 'all' ? 'bg-white text-zinc-950 shadow-xs font-black' : 'text-zinc-600 hover:text-zinc-950 font-bold'"
                    class="px-2.5 sm:px-3 py-1.5 rounded-xl text-xs transition cursor-pointer flex items-center justify-center gap-1.5"
                >
                    <span>Semua</span>
                    <span class="text-[10px] text-zinc-400 font-bold" :class="tab === 'all' ? 'text-zinc-700' : ''">({{ $totalCount }})</span>
                </button>
                <button
                    type="button"
                    @click="setTab('pending')"
                    :class="tab === 'pending' ? 'bg-white text-rose-700 shadow-xs font-black' : 'text-zinc-600 hover:text-rose-700 font-bold'"
                    class="px-2.5 sm:px-3 py-1.5 rounded-xl text-xs transition cursor-pointer flex items-center justify-center gap-1.5"
                >
                    <span>Menunggu ACC</span>
                    @if($pendingCount > 0)
                        <span class="px-1.5 py-0.2 rounded-full bg-rose-500 text-white text-[10px] font-black">{{ $pendingCount }}</span>
                    @else
                        <span class="text-[10px] text-zinc-400 font-bold">({{ $pendingCount }})</span>
                    @endif
                </button>
                <button
                    type="button"
                    @click="setTab('completed')"
                    :class="tab === 'completed' ? 'bg-white text-emerald-700 shadow-xs font-black' : 'text-zinc-600 hover:text-emerald-700 font-bold'"
                    class="px-2.5 sm:px-3 py-1.5 rounded-xl text-xs transition cursor-pointer flex items-center justify-center gap-1.5"
                >
                    <span>Ditransfer</span>
                    <span class="text-[10px] text-zinc-400 font-bold" :class="tab === 'completed' ? 'text-emerald-700' : ''">({{ $completedCount }})</span>
                </button>
                <button
                    type="button"
                    @click="setTab('rejected')"
                    :class="tab === 'rejected' ? 'bg-white text-rose-700 shadow-xs font-black' : 'text-zinc-600 hover:text-rose-700 font-bold'"
                    class="px-2.5 sm:px-3 py-1.5 rounded-xl text-xs transition cursor-pointer flex items-center justify-center gap-1.5"
                >
                    <span>Ditolak</span>
                    <span class="text-[10px] text-zinc-400 font-bold" :class="tab === 'rejected' ? 'text-rose-700' : ''">({{ $rejectedCount }})</span>
                </button>
            </div>
        </div>

        <!-- TAMPILAN TABEL (DESKTOP: LAYAR >= 1280px / XL) -->
        <div class="hidden xl:block overflow-x-auto">
            <table class="w-full min-w-[850px] text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-zinc-100 bg-zinc-50/70 text-zinc-500 font-bold uppercase text-[10px] tracking-wider whitespace-nowrap">
                        <th class="py-3 px-4">Kode WD</th>
                        <th class="py-3 px-4">Tanggal Pengajuan</th>
                        <th class="py-3 px-4">Customer</th>
                        <th class="py-3 px-4">Bank / E-Wallet</th>
                        <th class="py-3 px-4">Rekening / HP Penerima</th>
                        <th class="py-3 px-4 text-right">Nominal</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-center">Aksi Superadmin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 font-medium text-zinc-800">
                    @forelse($withdrawals as $wd)
                        <tr x-show="isVisible('{{ $wd->status }}')" class="hover:bg-zinc-50/60 transition">
                            <td class="py-3.5 px-4 font-mono font-black text-zinc-950 num whitespace-nowrap">
                                {{ $wd->withdrawal_code }}
                            </td>
                            <td class="py-3.5 px-4 text-zinc-600 whitespace-nowrap">
                                {{ $wd->created_at->translatedFormat('d M Y, H:i') }}
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <p class="font-bold text-zinc-900">{{ $wd->user?->name ?? 'Customer' }}</p>
                                <span class="text-[10px] text-zinc-400 font-mono">{{ $wd->user?->phone ?? '-' }}</span>
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded-md bg-zinc-100 text-zinc-800 font-black text-[10px] uppercase">
                                    {{ $wd->bank_name }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <div x-data="{ copied: false }" class="flex items-center gap-1.5">
                                    <span class="font-mono font-bold text-zinc-900 select-all">{{ $wd->account_number }}</span>
                                    <button
                                        type="button"
                                        @click="navigator.clipboard.writeText('{{ $wd->account_number }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                        class="p-1 rounded-lg hover:bg-zinc-100 text-zinc-400 hover:text-zinc-800 transition cursor-pointer"
                                        title="Salin Nomor Rekening / HP"
                                    >
                                        <span x-show="!copied">
                                            <x-ui.icon name="copy" class="w-3.5 h-3.5" />
                                        </span>
                                        <span x-show="copied" x-cloak class="text-[10px] font-black text-emerald-600">
                                            Tersalin!
                                        </span>
                                    </button>
                                </div>
                                <p class="text-[10px] text-zinc-400">a.n {{ $wd->account_holder }}</p>
                            </td>
                            <td class="py-3.5 px-4 text-right font-black text-zinc-950 num whitespace-nowrap">
                                Rp{{ number_format($wd->amount, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                @if($wd->status === 'completed')
                                    <span class="text-xs font-black text-emerald-600">
                                        Ditransfer
                                    </span>
                                @elseif($wd->status === 'rejected')
                                    <span class="text-xs font-black text-rose-600" title="{{ $wd->rejection_reason }}">
                                        Ditolak
                                    </span>
                                @else
                                    <span class="text-xs font-black text-rose-600">
                                        Menunggu ACC
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                @if($wd->status === 'pending' || $wd->status === 'processing')
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button
                                            type="button"
                                            onclick="openApproveWithdrawalModal('{{ $wd->id }}', '{{ $wd->withdrawal_code }}', '{{ number_format($wd->amount, 0, ',', '.') }}', '{{ $wd->bank_name }}', '{{ $wd->account_number }}', '{{ $wd->account_holder }}')"
                                            class="w-8 h-8 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white flex items-center justify-center transition shadow-xs cursor-pointer"
                                            title="ACC &amp; Selesai Transfer"
                                        >
                                            <x-ui.icon name="check" class="w-4 h-4" />
                                        </button>
                                        <button
                                            type="button"
                                            onclick="openRejectWithdrawalModal('{{ $wd->id }}', '{{ $wd->withdrawal_code }}', '{{ number_format($wd->amount, 0, ',', '.') }}')"
                                            class="w-8 h-8 rounded-xl bg-rose-600 hover:bg-rose-500 text-white flex items-center justify-center transition shadow-xs cursor-pointer"
                                            title="Tolak Penarikan"
                                        >
                                            <x-ui.icon name="x" class="w-4 h-4" />
                                        </button>
                                    </div>
                                @elseif($wd->proof_path)
                                    <a
                                        href="{{ \Illuminate\Support\Facades\Storage::url($wd->proof_path) }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="w-8 h-8 mx-auto rounded-xl bg-zinc-950 hover:bg-zinc-800 text-amber-400 flex items-center justify-center transition shadow-xs"
                                        title="Lihat Bukti Transfer"
                                    >
                                        <x-ui.icon name="external-link" class="w-4 h-4" />
                                    </a>
                                @else
                                    <span class="text-zinc-400 text-xs">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                    @endforelse

                    <tr x-show="(tab === 'all' && {{ $totalCount }} === 0) || (tab === 'pending' && {{ $pendingCount }} === 0) || (tab === 'completed' && {{ $completedCount }} === 0) || (tab === 'rejected' && {{ $rejectedCount }} === 0)">
                        <td colspan="8" class="py-12 text-center text-zinc-400">
                            <div class="w-14 h-14 rounded-2xl bg-zinc-100 text-zinc-400 flex items-center justify-center mx-auto mb-3 shadow-2xs">
                                <x-ui.icon name="inbox" class="w-7 h-7" />
                            </div>
                            <p class="text-xs font-bold text-zinc-800">Tidak Ada Data Penarikan</p>
                            <p class="text-[11px] text-zinc-400 mt-0.5">Tidak ada permohonan penarikan pada status ini.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- TAMPILAN KARTU / ITEM LIST (TABLET & MOBILE: LAYAR < 1280px) -->
        <div class="block xl:hidden divide-y divide-zinc-100">
            @forelse($withdrawals as $wd)
                <div x-show="isVisible('{{ $wd->status }}')" class="p-4 sm:p-5 hover:bg-zinc-50/60 transition space-y-3">
                    <!-- Header Item: Kode WD, Tanggal & Status -->
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-black text-xs text-zinc-950 num bg-zinc-100 px-2.5 py-1 rounded-lg">
                                {{ $wd->withdrawal_code }}
                            </span>
                            <span class="text-[11px] text-zinc-400 font-medium">
                                {{ $wd->created_at->translatedFormat('d M Y, H:i') }}
                            </span>
                        </div>
                        <div>
                            @if($wd->status === 'completed')
                                <span class="text-xs font-black text-emerald-600">
                                    Ditransfer
                                </span>
                            @elseif($wd->status === 'rejected')
                                <span class="text-xs font-black text-rose-600" title="{{ $wd->rejection_reason }}">
                                    Ditolak
                                </span>
                            @else
                                <span class="text-xs font-black text-rose-600">
                                    Menunggu ACC
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Detail Grid: Customer, Rekening, Nominal, & Aksi -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs pt-1">
                        <!-- Kolom Kiri: Customer & Rekening Penerima -->
                        <div class="space-y-2">
                            <div>
                                <span class="text-[10px] text-zinc-400 uppercase font-bold tracking-wider block">Customer</span>
                                <p class="font-bold text-zinc-900">{{ $wd->user?->name ?? 'Customer' }}</p>
                                <p class="text-[11px] text-zinc-500 font-mono">{{ $wd->user?->phone ?? '-' }}</p>
                            </div>
                            <div>
                                <span class="text-[10px] text-zinc-400 uppercase font-bold tracking-wider block">Rekening / E-Wallet Tujuan</span>
                                <div x-data="{ copied: false }" class="flex items-center gap-1.5 mt-0.5">
                                    <span class="px-2 py-0.5 rounded-md bg-zinc-100 text-zinc-800 font-black text-[10px] uppercase">
                                        {{ $wd->bank_name }}
                                    </span>
                                    <span class="font-mono font-bold text-zinc-900 select-all">{{ $wd->account_number }}</span>
                                    <button
                                        type="button"
                                        @click="navigator.clipboard.writeText('{{ $wd->account_number }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                        class="p-1 rounded-md hover:bg-zinc-200 text-zinc-500 hover:text-zinc-900 transition cursor-pointer flex items-center gap-1"
                                        title="Salin Nomor Rekening / HP"
                                    >
                                        <span x-show="!copied">
                                            <x-ui.icon name="copy" class="w-3.5 h-3.5" />
                                        </span>
                                        <span x-show="copied" x-cloak class="text-[10px] font-black text-emerald-600">
                                            Tersalin!
                                        </span>
                                    </button>
                                </div>
                                <p class="text-[11px] text-zinc-500">a.n {{ $wd->account_holder }}</p>
                            </div>
                        </div>

                        <!-- Kolom Kanan: Nominal & Tombol Aksi -->
                        <div class="flex flex-col justify-between items-start md:items-end gap-3 pt-2 md:pt-0 border-t md:border-t-0 border-zinc-100">
                            <div class="md:text-right">
                                <span class="text-[10px] text-zinc-400 uppercase font-bold tracking-wider block">Nominal Penarikan</span>
                                <span class="text-lg font-black text-zinc-950 num block">
                                    Rp{{ number_format($wd->amount, 0, ',', '.') }}
                                </span>
                                <span class="text-[10px] font-bold text-emerald-600">Bebas Biaya Admin (Rp0)</span>
                            </div>

                            @if($wd->status === 'pending' || $wd->status === 'processing')
                                <div class="flex items-center gap-2 w-full md:w-auto">
                                    <button
                                        type="button"
                                        onclick="openApproveWithdrawalModal('{{ $wd->id }}', '{{ $wd->withdrawal_code }}', '{{ number_format($wd->amount, 0, ',', '.') }}', '{{ $wd->bank_name }}', '{{ $wd->account_number }}', '{{ $wd->account_holder }}')"
                                        class="flex-1 md:flex-initial h-9 px-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition shadow-xs cursor-pointer"
                                        title="ACC &amp; Selesai Transfer"
                                    >
                                        <x-ui.icon name="check" class="w-4 h-4" />
                                        <span>ACC Transfer</span>
                                    </button>
                                    <button
                                        type="button"
                                        onclick="openRejectWithdrawalModal('{{ $wd->id }}', '{{ $wd->withdrawal_code }}', '{{ number_format($wd->amount, 0, ',', '.') }}')"
                                        class="flex-1 md:flex-initial h-9 px-3 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition shadow-xs cursor-pointer"
                                        title="Tolak Penarikan"
                                    >
                                        <x-ui.icon name="x" class="w-4 h-4" />
                                        <span>Tolak</span>
                                    </button>
                                </div>
                            @elseif($wd->proof_path)
                                <div>
                                    <a
                                        href="{{ \Illuminate\Support\Facades\Storage::url($wd->proof_path) }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-zinc-950 hover:bg-zinc-800 text-amber-400 text-xs font-bold transition shadow-xs"
                                        title="Lihat Bukti Transfer"
                                    >
                                        <x-ui.icon name="external-link" class="w-3.5 h-3.5" />
                                        <span>Lihat Bukti Transfer</span>
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if($wd->status === 'rejected' && $wd->rejection_reason)
                        <div class="p-2.5 rounded-xl bg-rose-50 border border-rose-200/60 text-[11px] text-rose-800">
                            <span class="font-bold">Alasan Ditolak:</span> {{ $wd->rejection_reason }}
                        </div>
                    @endif
                </div>
            @empty
            @endforelse

            <div
                x-show="(tab === 'all' && {{ $totalCount }} === 0) || (tab === 'pending' && {{ $pendingCount }} === 0) || (tab === 'completed' && {{ $completedCount }} === 0) || (tab === 'rejected' && {{ $rejectedCount }} === 0)"
                class="py-12 px-4 text-center text-zinc-400"
            >
                <div class="w-14 h-14 rounded-2xl bg-zinc-100 text-zinc-400 flex items-center justify-center mx-auto mb-3 shadow-2xs">
                    <x-ui.icon name="inbox" class="w-7 h-7" />
                </div>
                <p class="text-xs font-bold text-zinc-800">Tidak Ada Data Penarikan</p>
                <p class="text-[11px] text-zinc-400 mt-0.5">Tidak ada permohonan penarikan pada status ini.</p>
            </div>
        </div>

        @if($withdrawals->hasPages())
            <div class="p-4 border-t border-zinc-100">
                {{ $withdrawals->links() }}
            </div>
        @endif
    </div>

    <!-- MODAL APPROVE WITHDRAWAL CUSTOMER -->
    <dialog
        id="modal-approve-withdrawal"
        onclick="if(event.target===this)this.close()"
        class="m-auto w-[calc(100%-2rem)] max-w-md rounded-3xl bg-white text-zinc-900 p-0 shadow-2xl border border-zinc-200 backdrop:bg-zinc-950/60 backdrop:backdrop-blur-xs"
    >
        <form id="approve-wd-form" method="POST" action="" enctype="multipart/form-data" class="p-5 sm:p-6 space-y-4">
            @csrf
            <div class="flex items-center justify-between border-b border-zinc-100 pb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-700 grid place-items-center">
                        <x-ui.icon name="check-circle" class="w-4 h-4" />
                    </div>
                    <div>
                        <h3 class="font-black text-sm text-zinc-950">ACC &amp; Selesaikan Transfer</h3>
                        <p class="text-[10px] text-zinc-500 font-mono" id="approve-wd-code">WD-CODE</p>
                    </div>
                </div>
                <button type="button" onclick="document.getElementById('modal-approve-withdrawal').close()" class="p-1 rounded-lg text-zinc-400 hover:text-zinc-600 cursor-pointer">
                    <x-ui.icon name="x" class="w-4 h-4" />
                </button>
            </div>

            <div class="p-4 rounded-2xl bg-zinc-950 text-white space-y-3">
                <div>
                    <span class="text-[10px] text-zinc-400 uppercase tracking-wider block font-bold">Nominal Ditransfer ke Customer</span>
                    <div class="text-2xl font-black text-amber-400 num" id="approve-wd-amount">Rp0</div>
                </div>

                <div class="pt-2.5 border-t border-zinc-800/80 flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <span class="text-[10px] text-zinc-400 uppercase font-medium block">Tujuan Transfer</span>
                        <div class="text-xs font-bold text-zinc-200 truncate" id="approve-wd-bank-holder">-</div>
                        <div class="text-sm font-mono font-black text-amber-300 mt-0.5 select-all" id="approve-wd-account-number">-</div>
                    </div>
                    <button
                        type="button"
                        onclick="copyAccountNumber()"
                        id="btn-copy-acc"
                        class="px-2.5 py-1.5 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-200 text-[11px] font-bold flex items-center gap-1 transition shrink-0 cursor-pointer"
                        title="Salin Nomor Rekening / No. HP"
                    >
                        <x-ui.icon name="copy" class="w-3.5 h-3.5" />
                        <span id="text-copy-acc">Salin</span>
                    </button>
                </div>
            </div>

            <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                    <label class="block font-bold text-xs text-zinc-800">
                        Unggah Bukti Transfer
                    </label>
                    <span class="text-[10px] font-bold text-zinc-500 bg-zinc-100 px-2 py-0.5 rounded-md">
                        Opsional (Tidak Wajib)
                    </span>
                </div>

                <div
                    onclick="document.getElementById('wd_transfer_proof').click()"
                    class="group relative border-2 border-dashed border-zinc-200 hover:border-amber-400 bg-zinc-50 hover:bg-amber-50/20 rounded-2xl p-3.5 text-center cursor-pointer transition flex flex-col items-center justify-center gap-1.5"
                >
                    <input
                        type="file"
                        id="wd_transfer_proof"
                        name="transfer_proof"
                        accept="image/jpeg,image/png,image/webp,application/pdf"
                        class="hidden"
                        onchange="handleProofFileSelected(this)"
                    />

                    <div class="w-8 h-8 rounded-xl bg-white text-zinc-500 group-hover:text-amber-600 shadow-xs grid place-items-center transition">
                        <x-ui.icon name="upload-cloud" class="w-4 h-4" />
                    </div>

                    <div id="file-proof-placeholder">
                        <p class="text-xs font-bold text-zinc-700 group-hover:text-zinc-900 transition">
                            Pilih Foto / Screenshot Struk Transfer
                        </p>
                        <p class="text-[10px] text-zinc-400 mt-0.5">
                            JPG, PNG, atau PDF (Maks. 3 MB)
                        </p>
                    </div>

                    <div id="file-proof-selected" class="hidden flex items-center gap-2 bg-white px-3 py-1.5 rounded-xl border border-emerald-200 text-xs">
                        <x-ui.icon name="check-circle" class="w-4 h-4 text-emerald-600 shrink-0" />
                        <span class="font-bold text-zinc-800 truncate max-w-[200px]" id="file-proof-name">nama-file.png</span>
                        <button
                            type="button"
                            onclick="event.stopPropagation(); resetProofFile();"
                            class="text-rose-500 hover:text-rose-700 text-xs font-bold ml-1 cursor-pointer"
                        >
                            Hapus
                        </button>
                    </div>
                </div>

                <p class="text-[11px] text-zinc-500 leading-relaxed">
                    Boleh dikosongkan. Jika tidak diunggah, penarikan tetap selesai dan saldo customer otomatis terpotong.
                </p>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-100">
                <button
                    type="button"
                    onclick="document.getElementById('modal-approve-withdrawal').close()"
                    class="px-4 py-2 rounded-xl border border-zinc-200 text-xs font-bold text-zinc-600 hover:bg-zinc-50 cursor-pointer"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs shadow-md flex items-center gap-1.5 cursor-pointer"
                >
                    <x-ui.icon name="check" class="w-4 h-4" />
                    <span>ACC &amp; Tandai Ditransfer</span>
                </button>
            </div>
        </form>
    </dialog>

    <!-- MODAL REJECT WITHDRAWAL CUSTOMER -->
    <dialog
        id="modal-reject-withdrawal"
        onclick="if(event.target===this)this.close()"
        class="m-auto w-[calc(100%-2rem)] max-w-md rounded-3xl bg-white text-zinc-900 p-0 shadow-2xl border border-zinc-200 backdrop:bg-zinc-950/60 backdrop:backdrop-blur-xs"
    >
        <form id="reject-wd-form" method="POST" action="" class="p-5 sm:p-6 space-y-4">
            @csrf
            <div class="flex items-center justify-between border-b border-zinc-100 pb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-rose-100 text-rose-700 grid place-items-center">
                        <x-ui.icon name="x-circle" class="w-4 h-4" />
                    </div>
                    <div>
                        <h3 class="font-black text-sm text-zinc-950">Tolak Permohonan Penarikan</h3>
                        <p class="text-[10px] text-zinc-500 font-mono" id="reject-wd-code">WD-CODE</p>
                    </div>
                </div>
                <button type="button" onclick="document.getElementById('modal-reject-withdrawal').close()" class="p-1 rounded-lg text-zinc-400 hover:text-zinc-600 cursor-pointer">
                    <x-ui.icon name="x" class="w-4 h-4" />
                </button>
            </div>

            <div class="p-3 rounded-2xl bg-zinc-50 border border-zinc-200/80 text-xs text-zinc-700 leading-relaxed">
                Permohonan penarikan dana (<strong class="num font-bold text-zinc-950" id="reject-wd-amount">Rp0</strong>) akan ditolak dan saldo tetap utuh di dompet customer.
            </div>

            <div>
                <label class="block font-bold text-xs text-zinc-700 mb-1">
                    Alasan Penolakan <span class="text-rose-500">*</span>
                </label>
                <textarea
                    name="rejection_reason"
                    rows="2"
                    required
                    placeholder="Contoh: Nomor rekening tidak valid atau nama pemilik tidak sesuai..."
                    class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs text-zinc-900 focus:bg-white focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20 focus:outline-none transition"
                ></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-100">
                <button
                    type="button"
                    onclick="document.getElementById('modal-reject-withdrawal').close()"
                    class="px-4 py-2 rounded-xl border border-zinc-200 text-xs font-bold text-zinc-600 hover:bg-zinc-50 cursor-pointer"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-black text-xs shadow-md flex items-center gap-1.5 cursor-pointer"
                >
                    <x-ui.icon name="x" class="w-4 h-4" />
                    <span>Tolak Penarikan</span>
                </button>
            </div>
        </form>
    </dialog>

    <script>
        let currentTargetAccount = '';

        function openApproveWithdrawalModal(id, code, amount, bank, number, holder) {
            document.getElementById('approve-wd-form').action = '/platform/withdrawals/' + id + '/approve';
            document.getElementById('approve-wd-code').textContent = code;
            document.getElementById('approve-wd-amount').textContent = 'Rp' + amount;
            document.getElementById('approve-wd-bank-holder').textContent = bank + ' · a.n ' + holder;
            document.getElementById('approve-wd-account-number').textContent = number;
            currentTargetAccount = number;
            resetProofFile();
            document.getElementById('modal-approve-withdrawal').showModal();
        }

        function copyAccountNumber() {
            if (!currentTargetAccount) return;
            navigator.clipboard.writeText(currentTargetAccount).then(() => {
                const label = document.getElementById('text-copy-acc');
                label.textContent = 'Tersalin!';
                setTimeout(() => {
                    label.textContent = 'Salin';
                }, 2000);
            });
        }

        function handleProofFileSelected(input) {
            if (input.files && input.files[0]) {
                document.getElementById('file-proof-name').textContent = input.files[0].name;
                document.getElementById('file-proof-placeholder').classList.add('hidden');
                document.getElementById('file-proof-selected').classList.remove('hidden');
            }
        }

        function resetProofFile() {
            const input = document.getElementById('wd_transfer_proof');
            if (input) input.value = '';
            const placeholder = document.getElementById('file-proof-placeholder');
            const selected = document.getElementById('file-proof-selected');
            if (placeholder) placeholder.classList.remove('hidden');
            if (selected) selected.classList.add('hidden');
        }

        function openRejectWithdrawalModal(id, code, amount) {
            document.getElementById('reject-wd-form').action = '/platform/withdrawals/' + id + '/reject';
            document.getElementById('reject-wd-code').textContent = code;
            document.getElementById('reject-wd-amount').textContent = 'Rp' + amount;
            document.getElementById('modal-reject-withdrawal').showModal();
        }
    </script>
</x-layouts.app>
