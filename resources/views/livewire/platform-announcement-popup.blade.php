<div wire:poll.5s.visible>
    @if($pendingAnnouncement)
        <div
            id="platform-announcement-modal"
            class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-zinc-950/45 backdrop-blur-xs transition-opacity duration-200"
            role="dialog"
            aria-modal="true"
            aria-labelledby="announcement-modal-title"
        >
            <div class="relative w-full max-w-sm sm:max-w-md rounded-2xl bg-white border border-zinc-200/90 shadow-2xl p-4 sm:p-5 space-y-3.5 animate-in fade-in zoom-in-95 duration-200">
                <!-- Header: Ikon, Tipe, Target & Tombol Tutup -->
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 shadow-2xs {{
                            $pendingAnnouncement->type === 'urgent'
                                ? 'bg-rose-100 text-rose-700 border border-rose-200'
                                : ($pendingAnnouncement->type === 'maintenance'
                                    ? 'bg-amber-100 text-amber-800 border border-amber-200'
                                    : ($pendingAnnouncement->type === 'warning'
                                        ? 'bg-orange-100 text-orange-800 border border-orange-200'
                                        : 'bg-sky-100 text-sky-800 border border-sky-200'))
                        }}">
                            <x-ui.icon name="{{ $pendingAnnouncement->typeIcon() }}" class="w-4 h-4 stroke-[2.5]" />
                        </span>
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider {{ $pendingAnnouncement->typeBadgeClasses() }} border">
                                    {{ $pendingAnnouncement->typeLabel() }}
                                </span>
                                @if($pendingAnnouncement->target_role !== 'all')
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-zinc-100 text-zinc-600 border border-zinc-200">
                                        {{ $pendingAnnouncement->targetRoleLabel() }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-[10px] text-zinc-400 mt-0.5">Pengumuman Resmi Platform SaaS</p>
                        </div>
                    </div>

                    <button
                        type="button"
                        wire:click="dismiss(false)"
                        class="p-1 rounded-lg text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 transition cursor-pointer shrink-0 outline-none focus:outline-none"
                        title="Tutup sementara (hanya sesi ini)"
                    >
                        <x-ui.icon name="x" class="w-4 h-4" />
                    </button>
                </div>

                <!-- Konten Pesan -->
                <div class="space-y-1.5 pt-1">
                    @if($pendingAnnouncement->title)
                        <h3 id="announcement-modal-title" class="text-sm font-black text-zinc-950 tracking-tight leading-snug">
                            {{ $pendingAnnouncement->title }}
                        </h3>
                    @endif
                    <div class="max-h-44 overflow-y-auto pr-1 text-xs text-zinc-600 leading-relaxed space-y-2 break-words">
                        {!! nl2br(e($pendingAnnouncement->message)) !!}
                    </div>
                </div>

                <!-- Footer: Checkbox 'Jangan Tampilkan Lagi' & Tombol Aksi -->
                <div class="pt-3 border-t border-zinc-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none outline-none focus:outline-none">
                        <input
                            type="checkbox"
                            wire:model.live="permanently"
                            class="rounded border-zinc-300 text-amber-500 focus:ring-amber-500 w-3.5 h-3.5 outline-none focus:outline-none"
                        />
                        <span class="text-[11px] font-bold text-zinc-600 hover:text-zinc-900 transition">
                            Jangan tampilkan lagi
                        </span>
                    </label>

                    <button
                        type="button"
                        wire:click="dismissWithCheckbox"
                        class="w-full sm:w-auto px-4 py-2 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-xs shadow-xs transition cursor-pointer inline-flex items-center justify-center gap-1.5 outline-none focus:outline-none"
                    >
                        <span>Saya Mengerti</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
