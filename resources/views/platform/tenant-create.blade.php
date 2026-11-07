<x-layouts.app title="Daftarkan Barbershop Baru" subtitle="Buat tenant barbershop baru & akun owner" role="superadmin">
    <style>
        @media (min-width: 768px) and (max-width: 1023.98px) {
            html.tablet-sidebar-open .tenant-booking-url-preview {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 0.75rem !important;
            }
            html.tablet-sidebar-open .tenant-booking-url-preview .url-hint-text {
                text-align: left !important;
            }
            html.tablet-sidebar-open .tenant-plan-grid {
                grid-template-columns: minmax(0, 1fr) !important;
                gap: 1rem !important;
            }
            html:not(.tablet-sidebar-open) .tenant-plan-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
                gap: 0.875rem !important;
            }
            html:not(.tablet-sidebar-open) .tenant-booking-url-preview {
                flex-direction: row !important;
                align-items: center !important;
            }
        }
    </style>
    @php
        $tenantStatusOptions = [
            ['value' => 'active', 'label' => 'Aktif (Active) - Berlangganan Lunas', 'sublabel' => 'Masa aktif langsung 30 hari & terbit invoice perdana'],
            ['value' => 'trial', 'label' => 'Masa Uji Coba (Trial 14 Hari Gratis)', 'sublabel' => 'Masa evaluasi 14 hari tanpa tagihan di awal'],
        ];
    @endphp

    <div class="max-w-4xl mx-auto w-full space-y-6 pb-12">
        <!-- Breadcrumb / Tombol Kembali -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <a
                href="{{ route('platform.dashboard') }}"
                class="inline-flex items-center gap-2 text-xs font-bold text-zinc-500 hover:text-zinc-950 transition group self-start"
            >
                <span class="p-1.5 rounded-xl bg-white border border-zinc-200 group-hover:border-zinc-300 shadow-2xs transition">
                    <x-ui.icon name="arrow-left" class="w-3.5 h-3.5 text-zinc-600" />
                </span>
                <span>Kembali ke Monitoring Platform</span>
            </a>

            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/10 text-amber-700 border border-amber-500/20 font-bold text-xs">
                    <x-ui.icon name="sparkles" class="w-3.5 h-3.5 text-amber-600" />
                    <span>Registrasi Barbershop Baru</span>
                </span>
            </div>
        </div>

        <!-- Alert Error Validasi Global -->
        @if($errors->any())
            <div class="p-4 sm:p-5 rounded-2xl bg-rose-50 border border-rose-200/90 text-rose-800 text-xs shadow-2xs">
                <div class="flex items-center gap-2 font-black mb-1.5 text-sm text-rose-900">
                    <x-ui.icon name="alert-triangle" class="w-4 h-4 text-rose-600 shrink-0" />
                    <span>Terjadi kesalahan pada formulir pendaftaran:</span>
                </div>
                <ul class="list-disc pl-5 space-y-0.5 text-rose-700 font-medium">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('platform.tenants.store') }}" class="space-y-6" id="form-create-tenant">
            @csrf

            <!-- ========================================== -->
            <!-- 1. IDENTITAS & PROFIL BARBERSHOP          -->
            <!-- ========================================== -->
            <x-ui.card padding="p-5 sm:p-6">
                <x-slot:header>
                    <div class="flex items-center gap-3">
                        <span class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-600 border border-amber-500/20 flex items-center justify-center shrink-0 font-black text-sm shadow-2xs">
                            1
                        </span>
                        <div>
                            <h2 class="font-black text-base text-zinc-900 tracking-tight">Identitas &amp; Profil Barbershop</h2>
                            <p class="text-xs text-zinc-500 mt-0.5">Nama brand, alamat akses portal, kontak bisnis &amp; tema warna</p>
                        </div>
                    </div>
                </x-slot:header>

                <div class="space-y-4 pt-1">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-5">
                        <x-ui.input
                            label="Nama Barbershop *"
                            id="tenant_name"
                            name="tenant_name"
                            value="{{ old('tenant_name') }}"
                            placeholder="Contoh: Barber King Premium"
                            required
                        />

                        <div>
                            <x-ui.input
                                label="Slug URL Portal (/b/slug) *"
                                id="slug"
                                name="slug"
                                value="{{ old('slug') }}"
                                placeholder="contoh: barber-king"
                                required
                            />
                            <p class="text-[11px] text-zinc-400 mt-1.5 flex items-center gap-1">
                                <x-ui.icon name="info" class="w-3 h-3 text-zinc-400 shrink-0" />
                                <span>Hanya gunakan huruf kecil, angka, dan tanda hubung (-).</span>
                            </p>
                        </div>
                    </div>

                    <!-- Live URL Preview Bar (Responsif di Semua Mode) -->
                    <div class="tenant-booking-url-preview p-3.5 sm:p-4 rounded-2xl bg-zinc-50 border border-zinc-200/80 flex flex-col lg:flex-row lg:items-center justify-between gap-3 transition-all duration-300">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <span class="w-8 h-8 rounded-xl bg-white border border-zinc-200/90 text-amber-600 flex items-center justify-center shrink-0 shadow-2xs">
                                <x-ui.icon name="globe" class="w-4 h-4 text-amber-600" />
                            </span>
                            <div class="min-w-0 flex-1 text-xs">
                                <span class="text-zinc-500 font-semibold block sm:inline">Link Booking Online Publik:</span>
                                <div class="font-mono font-bold text-zinc-900 truncate mt-0.5">
                                    {{ rtrim(url('/b'), '/') }}/<span id="slug-preview" class="text-amber-700 bg-amber-50 px-2 py-0.5 rounded-lg border border-amber-200/80">{{ old('slug', 'slug-barbershop') }}</span>
                                </div>
                            </div>
                        </div>
                        <span class="url-hint-text text-[11px] text-zinc-500 font-medium shrink-0 leading-relaxed">
                            Akses booking pelanggan: <code class="font-mono font-bold text-zinc-700 bg-zinc-200/70 px-1.5 py-0.5 rounded-md text-[10px]">/b/[slug]</code>
                        </span>
                    </div>

                    <!-- Kontak Bisnis Barbershop (No. Telepon & Email) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-5 pt-3 border-t border-zinc-100">
                        <x-ui.input
                            label="Nomor Telepon Barbershop (Opsional)"
                            name="phone"
                            value="{{ old('phone') }}"
                            placeholder="Contoh: 021-7890123 / 08..."
                        />
                        <x-ui.input
                            label="Email Bisnis Barbershop (Opsional)"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            placeholder="Contoh: contact@barberking.id"
                        />
                    </div>

                    <!-- Warna Brand Utama & Alamat Pusat -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-5 items-start pt-3 border-t border-zinc-100">
                        <div>
                            <label class="block text-xs font-bold text-zinc-700 mb-1.5">Warna Brand Utama</label>
                            <div class="flex items-center gap-2.5">
                                <div class="relative w-10 h-10 rounded-xl border border-zinc-200 shadow-2xs overflow-hidden shrink-0 flex items-center justify-center p-0.5 bg-white">
                                    <input
                                        type="color"
                                        id="brand-color-input"
                                        name="brand_color"
                                        value="{{ old('brand_color', '#D97706') }}"
                                        class="absolute inset-0 w-[150%] h-[150%] -top-1/4 -left-1/4 cursor-pointer border-0"
                                        oninput="document.getElementById('brand-color-val').textContent = this.value"
                                    />
                                </div>
                                <div class="min-w-0">
                                    <span id="brand-color-val" class="text-xs font-mono font-bold text-zinc-800">{{ old('brand_color', '#D97706') }}</span>
                                    <p class="text-[10px] text-zinc-400">Aksen tema portal &amp; booking online</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-zinc-700 mb-1">Alamat Kantor / Cabang Pusat (Opsional)</label>
                            <textarea
                                name="address"
                                rows="2"
                                class="w-full text-xs font-semibold p-2.5 rounded-xl border border-zinc-200 focus:outline-none focus:ring-2 focus:ring-amber-500/25 focus:border-amber-500 bg-zinc-50"
                                placeholder="Contoh: Jl. Jend. Sudirman No. 45, Jakarta Selatan"
                            >{{ old('address') }}</textarea>
                        </div>
                    </div>
                </div>
            </x-ui.card>

            <!-- ========================================== -->
            <!-- 2. AKUN OWNER / PEMILIK                   -->
            <!-- ========================================== -->
            <x-ui.card padding="p-5 sm:p-6">
                <x-slot:header>
                    <div class="flex items-center gap-3">
                        <span class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-600 border border-amber-500/20 flex items-center justify-center shrink-0 font-black text-sm shadow-2xs">
                            2
                        </span>
                        <div>
                            <h2 class="font-black text-base text-zinc-900 tracking-tight">Akun Owner / Pemilik</h2>
                            <p class="text-xs text-zinc-500 mt-0.5">Kredensial login owner untuk mengelola operasional barbershop</p>
                        </div>
                    </div>
                </x-slot:header>

                <div class="space-y-4 pt-1">
                    <x-ui.input
                        label="Nama Lengkap Owner *"
                        name="owner_name"
                        value="{{ old('owner_name') }}"
                        placeholder="Contoh: Anton Hartono"
                        required
                    />

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-5">
                        <x-ui.input
                            label="Email Login *"
                            name="owner_email"
                            type="email"
                            value="{{ old('owner_email') }}"
                            placeholder="owner@barberking.id"
                            required
                        />

                        <x-ui.input
                            label="No HP / WhatsApp *"
                            name="owner_phone"
                            value="{{ old('owner_phone') }}"
                            placeholder="081234567890"
                            required
                        />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-5 pt-3 border-t border-zinc-100">
                        <div>
                            <x-ui.input
                                label="Password Akun *"
                                id="password"
                                name="password"
                                type="password"
                                placeholder="Minimal 8 karakter"
                                required
                            />
                            <p class="text-[11px] text-zinc-400 mt-1.5 flex items-center gap-1">
                                <x-ui.icon name="info" class="w-3 h-3 text-zinc-400 shrink-0" />
                                <span>Minimal 8 karakter untuk keamanan akun.</span>
                            </p>
                        </div>

                        <div>
                            <x-ui.input
                                label="Konfirmasi Password *"
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                placeholder="Ulangi password di atas"
                                required
                            />
                            <p class="text-[11px] text-zinc-400 mt-1.5 flex items-center gap-1">
                                <x-ui.icon name="check" class="w-3 h-3 text-zinc-400 shrink-0" />
                                <span>Pastikan password cocok.</span>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2.5 p-3 rounded-xl bg-amber-500/5 border border-amber-500/15 text-zinc-700 text-xs">
                        <x-ui.icon name="shield-check" class="w-4 h-4 text-amber-600 shrink-0" />
                        <span>Kredensial di atas langsung aktif dan dapat digunakan owner untuk masuk ke portal manajemen.</span>
                    </div>
                </div>
            </x-ui.card>

            <!-- ========================================== -->
            <!-- 3. PAKET & OUTLET PERDANA                 -->
            <!-- ========================================== -->
            <x-ui.card padding="p-5 sm:p-6">
                <x-slot:header>
                    <div class="flex items-center gap-3">
                        <span class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-600 border border-amber-500/20 flex items-center justify-center shrink-0 font-black text-sm shadow-2xs">
                            3
                        </span>
                        <div>
                            <h2 class="font-black text-base text-zinc-900 tracking-tight">Paket &amp; Outlet Perdana</h2>
                            <p class="text-xs text-zinc-500 mt-0.5">Penentuan paket SaaS &amp; cabang awal barbershop</p>
                        </div>
                    </div>
                </x-slot:header>

                <div class="space-y-6 pt-1">
                    <!-- 3A. Status Operasional Awal -->
                    <div class="space-y-3">
                        <x-ui.select
                            label="Status Operasional Awal Barbershop *"
                            name="status"
                            icon="shield-check"
                            size="md"
                            :value="old('status', 'active')"
                            :options="$tenantStatusOptions"
                        />

                        <!-- Notice info dinamis sesuai status -->
                        <div id="notice-status-active" class="flex items-center gap-2.5 p-3 rounded-xl bg-emerald-50 border border-emerald-200/80 text-emerald-900 text-xs {{ old('status', 'active') === 'trial' ? 'hidden' : '' }}">
                            <x-ui.icon name="shield-check" class="w-4 h-4 text-emerald-600 shrink-0" />
                            <span><strong>Administrasi Langganan Perdana:</strong> Barbershop langsung aktif dengan status <strong>Lunas (30 hari)</strong> dan sistem otomatis menerbitkan invoice pembayaran perdana.</span>
                        </div>

                        <div id="notice-status-trial" class="flex items-center gap-2.5 p-3 rounded-xl bg-amber-50 border border-amber-200/80 text-amber-900 text-xs {{ old('status', 'active') === 'trial' ? '' : 'hidden' }}">
                            <x-ui.icon name="clock" class="w-4 h-4 text-amber-600 shrink-0" />
                            <span><strong>Masa Uji Coba (Trial 14 Hari):</strong> Barbershop aktif dalam mode evaluasi gratis selama 14 hari tanpa menerbitkan invoice pembayaran di awal. Operasional akan terkunci setelah 14 hari jika belum berlangganan.</span>
                        </div>
                    </div>

                    <!-- 3B. Paket Langganan (Responsif & Longgar di Tablet) -->
                    <div>
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 sm:gap-2 mb-3">
                            <label class="block text-xs font-bold text-zinc-800">
                                Pilih Paket Langganan <span class="text-rose-500">*</span>
                            </label>
                            <span id="plan-selection-hint" class="text-[11px] text-zinc-400 font-medium">Klik salah satu paket untuk memilih</span>
                        </div>

                        <div class="tenant-plan-grid grid grid-cols-1 lg:grid-cols-3 gap-3.5 sm:gap-4 transition-all duration-300">
                            @foreach($plans as $plan)
                                @php
                                    $isSelected = (string) old('plan_id', $plans->first()?->id) === (string) $plan->id;
                                @endphp
                                <label
                                    class="plan-card relative flex flex-col justify-between p-4 rounded-2xl border-2 transition cursor-pointer select-none {{ $isSelected ? 'border-amber-500 bg-amber-500/5 ring-4 ring-amber-500/10' : 'border-zinc-200/80 bg-white hover:border-zinc-300 hover:bg-zinc-50/50' }}"
                                    data-plan-id="{{ $plan->id }}"
                                    data-plan-code="{{ $plan->code }}"
                                >
                                    <input
                                        type="radio"
                                        name="plan_id"
                                        value="{{ $plan->id }}"
                                        class="sr-only plan-radio"
                                        {{ $isSelected ? 'checked' : '' }}
                                        required
                                    />

                                    <div>
                                        <div class="flex items-center justify-between gap-2 mb-2">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="font-black text-sm text-zinc-900">{{ $plan->name }}</span>
                                                @if($plan->code === 'starter')
                                                    <span class="trial-plan-badge hidden px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-amber-500/20 text-amber-800 border border-amber-500/30 shrink-0">
                                                        Default Trial
                                                    </span>
                                                @else
                                                    <span class="trial-plan-lock hidden px-1.5 py-0.5 rounded text-[9px] font-bold text-zinc-400 bg-zinc-100 border border-zinc-200 shrink-0 flex items-center gap-1">
                                                        <x-ui.icon name="lock" class="w-2.5 h-2.5" />
                                                        <span>Berbayar</span>
                                                    </span>
                                                @endif
                                            </div>
                                            <span class="plan-check-badge w-5 h-5 rounded-full {{ $isSelected ? 'bg-amber-500 text-zinc-950' : 'bg-zinc-100 text-transparent' }} flex items-center justify-center shrink-0 transition">
                                                <x-ui.icon name="check" class="w-3 h-3 stroke-[3]" />
                                            </span>
                                        </div>

                                        <div class="mb-2.5">
                                            <span class="text-lg font-black text-zinc-900 tracking-tight">
                                                Rp{{ number_format($plan->price_monthly, 0, ',', '.') }}
                                            </span>
                                            <span class="text-xs text-zinc-400 font-medium">/bln</span>
                                        </div>

                                        <p class="text-[11px] text-zinc-500 leading-relaxed mb-3 line-clamp-2">
                                            {{ $plan->description }}
                                        </p>
                                    </div>

                                    <div class="pt-2.5 border-t border-zinc-100 flex items-center gap-1.5 flex-wrap text-[10px] font-bold text-zinc-600">
                                        <span class="px-2 py-0.5 rounded-md bg-zinc-100 border border-zinc-200/60">
                                            Maks {{ $plan->max_branches }} Cabang
                                        </span>
                                        <span class="px-2 py-0.5 rounded-md bg-zinc-100 border border-zinc-200/60">
                                            {{ $plan->max_staff ? 'Maks ' . $plan->max_staff . ' Staff' : 'Unlimited Staff' }}
                                        </span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        @error('plan_id')
                            <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>


                </div>
            </x-ui.card>

            <!-- ========================================== -->
            <!-- TOMBOL AKSI SIMPAN & BATAL                -->
            <!-- ========================================== -->
            <div class="p-4 sm:p-5 rounded-2xl bg-white border border-zinc-200/90 shadow-2xs space-y-4">
                <!-- Info Keterangan Aksi Footer -->
                <div class="flex items-start sm:items-center gap-2.5 p-3 rounded-xl bg-amber-500/5 border border-amber-500/15 text-xs text-zinc-700">
                    <x-ui.icon name="info" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5 sm:mt-0" />
                    <span id="footer-notice-text" class="leading-relaxed font-medium">
                        {{ old('status', 'active') === 'trial' ? 'Barbershop akan didaftarkan dalam masa uji coba (trial) gratis selama 14 hari dengan Paket Starter.' : 'Pastikan pembayaran administrasi perdana telah diverifikasi sebelum akun diaktifkan.' }}
                    </span>
                </div>

                <!-- Tombol Batal & Submit -->
                <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2.5 sm:gap-3">
                    <a
                        href="{{ route('platform.dashboard') }}"
                        class="w-full sm:w-auto px-5 py-2.5 rounded-xl border border-zinc-200 bg-white hover:bg-zinc-100 active:scale-[0.99] text-zinc-700 font-bold text-xs sm:text-sm text-center transition cursor-pointer min-h-[44px] flex items-center justify-center shadow-2xs"
                    >
                        Batal
                    </a>

                    <button
                        type="submit"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 active:scale-[0.99] text-zinc-950 font-black text-xs sm:text-sm shadow-md shadow-amber-500/20 transition cursor-pointer min-h-[44px]"
                    >
                        <x-ui.icon name="check" class="w-4 h-4 stroke-[2.5]" />
                        <span id="submit-btn-text">{{ old('status', 'active') === 'trial' ? 'Buat & Mulai Masa Trial (14 Hari)' : 'Buat & Aktifkan Barbershop' }}</span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Script Pembantu: Auto-slug & Interaksi Kartu Paket & Status Operasional -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tenantNameInput = document.getElementById('tenant_name');
            const slugInput = document.getElementById('slug');
            const slugPreview = document.getElementById('slug-preview');
            let isSlugManuallyEdited = false;

            function slugify(text) {
                return text
                    .toString()
                    .toLowerCase()
                    .trim()
                    .replace(/\s+/g, '-')
                    .replace(/[^\w\-]+/g, '')
                    .replace(/\-\-+/g, '-')
                    .replace(/^-+/, '')
                    .replace(/-+$/, '');
            }

            if (slugInput && slugInput.value.trim() !== '') {
                isSlugManuallyEdited = true;
            }

            if (tenantNameInput && slugInput) {
                tenantNameInput.addEventListener('input', function () {
                    if (!isSlugManuallyEdited) {
                        const generated = slugify(this.value);
                        slugInput.value = generated;
                        if (slugPreview) {
                            slugPreview.textContent = generated || 'slug-barbershop';
                        }
                    }
                });

                slugInput.addEventListener('input', function () {
                    isSlugManuallyEdited = this.value.trim() !== '';
                    if (slugPreview) {
                        slugPreview.textContent = this.value.trim() || 'slug-barbershop';
                    }
                });
            }

            // Radio Card Selector untuk Paket Langganan
            const planCards = document.querySelectorAll('.plan-card');
            planCards.forEach(card => {
                const radio = card.querySelector('.plan-radio');

                card.addEventListener('click', function () {
                    if (card.classList.contains('pointer-events-none') || (radio && radio.disabled)) {
                        return;
                    }

                    planCards.forEach(c => {
                        c.classList.remove('border-amber-500', 'bg-amber-500/5', 'ring-4', 'ring-amber-500/10');
                        c.classList.add('border-zinc-200/80', 'bg-white');
                        const badge = c.querySelector('.plan-check-badge');
                        if (badge) {
                            badge.classList.remove('bg-amber-500', 'text-zinc-950');
                            badge.classList.add('bg-zinc-100', 'text-transparent');
                        }
                    });

                    card.classList.remove('border-zinc-200/80', 'bg-white');
                    card.classList.add('border-amber-500', 'bg-amber-500/5', 'ring-4', 'ring-amber-500/10');
                    const badge = card.querySelector('.plan-check-badge');
                    if (badge) {
                        badge.classList.remove('bg-zinc-100', 'text-transparent');
                        badge.classList.add('bg-amber-500', 'text-zinc-950');
                    }

                    if (radio) {
                        radio.checked = true;
                    }
                });
            });

            // Toggle Info Status Operasional (Active vs Trial) & Kunci Paket ke Starter untuk Trial
            function updateStatusUI(statusVal) {
                const noticeActive = document.getElementById('notice-status-active');
                const noticeTrial = document.getElementById('notice-status-trial');
                const footerNotice = document.getElementById('footer-notice-text');
                const submitText = document.getElementById('submit-btn-text');
                const planHint = document.getElementById('plan-selection-hint');

                if (statusVal === 'trial') {
                    if (noticeActive) noticeActive.classList.add('hidden');
                    if (noticeTrial) noticeTrial.classList.remove('hidden');
                    if (footerNotice) footerNotice.textContent = 'Barbershop akan didaftarkan dalam masa uji coba (trial) gratis selama 14 hari dengan Paket Starter.';
                    if (submitText) submitText.textContent = 'Buat & Mulai Masa Trial (14 Hari)';
                    if (planHint) planHint.textContent = 'Trial otomatis terkunci pada Paket Starter (1 cabang & 5 staf)';

                    // Kunci pilihan paket hanya ke Starter
                    planCards.forEach(card => {
                        const code = card.getAttribute('data-plan-code');
                        const radio = card.querySelector('.plan-radio');
                        const trialBadge = card.querySelector('.trial-plan-badge');
                        const lockBadge = card.querySelector('.trial-plan-lock');

                        if (code === 'starter') {
                            card.classList.add('border-amber-500', 'bg-amber-500/5', 'ring-4', 'ring-amber-500/10');
                            card.classList.remove('border-zinc-200/80', 'bg-white', 'opacity-50', 'pointer-events-none', 'cursor-not-allowed', 'bg-zinc-50/70');
                            const badge = card.querySelector('.plan-check-badge');
                            if (badge) {
                                badge.classList.remove('bg-zinc-100', 'text-transparent');
                                badge.classList.add('bg-amber-500', 'text-zinc-950');
                            }
                            if (radio) {
                                radio.checked = true;
                                radio.disabled = false;
                            }
                            if (trialBadge) trialBadge.classList.remove('hidden');
                        } else {
                            card.classList.remove('border-amber-500', 'bg-amber-500/5', 'ring-4', 'ring-amber-500/10', 'bg-white');
                            card.classList.add('border-zinc-200/80', 'bg-zinc-50/70', 'opacity-50', 'pointer-events-none', 'cursor-not-allowed');
                            const badge = card.querySelector('.plan-check-badge');
                            if (badge) {
                                badge.classList.remove('bg-amber-500', 'text-zinc-950');
                                badge.classList.add('bg-zinc-100', 'text-transparent');
                            }
                            if (radio) {
                                radio.checked = false;
                                radio.disabled = true;
                            }
                            if (lockBadge) lockBadge.classList.remove('hidden');
                        }
                    });
                } else {
                    if (noticeActive) noticeActive.classList.remove('hidden');
                    if (noticeTrial) noticeTrial.classList.add('hidden');
                    if (footerNotice) footerNotice.textContent = 'Pastikan pembayaran administrasi perdana telah diverifikasi sebelum akun diaktifkan.';
                    if (submitText) submitText.textContent = 'Buat & Aktifkan Barbershop';
                    if (planHint) planHint.textContent = 'Klik untuk memilih paket';

                    // Buka kunci semua pilihan paket
                    planCards.forEach(card => {
                        const radio = card.querySelector('.plan-radio');
                        const trialBadge = card.querySelector('.trial-plan-badge');
                        const lockBadge = card.querySelector('.trial-plan-lock');

                        card.classList.remove('opacity-50', 'pointer-events-none', 'cursor-not-allowed', 'bg-zinc-50/70');
                        if (radio) {
                            radio.disabled = false;
                        }
                        if (trialBadge) trialBadge.classList.add('hidden');
                        if (lockBadge) lockBadge.classList.add('hidden');
                    });
                }
            }

            document.addEventListener('change', function (e) {
                if (e.target && e.target.name === 'status') {
                    updateStatusUI(e.target.value);
                }
            });

            document.addEventListener('ui-select:change', function (e) {
                if (e.detail && (e.detail.value === 'active' || e.detail.value === 'trial')) {
                    updateStatusUI(e.detail.value);
                }
            });

            // Jalankan inisialisasi status awal saat load
            const initialStatusInput = document.querySelector('input[name="status"]');
            if (initialStatusInput && initialStatusInput.value === 'trial') {
                updateStatusUI('trial');
            }
        });
    </script>
</x-layouts.app>
