<x-layouts.customer title="Selesai & Pembayaran" :subtitle="$ticket->tenant?->name ?? 'Rincian Pembayaran'" :tenant="$tenant">
    <div class="space-y-4">
        <!-- Header Info Tiket -->
        <div class="rounded-2xl bg-zinc-950 text-white p-4 shadow-lg border border-zinc-800">
            <div class="flex items-center justify-between gap-2">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-500/20 text-amber-400 text-xs font-extrabold">
                    <x-ui.icon name="scissors" class="w-3.5 h-3.5" />
                    <span>{{ $ticket->tenant?->name }}</span>
                </span>
                <span class="px-2 py-0.5 rounded bg-zinc-800 text-amber-400 font-extrabold text-xs num">
                    Tiket {{ $ticket->number }}
                </span>
            </div>

            <h1 class="text-lg font-black tracking-tight mt-3 text-white">
                {{ $ticket->status === \App\Enums\QueueStatus::Completed ? 'Konfirmasi Selesai & Pembayaran' : 'Pembayaran di Muka Antrean' }}
            </h1>
            <p class="text-xs text-zinc-400 mt-0.5">
                {{ $branch?->name }} · {{ $ticket->status === \App\Enums\QueueStatus::Completed ? 'Layanan potong rambut Anda telah selesai dikerjakan.' : 'Selesaikan pembayaran terlebih dahulu saat antre sebelum potong rambut dimulai.' }}
            </p>
        </div>

        @if($errors->any())
            <div class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-xs font-semibold text-rose-800 space-y-1">
                @foreach($errors->all() as $err)
                    <p class="flex items-center gap-1.5">
                        <x-ui.icon name="x" class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                        <span>{{ $err }}</span>
                    </p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('customer.tickets.process-checkout', $ticket->id) }}" class="space-y-4">
            @csrf

            <!-- BAGIAN 1: PENILAIAN KEPADA STAFF (AKTIF JIKA SUDAH SELESAI DICUKUR) -->
            @if($ticket->status === \App\Enums\QueueStatus::Completed)
            <x-ui.card>
                <x-slot:header>
                    <h2 class="font-extrabold text-xs uppercase tracking-wider text-zinc-800 flex items-center gap-2">
                        <x-ui.icon name="star" class="w-4 h-4 text-amber-500" />
                        <span>1. Berikan Penilaian untuk Staff / Barber</span>
                    </h2>
                </x-slot:header>

                <div class="space-y-3">
                    <!-- Barber Info Mini -->
                    <div class="flex items-center gap-3 p-3 rounded-2xl bg-zinc-50 border border-zinc-200/80">
                        <div class="w-11 h-11 rounded-2xl bg-zinc-950 text-amber-400 font-black text-sm grid place-items-center shrink-0">
                            {{ strtoupper(substr($barber?->display_name ?? 'S', 0, 1)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-extrabold text-xs text-zinc-900 truncate">{{ $barber?->display_name ?? 'Staff Barbershop' }}</h3>
                            <p class="text-[11px] text-zinc-500 truncate">{{ $barber?->specialty ?? 'Barber yang melayani Anda' }}</p>
                        </div>
                    </div>

                    <!-- Bintang Rating Interaktif -->
                    <div>
                        <label class="block text-xs font-bold text-zinc-700 mb-1 text-center">
                            Bagaimana pelayanan dari {{ $barber?->display_name ?? 'staff kami' }}?
                        </label>

                        <input type="hidden" name="rating" id="rating_input" value="{{ old('rating', 5) }}" required />

                        <div class="flex items-center justify-center gap-2 py-2" id="star_container">
                            @for($i = 1; $i <= 5; $i++)
                                <button
                                    type="button"
                                    data-star-btn="{{ $i }}"
                                    class="w-11 h-11 rounded-2xl flex items-center justify-center transition-all transform hover:scale-115 active:scale-95 cursor-pointer focus:outline-none"
                                    aria-label="Rating {{ $i }} Bintang"
                                >
                                    <svg class="w-8 h-8 text-amber-400 fill-amber-400 drop-shadow-sm transition-colors duration-150" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                                    </svg>
                                </button>
                            @endfor
                        </div>

                        <p class="text-xs font-extrabold text-center text-amber-700 mt-1 flex items-center justify-center gap-1.5" id="rating_label">
                            5 / 5 · Sangat Puas!
                        </p>
                    </div>

                    <!-- Ulasan / Catatan Pengalaman -->
                    <div>
                        <label for="comment" class="block text-xs font-semibold text-zinc-700 mb-1">
                            Ulasan / Komentar (Opsional)
                        </label>
                        <textarea
                            id="comment"
                            name="comment"
                            rows="2"
                            placeholder="Bagikan pengalaman potong rambut, kerapian hasil, atau keramahan staff..."
                            class="w-full rounded-xl border border-zinc-200 bg-white p-3 text-xs text-zinc-900 placeholder:text-zinc-400 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                        >{{ old('comment') }}</textarea>
                    </div>
                </div>
            </x-ui.card>
            @else
            <div class="p-3.5 rounded-2xl bg-amber-500/10 border border-amber-300/80 text-xs text-amber-950 flex items-center gap-2.5 shadow-2xs">
                <x-ui.icon name="star" class="w-4 h-4 text-amber-600 shrink-0" />
                <span class="leading-relaxed">
                    <b>Catatan Penilaian:</b> Form penilaian keramahan &amp; hasil cukur barber dapat Anda berikan setelah pangkas rambut Anda selesai dikerjakan.
                </span>
            </div>
            @endif

            <!-- BAGIAN 2: RINCIAN BIAYA & TOTAL -->
            <x-ui.card>
                <x-slot:header>
                    <h2 class="font-extrabold text-xs uppercase tracking-wider text-zinc-800 flex items-center gap-2">
                        <x-ui.icon name="file-text" class="w-4 h-4 text-amber-600" />
                        <span>Rincian Biaya Layanan</span>
                    </h2>
                </x-slot:header>

                <div class="space-y-2 text-xs">
                    @foreach($ticket->items as $item)
                        <div class="flex items-center justify-between text-zinc-700 py-1">
                            <span class="font-medium truncate">{{ $item->service_name }}</span>
                            <span class="font-bold text-zinc-900 num shrink-0">Rp{{ number_format($item->price, 0, ',', '.') }}</span>
                        </div>
                    @endforeach

                    @if($ticket->hairColor)
                        <div class="flex items-center justify-between text-zinc-700 py-1">
                            <span class="font-medium">Warna: {{ $ticket->hairColor->name }}</span>
                            <span class="font-semibold text-zinc-500 text-[11px]">(Sudah Termasuk)</span>
                        </div>
                    @endif

                    <div class="pt-2.5 mt-2 border-t border-zinc-100 flex items-center justify-between">
                        <span class="font-bold text-zinc-700 text-sm">Total Tagihan:</span>
                        <span class="font-black text-xl text-zinc-950 num">
                            Rp{{ number_format($totalPrice, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </x-ui.card>

            <!-- BAGIAN 3: PILIH METODE PEMBAYARAN -->
            <x-ui.card>
                <x-slot:header>
                    <h2 class="font-extrabold text-xs uppercase tracking-wider text-zinc-800 flex items-center gap-2">
                        <x-ui.icon name="banknote" class="w-4 h-4 text-emerald-600" />
                        <span>2. Pilih Metode Pembayaran</span>
                    </h2>
                </x-slot:header>

                <div class="space-y-3" id="payment_method_group">
                    <!-- Opsi QRIS -->
                    <label class="payment-card flex items-center justify-between p-3.5 rounded-2xl border-2 border-amber-500 bg-amber-50/40 cursor-pointer transition">
                        <div class="flex items-center gap-3">
                            <input type="radio" name="payment_method" value="qris" checked class="w-4 h-4 text-amber-600 focus:ring-amber-500" />
                            <div>
                                <span class="block font-extrabold text-xs text-zinc-950">QRIS (Scan di Meja Kasir)</span>
                                <span class="block text-[11px] text-zinc-500 mt-0.5">BCA, Mandiri, BRI, GoPay, OVO, Dana, ShopeePay</span>
                            </div>
                        </div>
                        <span class="px-2 py-0.5 rounded-md bg-amber-500 text-zinc-950 text-[10px] font-black uppercase">
                            Digital
                        </span>
                    </label>

                    <!-- Keterangan QRIS Meja Kasir -->
                    <div id="qris_details_box" class="p-3.5 rounded-2xl bg-amber-50/60 border border-amber-200/80 text-xs text-zinc-700 space-y-1.5">
                        <div class="flex items-start gap-2.5">
                            <div class="w-6 h-6 rounded-lg bg-amber-500/20 text-amber-800 grid place-items-center shrink-0 mt-0.5">
                                <x-ui.icon name="scissors" class="w-3.5 h-3.5" />
                            </div>
                            <div>
                                <p class="font-bold text-zinc-900">Scan QRIS di Meja Kasir</p>
                                <p class="text-[11px] text-zinc-500 mt-0.5 leading-relaxed">
                                    Silakan scan stiker QRIS yang tersedia di meja kasir dengan m-Banking / E-Wallet dengan nominal pas <b class="text-zinc-950 num">Rp{{ number_format($totalPrice, 0, ',', '.') }}</b>.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Opsi Tunai (Cash di Kasir) -->
                    <label class="payment-card flex items-center justify-between p-3.5 rounded-2xl border-2 border-zinc-200 bg-white hover:border-zinc-300 cursor-pointer transition">
                        <div class="flex items-center gap-3">
                            <input type="radio" name="payment_method" value="cash" class="w-4 h-4 text-amber-600 focus:ring-amber-500" />
                            <div>
                                <span class="block font-extrabold text-xs text-zinc-900">Tunai di Kasir</span>
                                <span class="block text-[11px] text-zinc-500 mt-0.5">Serahkan uang tunai langsung ke kasir</span>
                            </div>
                        </div>
                        <x-ui.icon name="banknote" class="w-4 h-4 text-zinc-400" />
                    </label>

                    <!-- Keterangan Tunai -->
                    <div id="cash_details_box" class="hidden p-3.5 rounded-2xl bg-zinc-50 border border-zinc-200/80 text-xs text-zinc-700 space-y-1.5">
                        <div class="flex items-start gap-2.5">
                            <div class="w-6 h-6 rounded-lg bg-zinc-200 text-zinc-800 grid place-items-center shrink-0 mt-0.5">
                                <x-ui.icon name="banknote" class="w-3.5 h-3.5" />
                            </div>
                            <div>
                                <p class="font-bold text-zinc-900">Bayar di Meja Kasir</p>
                                <p class="text-[11px] text-zinc-500 mt-0.5 leading-relaxed">
                                    Serahkan uang tunai sebesar <b class="text-zinc-950 num">Rp{{ number_format($totalPrice, 0, ',', '.') }}</b> langsung ke kasir.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </x-ui.card>

            <!-- BAGIAN 4: TOMBOL KONFIRMASI PEMBAYARAN -->
            <div class="pt-2 space-y-2">
                <x-ui.button
                    type="submit"
                    size="lg"
                    class="w-full bg-amber-500 hover:bg-amber-400 text-zinc-950 font-black shadow-xl py-3.5 text-base flex items-center justify-center gap-2"
                >
                    <x-ui.icon name="check" class="w-5 h-5" />
                    <span>Lanjut &amp; Tunggu Invoice Kasir</span>
                </x-ui.button>

                <a
                    href="{{ route('customer.tickets.show', $ticket->id) }}"
                    class="w-full min-h-[44px] px-4 py-2.5 rounded-xl border border-zinc-200 text-zinc-700 bg-white hover:bg-zinc-50 text-xs font-bold flex items-center justify-center gap-2 transition"
                >
                    <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" />
                    <span>Kembali ke Detail Antrean</span>
                </a>
            </div>
        </form>
    </div>

    <!-- Script Interaktif Star Rating & Seleksi Metode Pembayaran -->
    <script>
        (function () {
            // Logika Rating Bintang
            const ratingInput = document.getElementById('rating_input');
            const ratingLabel = document.getElementById('rating_label');
            const starButtons = document.querySelectorAll('[data-star-btn]');

            const labels = {
                1: '1 / 5 · Sangat Kecewa',
                2: '2 / 5 · Kurang Puas',
                3: '3 / 5 · Cukup Baik',
                4: '4 / 5 · Puas!',
                5: '5 / 5 · Sangat Puas!'
            };

            function renderStarsVisual(val) {
                starButtons.forEach(btn => {
                    const btnVal = parseInt(btn.getAttribute('data-star-btn'), 10);
                    const svg = btn.querySelector('svg');
                    if (svg) {
                        if (btnVal <= val) {
                            svg.setAttribute('class', 'w-8 h-8 text-amber-400 fill-amber-400 drop-shadow-sm transition-colors duration-150');
                        } else {
                            svg.setAttribute('class', 'w-8 h-8 text-zinc-300 fill-zinc-100 transition-colors duration-150');
                        }
                    }
                });
            }

            function updateStars(val) {
                if (ratingInput) {
                    ratingInput.value = val;
                }
                if (ratingLabel && labels[val]) {
                    ratingLabel.textContent = labels[val];
                }
                renderStarsVisual(val);
            }

            const starContainer = document.getElementById('star_container');
            if (starContainer && ratingInput) {
                starContainer.addEventListener('mouseleave', () => {
                    const currentVal = parseInt(ratingInput.value || '5', 10);
                    renderStarsVisual(currentVal);
                });
            }

            starButtons.forEach(btn => {
                const val = parseInt(btn.getAttribute('data-star-btn'), 10);
                btn.addEventListener('mouseenter', () => {
                    renderStarsVisual(val);
                });
                btn.addEventListener('click', () => {
                    updateStars(val);
                });
            });

            // Inisialisasi default bintang 5 jika form penilaian aktif
            if (ratingInput) {
                updateStars(parseInt(ratingInput.value || '5', 10));
            }

            // Logika Kartu Metode Pembayaran
            const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
            const qrisBox = document.getElementById('qris_details_box');
            const cashBox = document.getElementById('cash_details_box');

            function syncPaymentUI() {
                const selected = document.querySelector('input[name="payment_method"]:checked')?.value;
                document.querySelectorAll('.payment-card').forEach(card => {
                    const r = card.querySelector('input[type="radio"]');
                    if (r && r.checked) {
                        card.classList.remove('border-zinc-200', 'bg-white');
                        card.classList.add('border-amber-500', 'bg-amber-50/40');
                    } else {
                        card.classList.remove('border-amber-500', 'bg-amber-50/40');
                        card.classList.add('border-zinc-200', 'bg-white');
                    }
                });

                if (selected === 'qris') {
                    qrisBox?.classList.remove('hidden');
                    cashBox?.classList.add('hidden');
                } else {
                    qrisBox?.classList.add('hidden');
                    cashBox?.classList.remove('hidden');
                }
            }

            paymentRadios.forEach(radio => {
                radio.addEventListener('change', syncPaymentUI);
            });
            syncPaymentUI();
        })();
    </script>
</x-layouts.customer>
