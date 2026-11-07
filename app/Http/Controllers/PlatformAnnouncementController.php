<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PlatformAnnouncement;
use App\Models\PlatformAnnouncementRead;
use App\Services\PlatformAnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PlatformAnnouncementController extends Controller
{
    public function __construct(
        private readonly PlatformAnnouncementService $announcementService,
    ) {}

    public function index(Request $request): View
    {
        $typeFilter = $request->input('type');
        $targetFilter = $request->input('target_role');
        $statusFilter = $request->input('status');

        $query = PlatformAnnouncement::query()
            ->with('creator')
            ->withCount([
                'reads',
                'reads as permanently_dismissed_count' => function ($q): void {
                    $q->where('dismissed_permanently', true);
                },
            ]);

        if (! empty($typeFilter) && $typeFilter !== 'all') {
            $query->where('type', $typeFilter);
        }

        if (! empty($targetFilter) && $targetFilter !== 'all') {
            $query->where('target_role', $targetFilter);
        }

        if ($statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        $announcements = $query->latest('id')->paginate(10)->withQueryString();

        $totalCount = PlatformAnnouncement::query()->count();
        $activeCount = PlatformAnnouncement::query()->where('is_active', true)->count();
        $totalReads = PlatformAnnouncementRead::query()->count();

        return view('platform.announcements', [
            'announcements' => $announcements,
            'totalCount' => $totalCount,
            'activeCount' => $activeCount,
            'totalReads' => $totalReads,
            'typeFilter' => $typeFilter ?? 'all',
            'targetFilter' => $targetFilter ?? 'all',
            'statusFilter' => $statusFilter ?? 'all',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:1000'],
            'type' => ['required', 'string', 'in:info,warning,maintenance,urgent'],
            'target_role' => ['required', 'string', 'in:all,owner,admin,barber,customer'],
            'duration_type' => ['nullable', 'string', 'in:today_only,forever,custom'],
            'custom_date' => ['nullable', 'required_if:duration_type,custom', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'message.required' => 'Isi pesan pengumuman wajib diisi.',
            'message.max' => 'Pesan pengumuman maksimal 1000 karakter.',
            'type.in' => 'Tipe pengumuman tidak valid.',
            'target_role.in' => 'Target audiens tidak valid.',
            'custom_date.required_if' => 'Tentukan batas tanggal penayangan pengumuman.',
        ]);

        $startsAt = now();
        $endsAt = null;
        $durationType = $validated['duration_type'] ?? 'today_only';

        if ($durationType === 'today_only') {
            // Berlaku hari ini saja sampai pukul 23:59:59 (otomatis kedaluwarsa besok)
            $endsAt = now()->endOfDay();
        } elseif ($durationType === 'custom' && ! empty($validated['custom_date'])) {
            // Berlaku sampai tanggal yang ditentukan pukul 23:59:59
            $endsAt = \Illuminate\Support\Carbon::parse($validated['custom_date'])->endOfDay();
        }
        // Jika 'forever', $endsAt tetap null (berlaku seterusnya tanpa batas waktu)

        $announcement = PlatformAnnouncement::query()->create([
            'title' => $validated['title'] ?? null,
            'message' => $validated['message'],
            'type' => $validated['type'],
            'target_role' => $validated['target_role'],
            'is_active' => $request->boolean('is_active'),
            'created_by' => $request->user()?->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $statusMsg = $announcement->is_active
            ? "Pengumuman '{$announcement->typeLabel()}' berhasil disimpan dan langsung disiarkan."
            : "Pengumuman '{$announcement->typeLabel()}' berhasil disimpan (status nonaktif).";

        return back()->with('status', $statusMsg);
    }

    public function toggle(PlatformAnnouncement $announcement): RedirectResponse
    {
        $announcement->update([
            'is_active' => ! $announcement->is_active,
        ]);

        $statusLabel = $announcement->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('status', "Pengumuman berhasil {$statusLabel}.");
    }

    public function destroy(PlatformAnnouncement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('status', 'Pengumuman berhasil dihapus dari riwayat sistem.');
    }

    /**
     * Endpoint tutup pengumuman untuk user (Owner, Kasir, Barber, Customer).
     */
    public function dismiss(Request $request, PlatformAnnouncement $announcement): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $permanently = $request->boolean('permanently');

        $this->announcementService->dismiss($announcement, $user, $permanently);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'permanently' => $permanently,
            ]);
        }

        return back();
    }
}
