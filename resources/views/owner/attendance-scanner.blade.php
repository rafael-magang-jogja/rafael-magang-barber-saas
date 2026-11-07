<x-layouts.app title="Scan Absen Karyawan" subtitle="Pindai QR code absensi dari layar HP staf (Barber & Kasir) untuk pencatatan Masuk & Pulang" role="owner">
    <!-- Tab Navigasi Manajemen Jadwal & Absensi -->
    <x-owner.schedule-tabs active="scanner" />

    <x-ui.flash-alert />

    <div class="space-y-6">
        <!-- Baris Atas: Pilihan Cabang Outlet & Jam Live Realtime -->
        <div class="p-4 sm:p-5 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-2xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                    <x-ui.icon name="camera" class="w-6 h-6 stroke-[2.2]" />
                </div>
                <div>
                    <h2 class="font-black text-sm sm:text-base text-zinc-950">Pemindai QR Absensi Karyawan</h2>
                    <p class="text-xs text-zinc-500">Minta staf membuka menu Absensi di HP mereka, lalu arahkan kamera ke kode QR staf</p>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-3 shrink-0">
                <!-- Dropdown Cabang -->
                @if($branches->count() > 1)
                    @php
                        $scannerBranchOptions = $branches->map(fn ($b) => [
                            'value' => (string) $b->id,
                            'label' => $b->name,
                            'sublabel' => $b->code,
                        ])->all();
                    @endphp
                    <form method="GET" action="{{ route('owner.attendances.scanner') }}" class="w-full sm:w-56 shrink-0">
                        <x-ui.select
                            name="branch_id"
                            icon="store"
                            :options="$scannerBranchOptions"
                            :value="(string) ($selectedBranch?->id ?? '')"
                            auto-submit="true"
                            size="sm"
                        />
                    </form>
                @else
                    <div class="px-3 py-1.5 rounded-xl bg-zinc-100 text-zinc-700 text-xs font-bold border border-zinc-200/80 inline-flex items-center gap-1.5">
                        <x-ui.icon name="store" class="w-3.5 h-3.5 text-zinc-500" />
                        <span>{{ $selectedBranch?->name ?? 'Cabang Utama' }}</span>
                    </div>
                @endif

                <!-- Jam Realtime -->
                <div class="px-3 py-1.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-900 text-xs font-black">
                    <livewire:attendance-clock :show-pulse="true" />
                </div>
            </div>
        </div>

        <!-- Layout Grid: Kamera Scanner di Kiri, Hasil & Riwayat di Kanan -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            <!-- Kolom Kiri: Kamera Pemindai (7 Cols) -->
            <div class="lg:col-span-7 space-y-4">
                <div class="p-4 sm:p-6 rounded-3xl bg-zinc-950 text-white shadow-xl border border-zinc-800 space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span class="text-xs font-bold tracking-wide text-zinc-300 uppercase">Kamera Scanner Aktif</span>
                        </div>

                        <!-- Tombol Switch Kamera & Senter -->
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                id="btn-switch-camera"
                                onclick="switchScannerCamera()"
                                class="px-2.5 py-1.5 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 text-xs font-bold transition inline-flex items-center gap-1.5 cursor-pointer"
                                title="Ganti Kamera Depan / Belakang"
                            >
                                <x-ui.icon name="refresh-cw" class="w-3.5 h-3.5 text-amber-400" />
                                <span class="hidden sm:inline">Ganti Kamera</span>
                            </button>
                        </div>
                    </div>

                    <!-- Viewfinder Container -->
                    <div class="relative w-full aspect-square max-w-[420px] mx-auto rounded-2xl overflow-hidden bg-black border-2 border-zinc-800 flex items-center justify-center">
                        <div id="attendance-scanner-reader" class="w-full h-full"></div>

                        <!-- Target Frame Laser Overlay -->
                        <div id="scanner-overlay-box" class="pointer-events-none absolute inset-0 flex items-center justify-center">
                            <div class="relative w-3/4 h-3/4 rounded-2xl border-2 border-dashed border-amber-400/70 shadow-2xl flex items-center justify-center">
                                <!-- Laser Line Scan Effect -->
                                <div class="absolute inset-x-2 h-0.5 bg-gradient-to-r from-transparent via-amber-400 to-transparent animate-bounce opacity-80"></div>
                                <span class="text-[11px] font-bold text-amber-200/90 bg-zinc-950/70 px-2.5 py-1 rounded-full backdrop-blur-xs">
                                    Posisikan QR di dalam kotak
                                </span>
                            </div>
                        </div>

                        <!-- Loading Scanner Indicator -->
                        <div id="scanner-loading-state" class="absolute inset-0 bg-zinc-950/90 flex flex-col items-center justify-center gap-2 text-center p-4">
                            <div class="w-8 h-8 border-3 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
                            <span class="text-xs font-bold text-zinc-300">Menyiapkan kamera...</span>
                            <span class="text-[11px] text-zinc-500">Pastikan izin kamera telah disetujui di browser</span>
                        </div>
                    </div>

                    <!-- Status Text Panduan -->
                    <div class="text-center space-y-1">
                        <p class="text-xs text-zinc-300 font-medium">
                            Arahkan kamera ke kode QR yang ditampilkan pada HP staf.
                        </p>
                        <p class="text-[11px] text-zinc-500">
                            Sistem otomatis mendeteksi: Jika staf belum absen akan dicatat <strong>Masuk</strong>, jika sudah masuk akan dicatat <strong>Pulang</strong>.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Kolom Kanan: Card Hasil Terakhir & Log Realtime (5 Cols) -->
            <div class="lg:col-span-5 space-y-4">
                <!-- Card Notifikasi Hasil Scan Terakhir -->
                <div id="scan-result-card" class="hidden p-5 rounded-3xl border transition-all duration-300 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div id="result-icon-bg" class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 text-white font-black">
                                <span id="result-icon"></span>
                            </div>
                            <div class="min-w-0">
                                <span id="result-action-badge" class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider inline-block"></span>
                                <h3 id="result-staff-name" class="font-black text-sm text-zinc-900 truncate mt-0.5"></h3>
                                <p id="result-staff-meta" class="text-xs text-zinc-500 truncate"></p>
                            </div>
                        </div>

                        <button
                            type="button"
                            onclick="closeResultCard()"
                            class="w-7 h-7 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-500 flex items-center justify-center transition cursor-pointer"
                        >
                            <x-ui.icon name="x" class="w-3.5 h-3.5" />
                        </button>
                    </div>

                    <div id="result-message-box" class="p-3 rounded-2xl text-xs font-semibold leading-relaxed"></div>

                    <div class="pt-1 flex items-center justify-between text-xs text-zinc-600 border-t border-zinc-200/60">
                        <span class="text-zinc-400">Jam Dicatat:</span>
                        <span id="result-timestamp" class="font-black text-zinc-900 font-mono"></span>
                    </div>
                </div>

                <!-- Riwayat Absensi Hari Ini -->
                <div class="p-4 sm:p-5 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between pb-2 border-b border-zinc-100">
                        <div class="flex items-center gap-2">
                            <x-ui.icon name="check-circle" class="w-4 h-4 text-emerald-600" />
                            <h3 class="font-bold text-xs sm:text-sm text-zinc-900">Riwayat Scan Hari Ini</h3>
                        </div>
                        <span id="today-scan-count" class="px-2 py-0.5 rounded-full text-[10px] font-black bg-zinc-100 text-zinc-700">
                            {{ $todayAttendances->count() }} Staf
                        </span>
                    </div>

                    <div id="today-attendances-list" class="divide-y divide-zinc-100 max-h-[380px] overflow-y-auto no-scrollbar space-y-1">
                        @forelse($todayAttendances as $att)
                            <div class="py-2.5 flex items-center justify-between gap-2 text-xs">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-bold text-zinc-900 truncate">{{ $att->membership?->display_name ?? $att->membership?->user?->name ?? 'Staf' }}</span>
                                        <span class="text-[10px] font-semibold text-zinc-400 uppercase">({{ $att->membership?->role?->value ?? 'barber' }})</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-[11px] text-zinc-500 mt-0.5">
                                        <span class="text-emerald-700 font-mono font-bold">In: {{ $att->check_in_at ? $att->check_in_at->format('H:i') : '--:--' }}</span>
                                        <span>&bull;</span>
                                        <span class="text-amber-700 font-mono font-bold">Out: {{ $att->check_out_at ? $att->check_out_at->format('H:i') : '--:--' }}</span>
                                    </div>
                                </div>

                                <div class="shrink-0 text-right">
                                    @if($att->check_out_at)
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black bg-zinc-100 text-zinc-700 border border-zinc-200">
                                            Selesai
                                        </span>
                                    @elseif($att->status === 'late')
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black bg-rose-50 text-rose-700 border border-rose-200">
                                            Telat {{ $att->late_minutes }}m
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            Tepat Waktu
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div id="empty-state-list" class="py-8 text-center text-zinc-400 space-y-1">
                                <x-ui.icon name="clock" class="w-6 h-6 mx-auto text-zinc-300 stroke-[1.5]" />
                                <p class="text-xs font-bold text-zinc-500">Belum ada staf yang scan hari ini</p>
                                <p class="text-[11px] text-zinc-400">Hasil scan QR absensi akan otomatis muncul di sini</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Script Vendor HTML5-QRCode Scanner -->
    <script src="{{ asset('vendor/html5-qrcode/html5-qrcode.min.js') }}"></script>

    <script>
        let html5Scanner = null;
        let currentCameraIndex = 0;
        let availableCameras = [];
        let isProcessingScan = false;
        const selectedBranchId = {{ $selectedBranch?->id ?? 'null' }};
        const processScanUrl = '{{ route('owner.attendances.scanner.process') }}';
        const csrfToken = '{{ csrf_token() }}';

        // Play subtle synthesized audio beep
        function playSuccessAudio() {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(880, audioCtx.currentTime); // 880Hz (A5)
                gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.2);
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start();
                osc.stop(audioCtx.currentTime + 0.2);
            } catch (e) {
                // AudioContext might be restricted until user gesture
            }
        }

        async function initAttendanceScanner() {
            const loadingEl = document.getElementById('scanner-loading-state');

            if (!window.Html5Qrcode) {
                if (loadingEl) {
                    loadingEl.innerHTML = '<span class="text-xs font-bold text-rose-400">Library scanner gagal dimuat. Refresh halaman.</span>';
                }
                return;
            }

            try {
                availableCameras = await Html5Qrcode.getCameras();

                if (!availableCameras || availableCameras.length === 0) {
                    if (loadingEl) {
                        loadingEl.innerHTML = '<span class="text-xs font-bold text-rose-400">Tidak ada kamera yang ditemukan pada perangkat ini.</span>';
                    }
                    return;
                }

                // Prioritaskan kamera belakang jika ada
                let preferredCamIndex = 0;
                for (let i = 0; i < availableCameras.length; i++) {
                    const label = (availableCameras[i].label || '').toLowerCase();
                    if (label.includes('back') || label.includes('rear') || label.includes('belakang') || label.includes('environment')) {
                        preferredCamIndex = i;
                        break;
                    }
                }
                currentCameraIndex = preferredCamIndex;

                startCamera(availableCameras[currentCameraIndex].id);
            } catch (err) {
                console.error('Camera init error:', err);
                if (loadingEl) {
                    loadingEl.innerHTML = '<span class="text-xs font-bold text-rose-400">Izin kamera ditolak atau kamera sedang digunakan aplikasi lain.</span>';
                }
            }
        }

        async function startCamera(cameraId) {
            const loadingEl = document.getElementById('scanner-loading-state');
            if (loadingEl) loadingEl.style.display = 'flex';

            if (html5Scanner && html5Scanner.isScanning) {
                await html5Scanner.stop();
            }

            html5Scanner = new Html5Qrcode('attendance-scanner-reader');

            const config = {
                fps: 15,
                qrbox: { width: 260, height: 260 },
                aspectRatio: 1.0,
            };

            try {
                await html5Scanner.start(
                    cameraId,
                    config,
                    onQrCodeScanned,
                    (errorMessage) => {
                        // scanning frame by frame, ignore non-matches
                    }
                );

                if (loadingEl) loadingEl.style.display = 'none';
            } catch (err) {
                console.error('Start camera failed:', err);
                if (loadingEl) {
                    loadingEl.innerHTML = '<span class="text-xs font-bold text-rose-400">Gagal mengaktifkan kamera. Periksa izin browser.</span>';
                }
            }
        }

        async function switchScannerCamera() {
            if (!availableCameras || availableCameras.length <= 1) {
                alert('Hanya 1 kamera yang terdeteksi pada perangkat ini.');
                return;
            }

            currentCameraIndex = (currentCameraIndex + 1) % availableCameras.length;
            await startCamera(availableCameras[currentCameraIndex].id);
        }

        async function onQrCodeScanned(decodedText) {
            if (isProcessingScan) return;
            isProcessingScan = true;

            playSuccessAudio();

            try {
                const response = await fetch(processScanUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        code: decodedText,
                        branch_id: selectedBranchId,
                    }),
                });

                const data = await response.json();

                if (data.success) {
                    showResultCard(data);
                    prependAttendanceRow(data);
                } else {
                    showErrorResult(data.message || 'QR code tidak valid.');
                }
            } catch (err) {
                console.error('Process scan error:', err);
                showErrorResult('Gagal menghubungi server. Periksa koneksi internet.');
            } finally {
                // Beri jeda 3 detik sebelum scanner siap membaca kembali
                setTimeout(() => {
                    isProcessingScan = false;
                }, 3000);
            }
        }

        function showResultCard(data) {
            const card = document.getElementById('scan-result-card');
            const iconBg = document.getElementById('result-icon-bg');
            const icon = document.getElementById('result-icon');
            const actionBadge = document.getElementById('result-action-badge');
            const staffName = document.getElementById('result-staff-name');
            const staffMeta = document.getElementById('result-staff-meta');
            const messageBox = document.getElementById('result-message-box');
            const timestamp = document.getElementById('result-timestamp');

            if (!card) return;

            card.classList.remove('hidden');

            staffName.textContent = data.staff ? data.staff.name : 'Staf';
            staffMeta.textContent = (data.staff ? data.staff.role_label : '') + ' · ' + (data.staff ? data.staff.branch : '');
            timestamp.textContent = data.attendance ? (data.attendance.check_out_at || data.attendance.check_in_at || '-') : '-';
            messageBox.textContent = data.message;

            if (data.action === 'check_in') {
                const isLate = data.attendance && data.attendance.late_minutes > 0;
                card.className = 'p-5 rounded-3xl border transition-all duration-300 space-y-3 bg-emerald-50/90 border-emerald-200 shadow-sm';
                iconBg.className = 'w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 ' + (isLate ? 'bg-amber-500 text-zinc-950' : 'bg-emerald-600 text-white');
                icon.innerHTML = '<svg class="w-6 h-6 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>';
                actionBadge.className = 'px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider inline-block ' + (isLate ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800');
                actionBadge.textContent = 'Absen Masuk' + (isLate ? ' (Telat ' + data.attendance.late_minutes + 'm)' : ' (Tepat Waktu)');
                messageBox.className = 'p-3 rounded-2xl text-xs font-semibold leading-relaxed bg-white/80 border border-emerald-200/60 text-emerald-950';
            } else if (data.action === 'check_out') {
                card.className = 'p-5 rounded-3xl border transition-all duration-300 space-y-3 bg-amber-50/90 border-amber-200 shadow-sm';
                iconBg.className = 'w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 bg-amber-600 text-white';
                icon.innerHTML = '<svg class="w-6 h-6 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>';
                actionBadge.className = 'px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider inline-block bg-amber-100 text-amber-900';
                actionBadge.textContent = 'Absen Pulang (Selesai Shift)';
                messageBox.className = 'p-3 rounded-2xl text-xs font-semibold leading-relaxed bg-white/80 border border-amber-200/60 text-amber-950';
            } else {
                card.className = 'p-5 rounded-3xl border transition-all duration-300 space-y-3 bg-zinc-100 border-zinc-200 shadow-sm';
                iconBg.className = 'w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 bg-zinc-600 text-white';
                icon.innerHTML = '<svg class="w-6 h-6 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
                actionBadge.className = 'px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider inline-block bg-zinc-200 text-zinc-800';
                actionBadge.textContent = 'Informasi Absensi';
                messageBox.className = 'p-3 rounded-2xl text-xs font-semibold leading-relaxed bg-white/80 border border-zinc-200/60 text-zinc-800';
            }
        }

        function showErrorResult(msg) {
            const card = document.getElementById('scan-result-card');
            const iconBg = document.getElementById('result-icon-bg');
            const icon = document.getElementById('result-icon');
            const actionBadge = document.getElementById('result-action-badge');
            const staffName = document.getElementById('result-staff-name');
            const staffMeta = document.getElementById('result-staff-meta');
            const messageBox = document.getElementById('result-message-box');
            const timestamp = document.getElementById('result-timestamp');

            if (!card) return;

            card.classList.remove('hidden');
            card.className = 'p-5 rounded-3xl border transition-all duration-300 space-y-3 bg-rose-50 border-rose-200 shadow-sm';
            iconBg.className = 'w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 bg-rose-600 text-white';
            icon.innerHTML = '<svg class="w-6 h-6 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>';
            actionBadge.className = 'px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider inline-block bg-rose-100 text-rose-800';
            actionBadge.textContent = 'Gagal';
            staffName.textContent = 'Scan Tidak Dikenali';
            staffMeta.textContent = 'Periksa kode QR staf';
            messageBox.className = 'p-3 rounded-2xl text-xs font-semibold leading-relaxed bg-white/80 border border-rose-200/60 text-rose-950';
            messageBox.textContent = msg;
            timestamp.textContent = '-';
        }

        function closeResultCard() {
            const card = document.getElementById('scan-result-card');
            if (card) card.classList.add('hidden');
        }

        function prependAttendanceRow(data) {
            const list = document.getElementById('today-attendances-list');
            const emptyState = document.getElementById('empty-state-list');
            const countBadge = document.getElementById('today-scan-count');

            if (emptyState) emptyState.remove();

            const staff = data.staff || {};
            const att = data.attendance || {};

            const row = document.createElement('div');
            row.className = 'py-2.5 flex items-center justify-between gap-2 text-xs animate-in fade-in slide-in-from-top-2 duration-300';

            const statusBadge = att.check_out_at
                ? '<span class="px-2 py-0.5 rounded-md text-[10px] font-black bg-zinc-100 text-zinc-700 border border-zinc-200">Selesai</span>'
                : (att.late_minutes > 0
                    ? '<span class="px-2 py-0.5 rounded-md text-[10px] font-black bg-rose-50 text-rose-700 border border-rose-200">Telat ' + att.late_minutes + 'm</span>'
                    : '<span class="px-2 py-0.5 rounded-md text-[10px] font-black bg-emerald-50 text-emerald-700 border border-emerald-200">Tepat Waktu</span>');

            row.innerHTML = `
                <div class="min-w-0">
                    <div class="flex items-center gap-1.5">
                        <span class="font-bold text-zinc-900 truncate">${staff.name || 'Staf'}</span>
                        <span class="text-[10px] font-semibold text-zinc-400 uppercase">(${staff.role_label || 'Staf'})</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-[11px] text-zinc-500 mt-0.5">
                        <span class="text-emerald-700 font-mono font-bold">In: ${att.check_in_at || '--:--'}</span>
                        <span>&bull;</span>
                        <span class="text-amber-700 font-mono font-bold">Out: ${att.check_out_at || '--:--'}</span>
                    </div>
                </div>
                <div class="shrink-0 text-right">
                    ${statusBadge}
                </div>
            `;

            if (list) {
                list.prepend(row);
                const currentCount = list.children.length;
                if (countBadge) countBadge.textContent = currentCount + ' Staf';
            }
        }

        document.addEventListener('DOMContentLoaded', initAttendanceScanner);
    </script>
</x-layouts.app>