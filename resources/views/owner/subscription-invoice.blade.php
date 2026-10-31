<x-layouts.app title="Pembayaran Langganan" subtitle="Rincian tagihan paket barbershop, pilihan metode pembayaran, dan unggah bukti transfer" role="owner">
    @php
        $targetPlan = $invoice->targetPlan ?? $invoice->subscription?->plan;
        $isPending = $invoice->status === 'pending';
        $isPaid = $invoice->status === 'paid';
        $isCancelled = $invoice->status === 'cancelled';
        $isUnpaid = $invoice->status === 'unpaid';
        $type = $invoice->type ?? 'renewal';
        $currentMethod = old('payment_method', $invoice->payment_method ?? 'transfer');
        $isCash = $currentMethod === 'cash';

        $selectedBankName = old('bank_name', $invoice->bank_name ?? '');
        $bankSelectOptions = collect($bankAccounts)->map(fn ($b) => [
            'value' => $b['bank'],
            'label' => $b['bank'] . ' (' . $b['account_number'] . ')',
            'sublabel' => 'a.n ' . $b['account_name'],
        ])->all();
    @endphp

    <!-- Tombol Kembali ke Halaman Paket & No Tagihan -->
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <a
            href="{{ route('owner.subscription') }}"
            class="inline-flex items-center gap-2 text-xs font-bold text-zinc-600 hover:text-amber-600 transition group"
        >
            <x-ui.icon name="arrow-left" class="w-4 h-4 text-zinc-400 group-hover:-translate-x-0.5 transition" />
            <span>Kembali ke Pilihan Paket</span>
        </a>

        <div class="flex items-center gap-2">
            <span class="text-xs text-zinc-400">No. Tagihan:</span>
            <span class="font-mono font-bold text-xs bg-zinc-100 text-zinc-800 px-2.5 py-1 rounded-lg border border-zinc-200">
                {{ $invoice->number }}
            </span>
        </div>
    </div>

    <!-- Status Banner Jika Sudah Diunggah / Menunggu Konfirmasi -->
    @if($isPending)
        <div class="mb-6 p-4 sm:p-5 rounded-2xl bg-amber-50 border-2 border-amber-400 text-amber-950 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <span class="w-10 h-10 rounded-xl bg-amber-500 text-zinc-950 flex items-center justify-center shrink-0 shadow-sm animate-pulse">
                    <x-ui.icon name="clock" class="w-5 h-5" />
                </span>
                <div>
                    <h3 class="font-black text-sm sm:text-base text-zinc-900">Bukti Pembayaran Sedang Diverifikasi</h3>
                    <p class="text-xs text-zinc-600 mt-0.5 leading-relaxed">
                        Bukti pembayaran telah berhasil Anda kirimkan pada <strong class="text-zinc-900">{{ $invoice->proof_uploaded_at?->translatedFormat('d M Y, H:i') ?? '-' }}</strong>. Mohon tunggu beberapa saat, Superadmin sedang memverifikasi transaksi Anda.
                    </p>
                </div>
            </div>
            <span class="text-xs font-black uppercase tracking-wider text-amber-700 shrink-0">
                Pending Verifikasi
            </span>
        </div>
    @elseif($isPaid)
        <div class="mb-6 p-4 sm:p-5 rounded-2xl bg-emerald-50 border-2 border-emerald-400 text-emerald-950 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <span class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center shrink-0 shadow-sm">
                    <x-ui.icon name="check" class="w-5 h-5" />
                </span>
                <div>
                    <h3 class="font-black text-sm sm:text-base text-zinc-900">Pembayaran Telah Dikonfirmasi Lunas</h3>
                    <p class="text-xs text-zinc-600 mt-0.5">
                        Tagihan ini telah diverifikasi oleh Superadmin pada <strong class="text-zinc-900">{{ $invoice->paid_at?->translatedFormat('d M Y, H:i') ?? '-' }}</strong>. Fitur barbershop Anda telah aktif!
                    </p>
                </div>
            </div>
            <span class="text-xs font-black uppercase tracking-wider text-emerald-700 shrink-0">
                Lunas
            </span>
        </div>
    @elseif($isCancelled)
        <div class="mb-6 p-4 sm:p-5 rounded-2xl bg-zinc-100 border-2 border-zinc-300 text-zinc-700 shadow-sm space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-zinc-200 text-zinc-600 flex items-center justify-center shrink-0">
                        <x-ui.icon name="x" class="w-5 h-5" />
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-black text-sm sm:text-base text-zinc-900">Tagihan Dibatalkan / Ditolak</h3>
                            <span class="text-[11px] font-black uppercase tracking-wider text-zinc-500">
                                {{ $invoice->cancelled_by === 'superadmin' ? 'Ditolak Superadmin' : 'Dibatalkan Owner' }}
                            </span>
                        </div>
                        <p class="text-xs text-zinc-500 mt-0.5">
                            Dibatalkan pada {{ $invoice->cancelled_at?->translatedFormat('d M Y, H:i') ?? $invoice->updated_at?->translatedFormat('d M Y, H:i') }}
                        </p>
                    </div>
                </div>
                <a href="{{ route('owner.subscription') }}" class="px-4 py-2 rounded-xl bg-zinc-900 text-white text-xs font-bold hover:bg-zinc-800 transition inline-flex items-center gap-1.5 self-start sm:self-auto cursor-pointer">
                    <x-ui.icon name="refresh-cw" class="w-3.5 h-3.5" />
                    <span>Pilih Paket Baru</span>
                </a>
            </div>

            @if($invoice->cancellation_reason)
                <div class="p-3.5 rounded-xl bg-white border border-zinc-200 text-xs space-y-1">
                    <span class="font-bold text-zinc-800 block">Alasan Pembatalan / Penolakan:</span>
                    <p class="text-zinc-600 italic leading-relaxed">"{{ $invoice->cancellation_reason }}"</p>
                </div>
            @endif
        </div>
    @endif

    <!-- Layout Utama Full Width (Full Kanan-Kiri) -->
    <div class="space-y-6">
        <!-- 1. Kartu Rincian Tagihan (Full Width) -->
        <x-ui.card>
            <x-slot:header>
                <div class="flex items-center justify-between w-full">
                    <h3 class="font-bold text-sm sm:text-base flex items-center gap-2 text-zinc-900">
                        <x-ui.icon name="receipt" class="w-4 h-4 text-amber-500" />
                        <span>Rincian Tagihan Berlangganan</span>
                    </h3>
                    <span class="text-xs font-black uppercase tracking-wider
                        @if($isPaid) text-emerald-700
                        @elseif($isPending) text-amber-700
                        @elseif($isCancelled) text-zinc-500
                        @else text-rose-600 @endif
                    ">
                        {{ $isPaid ? 'Lunas' : ($isPending ? 'Menunggu Verifikasi' : ($isCancelled ? 'Dibatalkan' : 'Menunggu Bayar')) }}
                    </span>
                </div>
            </x-slot:header>

            <div class="space-y-4">
                <!-- Paket & Harga Banner -->
                <div class="p-4 sm:p-5 rounded-2xl bg-zinc-50 border border-zinc-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-black text-lg sm:text-xl text-zinc-900">{{ $targetPlan?->name ?? 'Paket Barbershop' }}</span>
                            <x-ui.plan-badge :code="$targetPlan?->code ?? 'starter'" />
                            <span class="text-xs font-bold text-amber-600 bg-amber-50 border border-amber-200 px-2.5 py-0.5 rounded-md">
                                {{ ucfirst($type) }}
                            </span>
                        </div>
                        <p class="text-xs text-zinc-500 mt-1 max-w-xl">
                            {{ $targetPlan?->description ?? 'Langganan aplikasi barbershop multi-cabang & payroll.' }}
                        </p>
                    </div>
                    <div class="sm:text-right shrink-0">
                        <span class="text-[11px] font-medium text-zinc-400 block">Total Tagihan</span>
                        <span class="text-2xl sm:text-3xl font-black text-zinc-900 num">
                            Rp{{ number_format($invoice->amount, 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                <!-- Strip Mini Info Masa Aktif -->
                @if($type === 'upgrade' || $type === 'downgrade')
                    <div class="p-2.5 rounded-xl bg-amber-50/70 border border-amber-200/70 flex items-center justify-between gap-2 text-xs">
                        <div class="flex items-center gap-1.5 text-amber-900 font-medium truncate">
                            <x-ui.icon name="info" class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                            <span class="truncate">Sisa hari paket lama tidak ditumpuk, diganti 30 hari paket baru.</span>
                        </div>
                        <button
                            type="button"
                            onclick="document.getElementById('modal-info-masa-aktif').showModal()"
                            class="text-amber-700 font-bold hover:underline shrink-0 text-[11px] cursor-pointer"
                        >
                            Info
                        </button>
                    </div>
                @else
                    <div class="p-2.5 rounded-xl bg-emerald-50/70 border border-emerald-200/70 flex items-center gap-2 text-xs text-emerald-900 font-medium">
                        <x-ui.icon name="check-circle" class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                        <span>Masa aktif bertambah +30 hari dari masa aktif saat ini.</span>
                    </div>
                @endif

                <!-- Meta Data Grid (4 Kolom Responsif) -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 rounded-xl bg-zinc-50/70 border border-zinc-200/70 text-xs">
                    <div>
                        <span class="text-[11px] text-zinc-400 block font-medium">Nomor Tagihan</span>
                        <span class="font-mono font-bold text-zinc-900">{{ $invoice->number }}</span>
                    </div>
                    <div>
                        <span class="text-[11px] text-zinc-400 block font-medium">Tanggal Diterbitkan</span>
                        <span class="font-semibold text-zinc-900">{{ $invoice->created_at->translatedFormat('d M Y, H:i') }}</span>
                    </div>
                    <div>
                        <span class="text-[11px] text-zinc-400 block font-medium">Batas Waktu Bayar</span>
                        <span class="font-semibold text-rose-600">
                            {{ $invoice->due_at ? $invoice->due_at->translatedFormat('d M Y, H:i') : '-' }}
                        </span>
                    </div>
                    <div>
                        <span class="text-[11px] text-zinc-400 block font-medium">Durasi Langganan</span>
                        <span class="font-semibold text-zinc-900">30 Hari Kalender</span>
                    </div>
                </div>
            </div>
        </x-ui.card>

        <!-- 2. Form Konfirmasi & Unggah Bukti Pembayaran (Full Width Card dengan 2 Kolom Input) -->
        @if(! $isPaid && ! $isCancelled)
            <x-ui.card>
                <x-slot:header>
                    <h3 class="font-bold text-sm sm:text-base flex items-center gap-2 text-zinc-900">
                        <x-ui.icon name="upload-cloud" class="w-4 h-4 text-amber-500" />
                        <span>{{ $isPending ? 'Ubah / Perbarui Bukti Pembayaran' : 'Form Konfirmasi Pembayaran' }}</span>
                    </h3>
                </x-slot:header>

                <form
                    method="POST"
                    action="{{ route('owner.subscription.submit-payment', $invoice->id) }}"
                    enctype="multipart/form-data"
                    class="space-y-6"
                >
                    @csrf

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start invoice-form-grid">
                        <!-- Sisi Kiri: Pilihan Metode & Rekening Bank -->
                        <div class="space-y-5">
                            <!-- 1. Pilihan Metode Pembayaran -->
                            <div class="space-y-2">
                                <label class="block text-xs font-bold text-zinc-800">
                                    1. Pilih Metode Pembayaran <span class="text-rose-500">*</span>
                                </label>
                                <div class="grid grid-cols-2 gap-3">
                                    <label class="cursor-pointer border-2 rounded-xl p-3 flex flex-col items-center text-center gap-1.5 transition {{ ! $isCash ? 'border-amber-500 bg-amber-50/50' : 'border-zinc-200 hover:border-zinc-300' }}" id="method-card-transfer">
                                        <input
                                            type="radio"
                                            name="payment_method"
                                            value="transfer"
                                            class="sr-only"
                                            {{ ! $isCash ? 'checked' : '' }}
                                            onchange="switchPaymentMethod('transfer')"
                                        >
                                        <x-ui.icon name="credit-card" class="w-5 h-5 text-amber-600" />
                                        <span class="text-xs font-black text-zinc-900">Transfer Bank</span>
                                        <span class="text-[10px] text-zinc-500">BCA, Mandiri, BRI, BNI</span>
                                    </label>

                                    <label class="cursor-pointer border-2 rounded-xl p-3 flex flex-col items-center text-center gap-1.5 transition {{ $isCash ? 'border-amber-500 bg-amber-50/50' : 'border-zinc-200 hover:border-zinc-300' }}" id="method-card-cash">
                                        <input
                                            type="radio"
                                            name="payment_method"
                                            value="cash"
                                            class="sr-only"
                                            {{ $isCash ? 'checked' : '' }}
                                            onchange="switchPaymentMethod('cash')"
                                        >
                                        <x-ui.icon name="wallet" class="w-5 h-5 text-emerald-600" />
                                        <span class="text-xs font-black text-zinc-900">Tunai (Cash)</span>
                                        <span class="text-[10px] text-zinc-500">Bayar Langsung</span>
                                    </label>
                                </div>
                                @error('payment_method')
                                    <p class="text-rose-600 text-xs font-semibold">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- 2. Pilihan Bank Tujuan & Card Rekening Terpilih (Khusus Transfer) -->
                            <div id="bank-selection-field" class="space-y-2.5 {{ $isCash ? 'hidden' : '' }}">
                                <label class="block text-xs font-bold text-zinc-800">
                                    2. Rekening Tujuan Transfer <span class="text-rose-500">*</span>
                                </label>

                                <x-ui.select
                                    name="bank_name"
                                    icon="building-2"
                                    :value="$selectedBankName"
                                    :options="$bankSelectOptions"
                                    placeholder="-- Pilih Rekening Bank Tujuan --"
                                />
                                @error('bank_name')
                                    <p class="text-rose-600 text-xs font-semibold">{{ $message }}</p>
                                @enderror

                                <!-- Card Rekening Bank Terpilih (Muncul Otomatis Sesuai Bank yang Dipilih) -->
                                <div id="selected-bank-cards-wrapper" class="pt-1">
                                    @foreach($bankAccounts as $bank)
                                        <div
                                            id="bank-detail-card-{{ $bank['bank'] }}"
                                            data-bank-detail-card="{{ $bank['bank'] }}"
                                            class="{{ $selectedBankName === $bank['bank'] ? '' : 'hidden' }} p-4 rounded-2xl bg-zinc-50 border border-zinc-200/90 shadow-2xs space-y-3 transition-all"
                                        >
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-black text-white {{ $bank['logo_color'] ?? 'bg-zinc-800' }} shadow-2xs">
                                                        {{ $bank['bank'] }}
                                                    </span>
                                                    <span class="text-xs font-bold text-zinc-900">Rekening Resmi Platform</span>
                                                </div>
                                                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200">
                                                    <x-ui.icon name="shield-check" class="w-3.5 h-3.5 text-emerald-600" />
                                                    <span>Akun Resmi</span>
                                                </span>
                                            </div>

                                            <!-- Nomor Rekening & Tombol Salin -->
                                            <div class="space-y-1">
                                                <span class="text-[11px] font-medium text-zinc-500 block">Nomor Rekening:</span>
                                                <div class="flex items-center justify-between gap-2 p-2 rounded-xl bg-white border border-zinc-200/90 overflow-hidden">
                                                    <span class="font-mono text-xs sm:text-sm font-black text-zinc-900 tracking-wider truncate min-w-0 select-all copy-target-{{ $bank['bank'] }}">
                                                        {{ $bank['account_number'] }}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        onclick="copyToClipboard('{{ $bank['account_number'] }}', this)"
                                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-[11px] font-bold border border-zinc-200 shrink-0 transition active:scale-95 cursor-pointer"
                                                        title="Salin Nomor Rekening"
                                                    >
                                                        <x-ui.icon name="copy" class="w-3.5 h-3.5 text-zinc-600 shrink-0" />
                                                        <span>Salin</span>
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Atas Nama & Nominal Tagihan -->
                                            <div class="pt-2 border-t border-zinc-200/60 flex items-center justify-between text-xs text-zinc-500">
                                                <span>Atas Nama:</span>
                                                <span class="font-bold text-zinc-800 truncate ml-2 text-right">{{ $bank['account_name'] }}</span>
                                            </div>

                                            <div class="p-2.5 rounded-xl bg-amber-50/70 border border-amber-200/70 flex items-center justify-between gap-2 text-xs">
                                                <span class="text-amber-900 font-medium">Nominal transfer tepat:</span>
                                                <div class="flex items-center gap-2">
                                                    <strong class="font-black text-amber-950 num">Rp{{ number_format($invoice->amount, 0, ',', '.') }}</strong>
                                                    <button
                                                        type="button"
                                                        onclick="copyToClipboard('{{ $invoice->amount }}', this)"
                                                        class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-800 hover:text-amber-950 underline cursor-pointer"
                                                        title="Salin Nominal Tagihan"
                                                    >
                                                        Salin
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach

                                    <div id="bank-detail-placeholder" class="{{ ! empty($selectedBankName) ? 'hidden' : '' }} p-3.5 rounded-xl border border-dashed border-zinc-300 text-center bg-zinc-50/60 text-xs text-zinc-500">
                                        <x-ui.icon name="building-2" class="w-4 h-4 mx-auto mb-1 text-zinc-400" />
                                        <span>Pilih salah satu rekening bank di atas untuk melihat detail nomor rekening tujuan transfer.</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Petunjuk Tunai (Khusus jika Tunai / Cash dipilih) -->
                            <div id="cash-instructions-field" class="space-y-2.5 {{ ! $isCash ? 'hidden' : '' }}">
                                <div class="p-4 rounded-2xl bg-emerald-50/70 border border-emerald-200/70 text-xs text-emerald-950 space-y-2">
                                    <p class="font-bold flex items-center gap-1.5 text-emerald-900">
                                        <x-ui.icon name="wallet" class="w-4 h-4 text-emerald-600" />
                                        <span>Petunjuk Pembayaran Tunai (Cash):</span>
                                    </p>
                                    <ol class="list-decimal list-inside space-y-1.5 text-emerald-900 leading-relaxed text-[11px]">
                                        <li>Kunjungi kantor operasional platform resmi platform.</li>
                                        <li>Sebutkan No. Tagihan: <strong class="font-mono text-emerald-950 font-black">{{ $invoice->number }}</strong>.</li>
                                        <li>Bayarkan nominal tepat <strong class="font-black text-emerald-950">Rp{{ number_format($invoice->amount, 0, ',', '.') }}</strong> secara tunai.</li>
                                        <li>Minta kuitansi resmi bertandatangan kasir lalu foto lembar tersebut.</li>
                                        <li>Unggah foto kuitansi pada formulir di samping kanan.</li>
                                    </ol>
                                    <p class="text-[10px] text-emerald-700 font-semibold pt-1">
                                        Jam Layanan Kasir: Senin &ndash; Sabtu, 09:00 &ndash; 18:00 WIB
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Sisi Kanan: Upload Bukti & Catatan Pembayaran -->
                        <div class="space-y-5">
                            <!-- 3. Unggah File Bukti Pembayaran -->
                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold text-zinc-800">
                                    3. Unggah Bukti Pembayaran <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative border-2 border-dashed border-zinc-300 hover:border-amber-500 rounded-2xl p-5 text-center bg-zinc-50/50 hover:bg-amber-50/20 transition cursor-pointer" onclick="document.getElementById('proof-file-input').click()">
                                    <input
                                        type="file"
                                        name="proof"
                                        id="proof-file-input"
                                        class="hidden"
                                        accept="image/png,image/jpeg,image/webp,application/pdf"
                                        onchange="previewProofImage(this)"
                                    >
                                    <div id="upload-placeholder" class="{{ $invoice->proof_path ? 'hidden' : 'space-y-2' }}">
                                        <span class="w-11 h-11 rounded-full bg-amber-100 text-amber-600 inline-flex items-center justify-center">
                                            <x-ui.icon name="image" class="w-5 h-5" />
                                        </span>
                                        <p class="text-xs font-bold text-zinc-800">Klik di sini untuk memilih foto struk / screenshot transfer</p>
                                        <p class="text-[11px] text-zinc-400">Format: PNG, JPG, WEBP, atau PDF (Maksimal 2MB)</p>
                                    </div>

                                    <div id="file-preview-box" class="{{ $invoice->proof_path ? '' : 'hidden' }} space-y-2">
                                        @if($invoice->proof_url)
                                            <img
                                                src="{{ $invoice->proof_url }}"
                                                alt="Bukti Transfer Sebelumnya"
                                                id="preview-img-tag"
                                                class="max-h-48 mx-auto rounded-xl object-contain border border-zinc-200 shadow-2xs"
                                            >
                                        @else
                                            <img
                                                src=""
                                                alt="Preview Bukti"
                                                id="preview-img-tag"
                                                class="max-h-48 mx-auto rounded-xl object-contain border border-zinc-200 shadow-2xs hidden"
                                            >
                                        @endif
                                        <p class="text-xs font-semibold text-emerald-700" id="preview-filename">
                                            {{ $invoice->proof_path ? 'Bukti saat ini tersimpan. Klik untuk mengganti foto.' : 'File dipilih' }}
                                        </p>
                                    </div>
                                </div>
                                <p id="client-proof-error" class="text-rose-600 text-xs font-semibold hidden"></p>
                                @error('proof')
                                    <p class="text-rose-600 text-xs font-semibold">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- 4. Catatan / Referensi Pengirim (Optional) -->
                            <div class="space-y-1.5">
                                <label for="notes" class="block text-xs font-bold text-zinc-800">
                                    4. Catatan Pembayaran <span class="text-zinc-400 font-normal">(Opsional)</span>
                                </label>
                                <textarea
                                    name="notes"
                                    id="notes"
                                    rows="2"
                                    placeholder="Contoh: Ditransfer via m-BCA a.n Rudi Hartono"
                                    class="w-full text-xs rounded-xl border border-zinc-200 bg-white px-3 py-2.5 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition"
                                >{{ old('notes', $invoice->notes) }}</textarea>
                                @error('notes')
                                    <p class="text-rose-600 text-xs font-semibold">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Kirim Bukti (Full Width) -->
                    <div class="pt-3 border-t border-zinc-100">
                        <button
                            type="submit"
                            class="w-full py-3.5 px-6 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-zinc-950 font-black text-sm shadow-sm hover:shadow transition flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <x-ui.icon name="check-circle" class="w-4 h-4 text-zinc-950" />
                            <span>{{ $isPending ? 'Simpan & Perbarui Bukti Pembayaran' : 'Kirim Bukti Pembayaran' }}</span>
                        </button>
                    </div>
                </form>
            </x-ui.card>
        @endif

        <!-- 3. Bukti Pembayaran Terverifikasi (Jika Sudah Lunas) -->
        @if($isPaid && $invoice->proof_url)
            <x-ui.card>
                <x-slot:header>
                    <h3 class="font-bold text-sm text-zinc-900 flex items-center gap-2">
                        <x-ui.icon name="file-check" class="w-4 h-4 text-emerald-600" />
                        <span>Arsip Bukti Pembayaran</span>
                    </h3>
                </x-slot:header>
                <div class="space-y-3">
                    <img
                        src="{{ $invoice->proof_url }}"
                        alt="Bukti Transfer Lunas"
                        class="w-full max-h-72 object-contain rounded-xl border border-zinc-200 shadow-2xs"
                    >
                    <div class="text-xs text-zinc-500">
                        Metode: <strong class="text-zinc-800 capitalize">{{ $invoice->payment_method ?? 'transfer' }}</strong> &bull; Bank: <strong class="text-zinc-800">{{ $invoice->bank_name ?? '-' }}</strong>
                    </div>
                </div>
            </x-ui.card>
        @endif

        <!-- 4. Panduan & Ketentuan Pembayaran (Responsif di Tablet & Desktop) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3.5 invoice-guide-grid">
            <!-- 1. Langkah Pembayaran -->
            <div class="p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs space-y-2.5">
                <div class="flex items-center gap-2 text-zinc-900 font-bold text-xs pb-2 border-b border-zinc-100">
                    <div class="w-7 h-7 rounded-lg bg-amber-500/10 text-amber-600 grid place-items-center shrink-0">
                        <x-ui.icon name="credit-card" class="w-4 h-4" />
                    </div>
                    <span>Langkah Pembayaran</span>
                </div>
                <ol class="text-[11px] text-zinc-600 space-y-1.5 list-decimal list-inside leading-relaxed pt-0.5">
                    <li>Pilih bank &amp; salin nomor rekening resmi platform.</li>
                    <li>Transfer nominal tepat <strong class="text-zinc-900 font-mono font-bold">Rp{{ number_format($invoice->amount, 0, ',', '.') }}</strong>.</li>
                    <li>Unggah foto bukti transfer dan kirim konfirmasi.</li>
                </ol>
            </div>

            <!-- 2. Waktu Verifikasi -->
            <div class="p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs space-y-2.5">
                <div class="flex items-center gap-2 text-zinc-900 font-bold text-xs pb-2 border-b border-zinc-100">
                    <div class="w-7 h-7 rounded-lg bg-emerald-500/10 text-emerald-600 grid place-items-center shrink-0">
                        <x-ui.icon name="clock" class="w-4 h-4" />
                    </div>
                    <span>Waktu Verifikasi</span>
                </div>
                <div class="space-y-1.5 pt-0.5">
                    <p class="text-[11px] text-zinc-600 leading-relaxed">
                        Diverifikasi oleh tim Superadmin dalam <strong class="text-zinc-900 font-bold">10 &ndash; 30 menit</strong> pada jam kerja operasional.
                    </p>
                    <p class="text-[10px] text-zinc-400 font-medium">
                        Jam Layanan: <strong class="text-zinc-700">Senin &ndash; Minggu, 08:00 &ndash; 22:00 WIB</strong>
                    </p>
                </div>
            </div>

            <!-- 3. Aktivasi Otomatis & Bantuan -->
            <div class="p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs space-y-2.5">
                <div class="flex items-center gap-2 text-zinc-900 font-bold text-xs pb-2 border-b border-zinc-100">
                    <div class="w-7 h-7 rounded-lg bg-sky-500/10 text-sky-600 grid place-items-center shrink-0">
                        <x-ui.icon name="shield-check" class="w-4 h-4" />
                    </div>
                    <span>Aktivasi Langsung</span>
                </div>
                <div class="space-y-1.5 pt-0.5">
                    <p class="text-[11px] text-zinc-600 leading-relaxed">
                        Paket langganan barbershop otomatis aktif langsung begitu bukti transfer disetujui Superadmin.
                    </p>
                    <p class="text-[10px] text-zinc-400 font-medium">
                        Ada kendala transfer? Hubungi CS platform via WhatsApp.
                    </p>
                </div>
            </div>
        </div>

        <!-- 5. Opsi Batalkan Tagihan (HANYA TAMPIL SAAT PEMBAYARAN SUDAH DILAKUKAN / PENDING VERIFIKASI) -->
        @if($isPending)
            <div class="p-4 sm:p-4.5 rounded-2xl bg-zinc-100 border border-zinc-200/90 flex flex-col sm:flex-row items-center justify-between gap-3 text-center sm:text-left">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-white text-zinc-700 flex items-center justify-center shrink-0 border border-zinc-200 shadow-2xs">
                        <x-ui.icon name="alert-triangle" class="w-4 h-4 text-rose-500" />
                    </div>
                    <div>
                        <p class="text-xs font-bold text-zinc-900">
                            Salah memilih paket atau ingin membatalkan tagihan ini?
                        </p>
                        <p class="text-[11px] text-zinc-500 mt-0.5">
                            Pembayaran Anda sedang dalam antrean verifikasi. Batalkan jika salah nominal transfer atau ingin mengganti paket.
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    onclick="document.getElementById('modal-owner-cancel-invoice').showModal()"
                    class="w-full sm:w-auto px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 active:scale-95 text-white font-bold text-xs shadow-xs transition cursor-pointer inline-flex items-center justify-center gap-1.5 shrink-0"
                >
                    <x-ui.icon name="trash" class="w-3.5 h-3.5 text-white" />
                    <span>Batalkan Tagihan</span>
                </button>
            </div>

            <!-- Modal Dialog Pembatalan Tagihan Oleh Owner -->
            <dialog id="modal-owner-cancel-invoice" class="rounded-3xl p-0 shadow-2xl backdrop:bg-zinc-950/70 backdrop:backdrop-blur-xs w-[calc(100%-2rem)] max-w-md mx-auto my-auto border border-zinc-200 bg-white">
                <form method="POST" action="{{ route('owner.subscription.cancel-invoice', $invoice->id) }}">
                    @csrf
                    <div class="p-5 sm:p-6 space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                                <x-ui.icon name="alert-triangle" class="w-5 h-5 text-rose-600" />
                            </div>
                            <div>
                                <h3 class="font-black text-base text-zinc-900">Batalkan Tagihan Ini</h3>
                                <p class="text-xs text-zinc-500 mt-0.5">Tagihan {{ $invoice->number }} akan dibatalkan</p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label for="owner-cancellation-reason" class="block text-xs font-bold text-zinc-800">
                                Alasan Pembatalan <span class="text-rose-500">*</span>
                            </label>
                            <textarea
                                name="cancellation_reason"
                                id="owner-cancellation-reason"
                                rows="3"
                                required
                                placeholder="Tuliskan alasan pembatalan tagihan ini... (Wajib diisi)"
                                class="w-full text-xs rounded-xl border border-zinc-200 bg-white px-3 py-2.5 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 transition"
                            ></textarea>
                            <div class="flex flex-wrap gap-1.5 pt-1">
                                <button type="button" onclick="setQuickReason('Salah memilih paket langganan')" class="text-[10px] font-medium px-2 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-700 transition cursor-pointer">
                                    Salah pilih paket
                                </button>
                                <button type="button" onclick="setQuickReason('Ingin ganti metode pembayaran')" class="text-[10px] font-medium px-2 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-700 transition cursor-pointer">
                                    Ganti metode bayar
                                </button>
                                <button type="button" onclick="setQuickReason('Salah mengunggah bukti transfer')" class="text-[10px] font-medium px-2 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-700 transition cursor-pointer">
                                    Salah unggah bukti
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 bg-zinc-50 border-t border-zinc-100 flex items-center justify-end gap-2.5 rounded-b-3xl">
                        <button
                            type="button"
                            onclick="document.getElementById('modal-owner-cancel-invoice').close()"
                            class="px-4 py-2.5 rounded-xl text-xs font-bold text-zinc-600 hover:bg-zinc-200/70 transition cursor-pointer"
                        >
                            Kembali
                        </button>
                        <button
                            type="submit"
                            class="px-5 py-2.5 rounded-xl text-xs font-black bg-rose-600 hover:bg-rose-500 text-white shadow-xs transition cursor-pointer inline-flex items-center gap-1.5"
                        >
                            <x-ui.icon name="trash" class="w-3.5 h-3.5" />
                            <span>Ya, Batalkan Tagihan</span>
                        </button>
                    </div>
                </form>
            </dialog>
        @endif
    </div>

    <!-- Script Clipboard Copy & Payment Method Toggle -->
    <script>
        function copyToClipboard(text, buttonElement) {
            navigator.clipboard.writeText(text).then(function() {
                const originalHtml = buttonElement.innerHTML;
                buttonElement.innerHTML = `
                    <svg class="w-3.5 h-3.5 text-emerald-600 inline shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span class="text-emerald-700 font-extrabold text-[11px]">Tersalin!</span>
                `;
                buttonElement.classList.add('bg-emerald-50', 'border-emerald-300');

                setTimeout(function() {
                    buttonElement.innerHTML = originalHtml;
                    buttonElement.classList.remove('bg-emerald-50', 'border-emerald-300');
                }, 2000);
            }).catch(function() {
                alert('Gagal menyalin nomor rekening. Silakan salin manual: ' + text);
            });
        }

        function updateSelectedBankCard(bankName) {
            const placeholder = document.getElementById('bank-detail-placeholder');
            const allCards = document.querySelectorAll('[data-bank-detail-card]');

            if (!bankName) {
                if (placeholder) placeholder.classList.remove('hidden');
                allCards.forEach(card => card.classList.add('hidden'));
                return;
            }

            if (placeholder) placeholder.classList.add('hidden');
            allCards.forEach(card => {
                const matches = card.getAttribute('data-bank-detail-card') === bankName;
                card.classList.toggle('hidden', !matches);
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const initialInput = document.querySelector('input[name="bank_name"], select[name="bank_name"]');
            if (initialInput && initialInput.value) {
                updateSelectedBankCard(initialInput.value);
            }
        });

        document.addEventListener('change', function(e) {
            if (e.target && e.target.name === 'bank_name') {
                updateSelectedBankCard(e.target.value);
            }
        });

        document.addEventListener('ui-select:change', function(e) {
            const root = e.target;
            if (root && root.getAttribute('data-input-name') === 'bank_name') {
                updateSelectedBankCard(e.detail.value);
            }
        });

        function switchPaymentMethod(method) {
            const transferCard = document.getElementById('method-card-transfer');
            const cashCard = document.getElementById('method-card-cash');
            const bankField = document.getElementById('bank-selection-field');
            const cashField = document.getElementById('cash-instructions-field');

            if (method === 'transfer') {
                if (transferCard) {
                    transferCard.classList.add('border-amber-500', 'bg-amber-50/50');
                    transferCard.classList.remove('border-zinc-200');
                }
                if (cashCard) {
                    cashCard.classList.remove('border-amber-500', 'bg-amber-50/50');
                    cashCard.classList.add('border-zinc-200');
                }
                if (bankField) bankField.classList.remove('hidden');
                if (cashField) cashField.classList.add('hidden');
            } else {
                if (cashCard) {
                    cashCard.classList.add('border-amber-500', 'bg-amber-50/50');
                    cashCard.classList.remove('border-zinc-200');
                }
                if (transferCard) {
                    transferCard.classList.remove('border-amber-500', 'bg-amber-50/50');
                    transferCard.classList.add('border-zinc-200');
                }
                if (bankField) bankField.classList.add('hidden');
                if (cashField) cashField.classList.remove('hidden');
            }
        }

        function previewProofImage(input) {
            const errorElem = document.getElementById('client-proof-error');
            if (errorElem) {
                errorElem.textContent = '';
                errorElem.classList.add('hidden');
            }

            if (input.files && input.files[0]) {
                const file = input.files[0];
                const placeholder = document.getElementById('upload-placeholder');
                const previewBox = document.getElementById('file-preview-box');
                const previewImg = document.getElementById('preview-img-tag');
                const previewFilename = document.getElementById('preview-filename');

                // Validasi Ekstensi dan Tipe File (Hanya PNG, JPG, JPEG, WEBP, PDF)
                const allowedExts = ['png', 'jpg', 'jpeg', 'webp', 'pdf'];
                const fileExt = file.name.split('.').pop().toLowerCase();
                const allowedMimes = ['image/png', 'image/jpeg', 'image/webp', 'application/pdf'];
                const isValidType = allowedExts.includes(fileExt) || allowedMimes.includes(file.type);

                if (!isValidType) {
                    input.value = '';
                    if (errorElem) {
                        errorElem.textContent = 'Format file tidak didukung. Hanya file format PNG, JPG, JPEG, WEBP, atau PDF yang diizinkan.';
                        errorElem.classList.remove('hidden');
                    }
                    if (placeholder) placeholder.classList.remove('hidden');
                    if (previewBox) previewBox.classList.add('hidden');
                    return;
                }

                // Validasi Ukuran Maksimal 2MB (2.097.152 bytes)
                const maxBytes = 2 * 1024 * 1024;
                if (file.size > maxBytes) {
                    input.value = '';
                    const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
                    if (errorElem) {
                        errorElem.textContent = `Ukuran file (${sizeMB} MB) melebihi batas maksimal 2MB. Silakan kompres atau pilih file lain.`;
                        errorElem.classList.remove('hidden');
                    }
                    if (placeholder) placeholder.classList.remove('hidden');
                    if (previewBox) previewBox.classList.add('hidden');
                    return;
                }

                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImg.src = e.target.result;
                        previewImg.classList.remove('hidden');
                    };
                    reader.readAsDataURL(file);
                } else {
                    previewImg.classList.add('hidden');
                }

                if (placeholder) placeholder.classList.add('hidden');
                if (previewBox) previewBox.classList.remove('hidden');
                if (previewFilename) previewFilename.textContent = file.name + ' (' + Math.round(file.size / 1024) + ' KB)';
            }
        }

        function setQuickReason(text) {
            const input = document.getElementById('owner-cancellation-reason');
            if (input) {
                input.value = text;
                input.focus();
            }
        }
    </script>

    <!-- Modal Popup Kecil Info Ketentuan Masa Aktif (Simpel & Ringkas) -->
    <dialog id="modal-info-masa-aktif" class="rounded-2xl p-0 shadow-2xl backdrop:bg-zinc-950/60 backdrop:backdrop-blur-xs w-[calc(100%-2rem)] max-w-sm mx-auto my-auto border border-zinc-200 bg-white">
        <div class="p-5 space-y-3 text-center">
            <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center mx-auto">
                <x-ui.icon name="info" class="w-5 h-5 text-amber-500" />
            </div>

            <div>
                <h3 class="font-black text-sm text-zinc-900">Ketentuan Masa Aktif</h3>
                <p class="text-xs text-zinc-600 mt-1.5 leading-relaxed">
                    Pergantian paket memberikan masa aktif baru selama <strong>30 hari</strong> sejak disetujui. Sisa hari dari paket sebelumnya tidak diakumulasikan.
                </p>
            </div>
        </div>

        <div class="p-3 bg-zinc-50 border-t border-zinc-100 flex justify-end rounded-b-2xl">
            <button
                type="button"
                onclick="document.getElementById('modal-info-masa-aktif').close()"
                class="px-4 py-2 rounded-xl text-xs font-bold bg-zinc-900 text-white hover:bg-zinc-800 transition cursor-pointer"
            >
                Mengerti
            </button>
        </div>
    </dialog>
    <style>
        /* Optimasi Tampilan Mode Tablet Khusus Saat Sidebar Aktif */
        @media (max-width: 1200px) {
            html.tablet-sidebar-open .invoice-guide-grid,
            html.tablet-sidebar-open .invoice-form-grid {
                grid-template-columns: minmax(0, 1fr) !important;
            }
        }
    </style>
</x-layouts.app>
