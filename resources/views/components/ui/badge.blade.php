@props([
    'status' => 'waiting',
    'plain' => false,
])

@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;

    $map = [
        'waiting' => ['label' => 'Menunggu', 'class' => 'bg-zinc-100 text-zinc-700', 'plainClass' => 'text-zinc-600'],
        'called' => ['label' => 'Dipanggil', 'class' => 'bg-sky-100 text-sky-700', 'plainClass' => 'text-sky-600'],
        'in_service' => ['label' => 'Sedang Dicukur', 'class' => 'bg-amber-100 text-amber-800', 'plainClass' => 'text-amber-600'],
        'completed' => ['label' => 'Selesai', 'class' => 'bg-emerald-100 text-emerald-700', 'plainClass' => 'text-emerald-600'],
        'paid' => ['label' => 'Lunas', 'class' => 'bg-emerald-100 text-emerald-700', 'plainClass' => 'text-emerald-600'],
        'unpaid' => ['label' => 'Belum Bayar', 'class' => 'bg-orange-100 text-orange-700', 'plainClass' => 'text-amber-600'],
        'pending' => ['label' => 'Pending', 'class' => 'bg-amber-100 text-amber-800', 'plainClass' => 'text-amber-600'],
        'confirmed' => ['label' => 'Booking Aktif', 'class' => 'bg-violet-100 text-violet-700', 'plainClass' => 'text-violet-600'],
        'cancelled' => ['label' => 'Dibatalkan', 'class' => 'bg-rose-100 text-rose-700', 'plainClass' => 'text-rose-600'],
        'no_show' => ['label' => 'No-show', 'class' => 'bg-rose-100 text-rose-700', 'plainClass' => 'text-rose-600'],
        'active' => ['label' => 'Aktif', 'class' => 'bg-emerald-100 text-emerald-700', 'plainClass' => 'text-emerald-600'],
        'suspended' => ['label' => 'Suspended', 'class' => 'bg-rose-100 text-rose-700', 'plainClass' => 'text-rose-600'],
        'trial' => ['label' => 'Trial', 'class' => 'bg-amber-100 text-amber-800', 'plainClass' => 'text-amber-600'],
        'draft' => ['label' => 'Draft', 'class' => 'bg-zinc-100 text-zinc-700', 'plainClass' => 'text-zinc-500'],
    ];

    $cfg = $map[$value] ?? [
        'label' => ucfirst(str_replace('_', ' ', $value)),
        'class' => 'bg-zinc-100 text-zinc-700',
        'plainClass' => 'text-zinc-600',
    ];
@endphp

@if($plain)
    <span {{ $attributes->merge(['class' => 'font-bold text-xs inline-block ' . $cfg['plainClass']]) }}>
        {{ $slot->isEmpty() ? $cfg['label'] : $slot }}
    </span>
@else
    <span {{ $attributes->merge([
        'class' => 'inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full ' . $cfg['class'],
    ]) }}>
        {{ $slot->isEmpty() ? $cfg['label'] : $slot }}
    </span>
@endif
