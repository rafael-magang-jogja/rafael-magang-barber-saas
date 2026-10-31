@props([
    'duration' => 3000,
])

@php
    $successMessage = session('status') ?? session('success');
    $errorMessage = session('error');
    $warningMessage = session('warning');
    $infoMessage = session('info');
    $validationErrors = ($errors ?? null) && $errors->any() ? $errors->all() : [];
@endphp

@if($successMessage || $errorMessage || !empty($validationErrors) || $warningMessage || $infoMessage)
    <div id="flash-alert-container" class="space-y-3 mb-4">
        {{-- Flash Berhasil / Sukses --}}
        @if($successMessage)
            <div
                data-flash-alert
                data-flash-duration="{{ $duration }}"
                class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-emerald-600 via-emerald-500 to-teal-600 text-white p-3.5 sm:px-4 sm:py-3.5 shadow-md shadow-emerald-950/10 border border-emerald-400/30 flex items-center justify-between gap-3 transition-all duration-400 ease-out"
                role="alert"
            >
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-white/20 text-white grid place-items-center shrink-0 backdrop-blur-xs shadow-2xs">
                        <x-ui.icon name="check" class="w-4 h-4 stroke-[2.5]" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs sm:text-sm font-bold text-white leading-snug break-words">
                            {{ $successMessage }}
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    onclick="window.dismissFlashAlert(this.closest('[data-flash-alert]'))"
                    class="w-7 h-7 rounded-lg hover:bg-white/20 text-white/80 hover:text-white transition grid place-items-center shrink-0 cursor-pointer"
                    title="Tutup notifikasi"
                >
                    <x-ui.icon name="x" class="w-4 h-4" />
                </button>

                {{-- Garis Indikator Timer 3 Detik --}}
                <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-black/10 overflow-hidden pointer-events-none">
                    <div class="h-full bg-white/50 w-full origin-left" data-flash-progress></div>
                </div>
            </div>
        @endif

        {{-- Flash Gagal / Error Session --}}
        @if($errorMessage)
            <div
                data-flash-alert
                data-flash-duration="{{ $duration }}"
                class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-rose-600 via-rose-500 to-red-600 text-white p-3.5 sm:px-4 sm:py-3.5 shadow-md shadow-rose-950/10 border border-rose-400/30 flex items-center justify-between gap-3 transition-all duration-400 ease-out"
                role="alert"
            >
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-white/20 text-white grid place-items-center shrink-0 backdrop-blur-xs shadow-2xs">
                        <x-ui.icon name="x" class="w-4 h-4 stroke-[2.5]" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs sm:text-sm font-bold text-white leading-snug break-words">
                            {{ $errorMessage }}
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    onclick="window.dismissFlashAlert(this.closest('[data-flash-alert]'))"
                    class="w-7 h-7 rounded-lg hover:bg-white/20 text-white/80 hover:text-white transition grid place-items-center shrink-0 cursor-pointer"
                    title="Tutup notifikasi"
                >
                    <x-ui.icon name="x" class="w-4 h-4" />
                </button>

                <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-black/10 overflow-hidden pointer-events-none">
                    <div class="h-full bg-white/50 w-full origin-left" data-flash-progress></div>
                </div>
            </div>
        @endif

        {{-- Flash Gagal / Validasi Form ($errors) --}}
        @if(!empty($validationErrors))
            <div
                data-flash-alert
                data-flash-duration="{{ $duration }}"
                class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-rose-600 via-rose-500 to-red-600 text-white p-3.5 sm:px-4 sm:py-3.5 shadow-md shadow-rose-950/10 border border-rose-400/30 flex items-start justify-between gap-3 transition-all duration-400 ease-out"
                role="alert"
            >
                <div class="flex items-start gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-white/20 text-white grid place-items-center shrink-0 backdrop-blur-xs shadow-2xs mt-0.5">
                        <x-ui.icon name="x" class="w-4 h-4 stroke-[2.5]" />
                    </div>
                    <div class="min-w-0">
                        @if(count($validationErrors) === 1)
                            <p class="text-xs sm:text-sm font-bold text-white leading-snug break-words">
                                {{ $validationErrors[0] }}
                            </p>
                        @else
                            <p class="text-xs sm:text-sm font-bold text-white leading-snug">
                                Terjadi beberapa kendala:
                            </p>
                            <ul class="mt-1 list-disc list-inside space-y-0.5 text-xs font-semibold text-rose-50">
                                @foreach($validationErrors as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                <button
                    type="button"
                    onclick="window.dismissFlashAlert(this.closest('[data-flash-alert]'))"
                    class="w-7 h-7 rounded-lg hover:bg-white/20 text-white/80 hover:text-white transition grid place-items-center shrink-0 cursor-pointer"
                    title="Tutup notifikasi"
                >
                    <x-ui.icon name="x" class="w-4 h-4" />
                </button>

                <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-black/10 overflow-hidden pointer-events-none">
                    <div class="h-full bg-white/50 w-full origin-left" data-flash-progress></div>
                </div>
            </div>
        @endif

        {{-- Flash Peringatan / Warning --}}
        @if($warningMessage)
            <div
                data-flash-alert
                data-flash-duration="{{ $duration }}"
                class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-amber-600 via-amber-500 to-orange-500 text-white p-3.5 sm:px-4 sm:py-3.5 shadow-md shadow-amber-950/10 border border-amber-400/30 flex items-center justify-between gap-3 transition-all duration-400 ease-out"
                role="alert"
            >
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-white/20 text-white grid place-items-center shrink-0 backdrop-blur-xs shadow-2xs">
                        <x-ui.icon name="alert-triangle" class="w-4 h-4" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs sm:text-sm font-bold text-white leading-snug break-words">
                            {{ $warningMessage }}
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    onclick="window.dismissFlashAlert(this.closest('[data-flash-alert]'))"
                    class="w-7 h-7 rounded-lg hover:bg-white/20 text-white/80 hover:text-white transition grid place-items-center shrink-0 cursor-pointer"
                    title="Tutup notifikasi"
                >
                    <x-ui.icon name="x" class="w-4 h-4" />
                </button>

                <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-black/10 overflow-hidden pointer-events-none">
                    <div class="h-full bg-white/50 w-full origin-left" data-flash-progress></div>
                </div>
            </div>
        @endif

        {{-- Flash Info --}}
        @if($infoMessage)
            <div
                data-flash-alert
                data-flash-duration="{{ $duration }}"
                class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-sky-600 via-sky-500 to-blue-600 text-white p-3.5 sm:px-4 sm:py-3.5 shadow-md shadow-sky-950/10 border border-sky-400/30 flex items-center justify-between gap-3 transition-all duration-400 ease-out"
                role="alert"
            >
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-white/20 text-white grid place-items-center shrink-0 backdrop-blur-xs shadow-2xs">
                        <x-ui.icon name="info" class="w-4 h-4" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs sm:text-sm font-bold text-white leading-snug break-words">
                            {{ $infoMessage }}
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    onclick="window.dismissFlashAlert(this.closest('[data-flash-alert]'))"
                    class="w-7 h-7 rounded-lg hover:bg-white/20 text-white/80 hover:text-white transition grid place-items-center shrink-0 cursor-pointer"
                    title="Tutup notifikasi"
                >
                    <x-ui.icon name="x" class="w-4 h-4" />
                </button>

                <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-black/10 overflow-hidden pointer-events-none">
                    <div class="h-full bg-white/50 w-full origin-left" data-flash-progress></div>
                </div>
            </div>
        @endif
    </div>

    <script>
        (function() {
            function autoInitFlash() {
                if (typeof window.initAllFlashAlerts === 'function') {
                    window.initAllFlashAlerts();
                }
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', autoInitFlash);
            } else {
                autoInitFlash();
            }
        })();
    </script>
@endif
