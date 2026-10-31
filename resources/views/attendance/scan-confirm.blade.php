<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-zinc-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Absensi Staf — {{ $branch->name }} · {{ $tenant->name }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full flex flex-col justify-center items-center p-4 sm:p-6 text-zinc-100 antialiased selection:bg-amber-500 selection:text-zinc-950 font-sans">
    <div class="w-full max-w-md mx-auto space-y-4">
        <!-- Logo / Nama Barbershop -->
        <div class="text-center space-y-1">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-amber-500 text-zinc-950 font-black text-xl shadow-lg shadow-amber-500/20 mb-1">
                {{ substr($tenant->name, 0, 1) }}
            </div>
            <h1 class="font-black text-xl text-white tracking-tight">{{ $tenant->name }}</h1>
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-zinc-800 border border-zinc-700/80 text-xs font-semibold text-zinc-300">
                <x-ui.icon name="store" class="w-3.5 h-3.5 text-amber-400 shrink-0" />
                <span>{{ $branch->name }} ({{ $branch->code }})</span>
            </div>
        </div>

        @if(session('status'))
            <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm flex items-start gap-3 shadow-lg">
                <x-ui.icon name="check-circle" class="w-5 h-5 shrink-0 mt-0.5 text-emerald-400" />
                <div class="space-y-1 flex-1">
                    <p class="font-bold">{{ session('status') }}</p>
                </div>
            </div>
        @endif

        @if($errors->has('attendance'))
            <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-sm flex items-start gap-3 shadow-lg">
                <x-ui.icon name="alert-triangle" class="w-5 h-5 shrink-0 mt-0.5 text-rose-400" />
                <div class="space-y-1 flex-1">
                    <p class="font-bold">{{ $errors->first('attendance') }}</p>
                </div>
            </div>
        @endif

        @if($error)
            <!-- Error Bukan Staf / Belum Waktunya Absen -->
            <div class="p-6 rounded-3xl bg-zinc-850 border {{ $error === 'Belum saatnya absen' ? 'border-amber-500/30' : 'border-rose-500/30' }} text-center space-y-4 shadow-xl">
                <div class="w-12 h-12 rounded-2xl {{ $error === 'Belum saatnya absen' ? 'bg-amber-500/10 text-amber-400' : 'bg-rose-500/10 text-rose-400' }} flex items-center justify-center mx-auto">
                    <x-ui.icon name="{{ $error === 'Belum saatnya absen' ? 'clock' : 'shield-alert' }}" class="w-6 h-6" />
                </div>
                <div>
                    <h3 class="font-black text-white text-base">{{ $error }}</h3>
                </div>
                <div class="pt-2">
                    <a href="{{ route('home') }}" class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-200 text-xs font-bold transition">
                        Kembali ke Beranda
                    </a>
                </div>
            </div>
        @else
            <!-- Kartu Identitas Staf & Absensi -->
            <div class="p-5 sm:p-6 rounded-3xl bg-zinc-800/90 border border-zinc-700/80 shadow-2xl backdrop-blur space-y-5">
                <!-- Info Karyawan -->
                <div class="flex items-center gap-3.5 pb-4 border-b border-zinc-700/60">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-amber-400 to-amber-600 text-zinc-950 font-black text-lg flex items-center justify-center shrink-0 shadow-md">
                        {{ strtoupper(substr($membership->display_name ?? $user->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <h2 class="font-black text-white text-base truncate">{{ $membership->display_name ?? $user->name }}</h2>
                            <span class="px-2 py-0.5 rounded-md bg-amber-500/10 text-amber-400 border border-amber-500/20 text-[10px] font-bold uppercase tracking-wider">
                                {{ $membership->role?->value ?? 'Staf' }}
                            </span>
                        </div>
                        <p class="text-xs text-zinc-400 truncate mt-0.5">{{ $user->email }}</p>
                    </div>
                </div>

                <!-- Jam Realtime & Tanggal -->
                <div class="p-4 rounded-2xl bg-zinc-900/80 border border-zinc-700/60 text-center space-y-1">
                    <div id="live-clock" class="text-3xl font-black font-mono tracking-tight text-amber-400">
                        {{ now()->format('H:i:s') }}
                    </div>
                    <p class="text-xs text-zinc-400 font-medium">
                        {{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}
                    </p>
                </div>

                <!-- Status Absensi Hari Ini -->
                <div class="space-y-3">
                    <div class="text-xs font-bold uppercase tracking-wider text-zinc-400">
                        Status Kehadiran Hari Ini
                    </div>

                    <div class="grid grid-cols-2 gap-2.5 text-xs">
                        <!-- Absen Masuk -->
                        <div class="p-3 rounded-xl bg-zinc-900/60 border border-zinc-700/60 space-y-1">
                            <span class="text-[10px] text-zinc-400 block font-semibold">Absen Masuk</span>
                            @if($attendance && $attendance->check_in_at)
                                <div class="font-mono font-black text-emerald-400 text-sm">
                                    {{ $attendance->check_in_at->format('H:i') }}
                                </div>
                                <span class="text-[10px] text-zinc-500 block">
                                    {{ $attendance->late_minutes > 0 ? "Telat {$attendance->late_minutes}m" : 'Tepat Waktu' }}
                                </span>
                            @else
                                <div class="font-mono text-zinc-500 text-sm">--:--</div>
                                <span class="text-[10px] text-zinc-600 block">Belum tercatat</span>
                            @endif
                        </div>

                        <!-- Absen Pulang -->
                        <div class="p-3 rounded-xl bg-zinc-900/60 border border-zinc-700/60 space-y-1">
                            <span class="text-[10px] text-zinc-400 block font-semibold">Absen Pulang</span>
                            @if($attendance && $attendance->check_out_at)
                                <div class="font-mono font-black text-amber-400 text-sm">
                                    {{ $attendance->check_out_at->format('H:i') }}
                                </div>
                                <span class="text-[10px] text-zinc-500 block">Selesai Shift</span>
                            @else
                                <div class="font-mono text-zinc-500 text-sm">--:--</div>
                                <span class="text-[10px] text-zinc-600 block">Belum pulang</span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Status Lokasi GPS & Radius Cabang -->
                @if($branch->lat !== null && $branch->lng !== null)
                    <div id="gps-status-box" class="p-3.5 rounded-2xl bg-zinc-900/80 border border-zinc-700/60 space-y-1.5 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-400 font-bold text-[11px] flex items-center gap-1.5">
                                <x-ui.icon name="map-pin" class="w-3.5 h-3.5 text-amber-500" />
                                <span>Verifikasi Radius Lokasi Cabang</span>
                            </span>
                            <span class="text-[10px] text-zinc-500 font-mono">Batas: {{ $branch->radius_meters ?: 50 }}m</span>
                        </div>

                        <div id="gps-status-content" class="flex items-center gap-2 text-zinc-400 text-xs py-1">
                            <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                            <span>Mendeteksi koordinat GPS perangkat Anda...</span>
                        </div>
                    </div>
                @endif

                <!-- Form Tombol Aksi Absensi -->
                <form id="attendance-form" method="POST" action="{{ route('attend.record', $branch->qr_token) }}" class="space-y-3 pt-1">
                    @csrf
                    <input type="hidden" name="lat" id="gps-lat" value="">
                    <input type="hidden" name="lng" id="gps-lng" value="">

                    @if(!$attendance || !$attendance->check_in_at)
                        <!-- Belum Absen Masuk -->
                        <input type="hidden" name="action" value="check_in">
                        <button
                            type="submit"
                            id="btn-submit-attendance"
                            class="w-full py-3.5 px-5 rounded-2xl bg-emerald-500 hover:bg-emerald-400 active:scale-[0.99] text-zinc-950 font-black text-sm flex items-center justify-center gap-2 shadow-lg shadow-emerald-500/25 transition cursor-pointer"
                        >
                            <x-ui.icon name="check-circle" class="w-5 h-5 stroke-[2.5]" />
                            <span>Konfirmasi Absen Masuk</span>
                        </button>
                    @elseif($attendance->check_in_at && !$attendance->check_out_at)
                        <!-- Sudah Masuk, Siap Absen Pulang -->
                        <input type="hidden" name="action" value="check_out">
                        <button
                            type="submit"
                            id="btn-submit-attendance"
                            class="w-full py-3.5 px-5 rounded-2xl bg-amber-500 hover:bg-amber-400 active:scale-[0.99] text-zinc-950 font-black text-sm flex items-center justify-center gap-2 shadow-lg shadow-amber-500/25 transition cursor-pointer"
                        >
                            <x-ui.icon name="log-out" class="w-5 h-5 stroke-[2.5]" />
                            <span>Konfirmasi Absen Pulang</span>
                        </button>
                    @else
                        <!-- Selesai Hari Ini -->
                        <div class="p-3.5 rounded-2xl bg-zinc-900 border border-zinc-700 text-center space-y-1">
                            <span class="text-xs font-bold text-emerald-400 flex items-center justify-center gap-1.5">
                                <x-ui.icon name="check" class="w-4 h-4" />
                                <span>Absensi Hari Ini Lengkap</span>
                            </span>
                            <p class="text-[11px] text-zinc-400">Terima kasih atas kerja keras Anda di cabang {{ $branch->name }}.</p>
                        </div>
                    @endif
                </form>

                <!-- Tombol Navigasi Cepat Sesuai Role -->
                <div class="pt-2 border-t border-zinc-700/60 flex items-center justify-between text-xs">
                    <span class="text-zinc-500">Barbershop SaaS</span>
                    @php
                        $roleRoute = match ($membership->role?->value) {
                            'barber' => route('barber.dashboard'),
                            'owner' => route('owner.dashboard'),
                            default => route('cashier.dashboard'),
                        };
                    @endphp
                    <a href="{{ $roleRoute }}" class="text-amber-400 hover:text-amber-300 font-bold inline-flex items-center gap-1 transition">
                        <span>Buka Dashboard</span>
                        <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                    </a>
                </div>
            </div>
        @endif
    </div>

    <script>
        // Jam Berjalan Realtime
        function updateClock() {
            const clockEl = document.getElementById('live-clock');
            if (!clockEl) return;
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            clockEl.textContent = `${hours}:${minutes}:${seconds}`;
        }
        setInterval(updateClock, 1000);

        // Deteksi Lokasi GPS Otomatis & Kalkulasi Radius Cabang
        const branchLat = {{ $branch->lat !== null ? (float)$branch->lat : 'null' }};
        const branchLng = {{ $branch->lng !== null ? (float)$branch->lng : 'null' }};
        const maxRadius = {{ (int)($branch->radius_meters ?: 50) }};

        function calculateDistanceMeters(lat1, lon1, lat2, lon2) {
            const R = 6371000;
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                      Math.sin(dLon/2) * Math.sin(dLon/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            return R * c;
        }

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    const latInput = document.getElementById('gps-lat');
                    const lngInput = document.getElementById('gps-lng');
                    if (latInput) latInput.value = pos.coords.latitude;
                    if (lngInput) lngInput.value = pos.coords.longitude;

                    if (branchLat !== null && branchLng !== null) {
                        const dist = calculateDistanceMeters(pos.coords.latitude, pos.coords.longitude, branchLat, branchLng);
                        const roundedDist = Math.round(dist);
                        const statusBox = document.getElementById('gps-status-content');
                        const submitBtn = document.getElementById('btn-submit-attendance');

                        if (dist <= maxRadius) {
                            if (statusBox) {
                                statusBox.innerHTML = `
                                    <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0"></span>
                                    <span class="text-emerald-400 font-bold">Lokasi Valid: Jarak Anda ~${roundedDist} meter (Di dalam radius ${maxRadius}m)</span>
                                `;
                            }
                            if (submitBtn) {
                                submitBtn.disabled = false;
                            }
                        } else {
                            if (statusBox) {
                                statusBox.innerHTML = `
                                    <span class="w-2 h-2 rounded-full bg-rose-500 shrink-0"></span>
                                    <span class="text-rose-400 font-bold">Di Luar Jangkauan: Jarak Anda ~${roundedDist} meter (Maks. ${maxRadius}m)</span>
                                `;
                            }
                            if (submitBtn) {
                                submitBtn.disabled = true;
                                submitBtn.classList.remove('bg-emerald-500', 'hover:bg-emerald-400', 'bg-amber-500', 'hover:bg-amber-400');
                                submitBtn.classList.add('bg-zinc-700', 'text-zinc-400', 'cursor-not-allowed', 'shadow-none');
                                submitBtn.title = `Anda berada di luar radius cabang (${roundedDist}m > ${maxRadius}m)`;
                            }
                        }
                    }
                },
                function (err) {
                    console.log('GPS Geolocation dibatasi atau tidak diizinkan:', err.message);
                    const statusBox = document.getElementById('gps-status-content');
                    const submitBtn = document.getElementById('btn-submit-attendance');
                    if (statusBox) {
                        statusBox.innerHTML = `
                            <span class="w-2 h-2 rounded-full bg-rose-500 shrink-0"></span>
                            <span class="text-rose-400 font-bold">Akses GPS ditolak: Harap izinkan akses lokasi pada browser untuk absensi</span>
                        `;
                    }
                    if (branchLat !== null && branchLng !== null && submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.classList.remove('bg-emerald-500', 'hover:bg-emerald-400', 'bg-amber-500', 'hover:bg-amber-400');
                        submitBtn.classList.add('bg-zinc-700', 'text-zinc-400', 'cursor-not-allowed', 'shadow-none');
                    }
                },
                { enableHighAccuracy: true, timeout: 8000 }
            );
        }
    </script>
</body>
</html>
