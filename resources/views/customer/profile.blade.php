<x-layouts.customer title="Profil Saya" :backUrl="route('customer.dashboard')" :tenant="$tenant">
    <div class="space-y-4" x-data="{ showWithdrawModal: false, showMutationHistory: false, showWithdrawHistory: false }">
        <!-- Kartu Ringkasan Akun Mobile -->
        <div class="p-5 rounded-2xl bg-zinc-950 text-white shadow-xl flex items-center justify-between gap-3 border border-zinc-800">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-12 h-12 rounded-2xl bg-amber-500 text-zinc-950 grid place-items-center font-extrabold text-xl shadow-md shadow-amber-500/25 shrink-0">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <h1 class="text-base font-extrabold tracking-tight truncate">{{ $user->name }}</h1>
                    <p class="text-xs text-zinc-400 truncate">{{ $user->phone ?? 'Belum ada nomor HP' }}</p>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-400 text-[10px] font-extrabold mt-1">
                        <x-ui.icon name="users" class="w-3 h-3" />
                        <span>Pelanggan BarberOS</span>
                    </span>
                </div>
            </div>

            <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                @csrf
                <button
                    type="submit"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-zinc-800 hover:bg-rose-600 text-zinc-300 hover:text-white font-bold text-xs transition cursor-pointer"
                    title="Keluar Akun"
                >
                    <x-ui.icon name="log-out" class="w-3.5 h-3.5" />
                    <span>Keluar</span>
                </button>
            </form>
        </div>

        <!-- KARTU DOMPET & SALDO REFUND CUSTOMER -->
        <div class="rounded-3xl border border-amber-300/80 bg-gradient-to-br from-amber-50 via-white to-amber-50/40 p-5 shadow-sm space-y-4">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-2xl bg-amber-500 text-zinc-950 grid place-items-center font-black shadow-md shadow-amber-500/20 shrink-0">
                        <x-ui.icon name="wallet" class="w-5 h-5" />
                    </div>
                    <div>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-500">Saldo Dompet Akun</h2>
                        <div class="text-2xl font-black text-zinc-950 num">
                            Rp{{ number_format($user->wallet_balance, 0, ',', '.') }}
                        </div>
                    </div>
                </div>

                @php
                    $availableBalance = $availableBalance ?? (isset($user) ? app(\App\Services\CustomerWalletService::class)->getAvailableBalance($user) : 0);
                    $pendingWithdrawalAmount = isset($withdrawals) ? $withdrawals->where('status', 'pending')->sum('amount') : 0;
                @endphp
                @if($availableBalance >= 10000)
                    <button
                        type="button"
                        @click="showWithdrawModal = true"
                        class="px-3.5 py-2 rounded-xl bg-zinc-950 hover:bg-zinc-800 text-amber-400 font-bold text-xs shadow-md transition cursor-pointer inline-flex items-center gap-1.5 shrink-0"
                    >
                        <x-ui.icon name="arrow-up-right" class="w-4 h-4 text-amber-400" />
                        <span>Tarik Dana</span>
                    </button>
                @else
                    <button
                        type="button"
                        disabled
                        class="px-3 py-2 rounded-xl bg-zinc-100 text-zinc-400 font-bold text-xs inline-flex items-center gap-1.5 shrink-0 cursor-not-allowed"
                        title="{{ $pendingWithdrawalAmount > 0 ? 'Sedang ada permohonan penarikan yang menunggu ACC' : 'Saldo minimal Rp10.000 untuk penarikan' }}"
                    >
                        <x-ui.icon name="arrow-up-right" class="w-4 h-4 text-zinc-300" />
                        <span>Tarik Dana</span>
                    </button>
                @endif
            </div>

            @if($pendingWithdrawalAmount > 0)
                <div class="flex items-center justify-between p-2.5 rounded-xl bg-amber-50 border border-amber-200/80 text-xs">
                    <span class="flex items-center gap-1.5 font-bold text-amber-900">
                        <x-ui.icon name="clock" class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                        <span>Sedang Diproses (Menunggu ACC):</span>
                    </span>
                    <span class="font-black text-amber-950 num">Rp{{ number_format($pendingWithdrawalAmount, 0, ',', '.') }}</span>
                </div>
            @endif

            <div class="flex items-start gap-2.5 p-3 rounded-2xl bg-white/90 border border-amber-200/60 text-[11px] text-zinc-600 leading-relaxed">
                <x-ui.icon name="info" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                <span><strong class="text-zinc-900">Saldo Dompet</strong> berasal dari refund booking. Dapat digunakan kembali untuk booking atau ditarik ke rekening/e-wallet (Bebas Biaya Admin).</span>
            </div>

            <!-- RIWAYAT MUTASI DOMPET (KREDIT / DEBIT) -->
            @if(isset($walletTransactions) && $walletTransactions->isNotEmpty())
                <div class="pt-3 border-t border-amber-200/60">
                    <button
                        type="button"
                        @click="showMutationHistory = !showMutationHistory"
                        class="w-full flex items-center justify-between text-left group cursor-pointer py-1"
                    >
                        <div class="flex items-center gap-1.5">
                            <span class="font-extrabold text-xs uppercase tracking-wider text-zinc-700 group-hover:text-zinc-950 transition">
                                Riwayat Mutasi Saldo
                            </span>
                            <span class="text-[10px] text-zinc-400 font-bold bg-zinc-100 px-1.5 py-0.5 rounded-md">
                                {{ $walletTransactions->count() }}
                            </span>
                        </div>
                        <div class="flex items-center gap-1 text-[11px] font-bold text-zinc-500 group-hover:text-zinc-800 transition">
                            <span x-text="showMutationHistory ? 'Sembunyikan' : 'Lihat'"></span>
                            <x-ui.icon name="chevron-down" class="w-3.5 h-3.5 transition-transform duration-200" ::class="showMutationHistory ? 'rotate-180' : ''" />
                        </div>
                    </button>

                    <div x-show="showMutationHistory" x-cloak class="mt-2.5 space-y-1.5 max-h-56 overflow-y-auto pr-1">
                        @foreach($walletTransactions as $tx)
                            <div class="p-2.5 rounded-xl bg-white border border-zinc-200/80 flex items-center justify-between text-xs shadow-2xs">
                                <div class="min-w-0 pr-2">
                                    <p class="font-bold text-zinc-900 truncate">
                                        {{ $tx->notes ?: ucfirst(str_replace('_', ' ', $tx->category)) }}
                                    </p>
                                    <span class="text-[10px] text-zinc-400">{{ $tx->created_at->translatedFormat('d M Y, H:i') }}</span>
                                </div>
                                <div class="text-right shrink-0">
                                    @if($tx->type === 'credit')
                                        <span class="font-black text-emerald-600 num">+Rp{{ number_format($tx->amount, 0, ',', '.') }}</span>
                                    @else
                                        <span class="font-black text-rose-600 num">-Rp{{ number_format($tx->amount, 0, ',', '.') }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- RIWAYAT PENARIKAN DANA CUSTOMER -->
            @if(isset($withdrawals) && $withdrawals->isNotEmpty())
                <div class="pt-3 border-t border-amber-200/60">
                    <button
                        type="button"
                        @click="showWithdrawHistory = !showWithdrawHistory"
                        class="w-full flex items-center justify-between text-left group cursor-pointer py-1"
                    >
                        <div class="flex items-center gap-1.5">
                            <span class="font-extrabold text-xs uppercase tracking-wider text-zinc-700 group-hover:text-zinc-950 transition">
                                Riwayat Pengajuan Tarik Dana
                            </span>
                            <span class="text-[10px] text-zinc-400 font-bold bg-zinc-100 px-1.5 py-0.5 rounded-md">
                                {{ $withdrawals->count() }}
                            </span>
                        </div>
                        <div class="flex items-center gap-1 text-[11px] font-bold text-zinc-500 group-hover:text-zinc-800 transition">
                            <span x-text="showWithdrawHistory ? 'Sembunyikan' : 'Lihat'"></span>
                            <x-ui.icon name="chevron-down" class="w-3.5 h-3.5 transition-transform duration-200" ::class="showWithdrawHistory ? 'rotate-180' : ''" />
                        </div>
                    </button>

                    <div x-show="showWithdrawHistory" x-cloak class="mt-2.5 space-y-1.5 max-h-56 overflow-y-auto pr-1">
                        @foreach($withdrawals as $wd)
                            <div class="p-2.5 rounded-xl bg-white border border-zinc-200/80 flex items-center justify-between text-xs shadow-2xs">
                                <div class="min-w-0 pr-2">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-mono font-bold text-zinc-900">{{ $wd->withdrawal_code }}</span>
                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider
                                            {{ $wd->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($wd->status === 'rejected' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') }}">
                                            {{ $wd->status === 'completed' ? 'Ditransfer' : ($wd->status === 'rejected' ? 'Ditolak' : 'Menunggu Transfer') }}
                                        </span>
                                    </div>
                                    <p class="text-[10px] text-zinc-500 mt-0.5">
                                        {{ $wd->bank_name }} · {{ $wd->account_number }} (a.n {{ $wd->account_holder }})
                                    </p>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="font-black text-zinc-900 num">Rp{{ number_format($wd->amount, 0, ',', '.') }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <!-- MODAL PENARIKAN DANA (WITHDRAWAL) -->
        <div
            x-show="showWithdrawModal"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/60 backdrop-blur-xs"
            @keydown.escape.window="showWithdrawModal = false"
        >
            <div
                class="w-full max-w-md bg-white rounded-3xl p-5 sm:p-6 shadow-2xl border border-zinc-200 space-y-4 max-h-[90vh] overflow-y-auto"
                @click.outside="showWithdrawModal = false"
            >
                <div class="flex items-center justify-between border-b border-zinc-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-600 grid place-items-center">
                            <x-ui.icon name="arrow-up-right" class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="font-black text-sm text-zinc-950">Tarik Saldo ke Rekening / E-Wallet</h3>
                            <p class="text-[10px] text-zinc-400">Gratis biaya admin (Rp0)</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="showWithdrawModal = false"
                        class="p-1 rounded-lg text-zinc-400 hover:text-zinc-600 hover:bg-zinc-100"
                    >
                        <x-ui.icon name="x" class="w-4 h-4" />
                    </button>
                </div>

                <form method="POST" action="{{ route('customer.wallet.withdraw') }}" class="space-y-3.5">
                    @csrf

                    <div>
                        <label class="block font-bold text-xs text-zinc-700 mb-1">
                            Nominal Penarikan <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-2.5 text-xs font-black text-zinc-400">Rp</span>
                            <input
                                type="number"
                                name="amount"
                                min="10000"
                                max="{{ $availableBalance }}"
                                value="{{ old('amount', $availableBalance) }}"
                                required
                                class="w-full rounded-xl border border-zinc-200 bg-zinc-50 pl-9 pr-3 py-2.5 text-sm font-black text-zinc-950 focus:bg-white focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:outline-none transition num"
                            />
                        </div>
                        <span class="text-[10px] text-zinc-500 mt-1 block">Saldo tersedia untuk ditarik: <strong class="text-zinc-900 num">Rp{{ number_format($availableBalance, 0, ',', '.') }}</strong> (Min. penarikan Rp10.000)</span>
                    </div>

                    <div>
                        <label class="block font-bold text-xs text-zinc-700 mb-1">
                            Tujuan Bank / E-Wallet <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="bank_name"
                            list="customer-bank-list"
                            required
                            placeholder="Contoh: BCA, Mandiri, GoPay, OVO, DANA"
                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2.5 text-xs text-zinc-900 focus:bg-white focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:outline-none transition uppercase"
                        />
                        <datalist id="customer-bank-list">
                            <option value="BCA">Bank Central Asia (BCA)</option>
                            <option value="Mandiri">Bank Mandiri</option>
                            <option value="BRI">Bank Rakyat Indonesia (BRI)</option>
                            <option value="BNI">Bank Negara Indonesia (BNI)</option>
                            <option value="BSI">Bank Syariah Indonesia (BSI)</option>
                            <option value="GoPay">GoPay</option>
                            <option value="OVO">OVO</option>
                            <option value="DANA">DANA</option>
                            <option value="ShopeePay">ShopeePay</option>
                            <option value="LinkAja">LinkAja</option>
                        </datalist>
                    </div>

                    <div>
                        <label class="block font-bold text-xs text-zinc-700 mb-1">
                            Nomor Rekening / No. HP E-Wallet <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="account_number"
                            required
                            placeholder="Nomor rekening bank atau nomor HP e-wallet"
                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2.5 text-xs font-mono font-bold text-zinc-900 focus:bg-white focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:outline-none transition"
                        />
                    </div>

                    <div>
                        <label class="block font-bold text-xs text-zinc-700 mb-1">
                            Nama Pemilik Rekening / Akun E-Wallet <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="account_holder"
                            value="{{ old('account_holder', $user->name) }}"
                            required
                            placeholder="Harus sesuai nama buku tabungan / akun e-wallet"
                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2.5 text-xs text-zinc-900 focus:bg-white focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:outline-none transition"
                        />
                    </div>

                    <div class="p-3 rounded-2xl bg-emerald-50 border border-emerald-200/80 text-[11px] text-emerald-900 space-y-1">
                        <div class="flex items-center gap-1.5 font-bold">
                            <x-ui.icon name="check-circle" class="w-3.5 h-3.5 text-emerald-600" />
                            <span>Bebas Biaya Admin (Rp0)</span>
                        </div>
                        <p class="text-emerald-800">
                            Permohonan Anda akan diproses transfer langsung oleh Admin Platform dalam 1x24 jam kerja.
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button
                            type="button"
                            @click="showWithdrawModal = false"
                            class="px-4 py-2.5 rounded-xl border border-zinc-200 text-xs font-bold text-zinc-600 hover:bg-zinc-50 transition cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2.5 rounded-xl bg-zinc-950 hover:bg-zinc-800 text-amber-400 font-black text-xs shadow-md transition cursor-pointer flex items-center gap-1.5"
                        >
                            <x-ui.icon name="arrow-up-right" class="w-4 h-4" />
                            <span>Ajukan Penarikan</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Form Edit Profil Mobile -->
        <x-ui.card>
            <x-slot:header>
                <h2 class="font-extrabold text-xs uppercase tracking-wider text-zinc-800 flex items-center gap-1.5">
                    <x-ui.icon name="edit" class="w-3.5 h-3.5 text-amber-600" />
                    <span>Ubah Informasi Akun</span>
                </h2>
            </x-slot:header>

            <form method="POST" action="{{ route('customer.profile.update') }}" class="space-y-3.5">
                @csrf
                @method('PUT')

                <x-ui.input
                    label="Nama Lengkap *"
                    name="name"
                    :value="old('name', $user->name)"
                    required
                />

                <x-ui.input
                    label="Nomor WhatsApp / HP *"
                    name="phone"
                    :value="old('phone', $user->phone)"
                    placeholder="0812..."
                    required
                />

                <x-ui.input
                    label="Email (Opsional)"
                    name="email"
                    type="email"
                    :value="old('email', $user->email)"
                    placeholder="nama@domain.com"
                />

                <div class="pt-3 border-t border-zinc-100 space-y-3">
                    <div>
                        <h3 class="font-extrabold text-xs text-zinc-800 uppercase tracking-wider">Ubah Kata Sandi (Opsional)</h3>
                        <p class="text-[11px] text-zinc-400">Kosongkan bila tidak ingin mengubah kata sandi.</p>
                    </div>

                    <x-ui.input
                        label="Kata Sandi Saat Ini"
                        name="current_password"
                        type="password"
                        placeholder="••••••••"
                    />

                    <x-ui.input
                        label="Kata Sandi Baru"
                        name="password"
                        type="password"
                        placeholder="Minimal 8 karakter"
                    />

                    <x-ui.input
                        label="Konfirmasi Kata Sandi Baru"
                        name="password_confirmation"
                        type="password"
                        placeholder="Ulangi kata sandi baru"
                    />
                </div>

                <div class="pt-2">
                    <x-ui.button type="submit" size="lg" class="w-full">
                        <span>Simpan Perubahan</span>
                        <x-ui.icon name="check" class="w-4 h-4" />
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-layouts.customer>
