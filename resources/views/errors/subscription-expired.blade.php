<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masa Langganan Berakhir — {{ $tenant->name ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-100 flex items-center justify-center p-4">
    <div class="max-w-md w-full text-center space-y-6 p-6 sm:p-8 rounded-3xl bg-zinc-900 border border-zinc-800 shadow-2xl">
        <div class="w-16 h-16 rounded-3xl bg-amber-500/10 border border-amber-500/20 text-amber-500 flex items-center justify-center mx-auto shadow-inner">
            <x-ui.icon name="lock" class="w-8 h-8 text-amber-500" />
        </div>

        <div class="space-y-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-rose-500/10 text-rose-400 border border-rose-500/20 font-bold text-xs uppercase tracking-wider">
                Masa Sewa Sistem Berakhir
            </span>
            <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">Layanan Sistem Dinonaktifkan</h1>
            <p class="text-xs sm:text-sm text-zinc-400 leading-relaxed">
                Masa aktif paket langganan barbershop <strong class="text-white">{{ $tenant->name }}</strong> telah habis. Seluruh fitur operasional dan kasir dikunci sementara.
            </p>
        </div>

        <div class="p-4 rounded-2xl bg-zinc-950/80 border border-zinc-800/80 text-left text-xs space-y-2">
            <div class="flex items-center gap-2 text-amber-400 font-bold">
                <x-ui.icon name="info" class="w-4 h-4 shrink-0" />
                <span>Petunjuk untuk Staf / Barber:</span>
            </div>
            <p class="text-zinc-400 leading-relaxed">
                Silakan hubungi pemilik barbershop (<strong class="text-zinc-200">{{ $tenant->owner?->name ?? 'Owner' }}</strong>) untuk melakukan perpanjangan paket langganan agar sistem dapat beroperasi kembali.
            </p>
        </div>

        <div class="pt-2 flex flex-col gap-2.5">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button
                    type="submit"
                    class="w-full py-2.5 px-4 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-white font-bold text-xs sm:text-sm transition cursor-pointer flex items-center justify-center gap-2"
                >
                    <x-ui.icon name="log-out" class="w-4 h-4" />
                    <span>Keluar dari Akun</span>
                </button>
            </form>
        </div>
    </div>
</body>
</html>
