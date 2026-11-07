<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PlatformAnnouncement;
use App\Models\PlatformAnnouncementRead;
use App\Models\User;

final class PlatformAnnouncementService
{
    /**
     * Dapatkan pengumuman aktif yang berhak dilihat user dan belum di-dismiss.
     */
    public function getPendingAnnouncementForUser(?User $user, ?string $role = null): ?PlatformAnnouncement
    {
        if ($user === null) {
            return null;
        }

        $effectiveRole = $role ?? ($user->is_superadmin ? 'superadmin' : null);
        if ($effectiveRole === 'superadmin') {
            return null; // Superadmin tidak perlu melihat popup pengumuman buatannya sendiri
        }

        $targetRole = $effectiveRole ?? 'customer';

        // Cari pengumuman aktif terbaru
        $announcements = PlatformAnnouncement::query()
            ->active()
            ->forRole($targetRole)
            ->latest('id')
            ->get();

        if ($announcements->isEmpty()) {
            return null;
        }

        $shownInSession = session('shown_announcements', []);

        $permanentlyDismissedIds = PlatformAnnouncementRead::query()
            ->where('user_id', $user->id)
            ->whereIn('announcement_id', $announcements->pluck('id'))
            ->where('dismissed_permanently', true)
            ->pluck('announcement_id')
            ->all();

        foreach ($announcements as $announcement) {
            // 1. Cek apakah sudah pernah ditampilkan pada sesi login saat ini (1x per login)
            if (in_array($announcement->id, $shownInSession, true)) {
                continue;
            }

            // 2. Cek apakah user telah mencentang "Jangan tampilkan lagi" secara permanen
            if (in_array($announcement->id, $permanentlyDismissedIds, true)) {
                continue;
            }

            return $announcement;
        }

        return null;
    }

    /**
     * Tandai pengumuman telah ditutup (baik sementara per sesi atau permanen jika dicentang).
     */
    public function dismiss(PlatformAnnouncement $announcement, User $user, bool $permanently = false): void
    {
        // Catat ke session agar tidak muncul lagi pada sesi login berjalan
        $shown = session('shown_announcements', []);
        if (! in_array($announcement->id, $shown, true)) {
            $shown[] = $announcement->id;
            session(['shown_announcements' => $shown]);
        }

        // Jika dicentang permanen, catat ke database
        if ($permanently) {
            PlatformAnnouncementRead::query()->updateOrCreate(
                [
                    'announcement_id' => $announcement->id,
                    'user_id' => $user->id,
                ],
                [
                    'dismissed_permanently' => true,
                    'last_shown_at' => now(),
                ]
            );
        } else {
            PlatformAnnouncementRead::query()->updateOrCreate(
                [
                    'announcement_id' => $announcement->id,
                    'user_id' => $user->id,
                ],
                [
                    'last_shown_at' => now(),
                ]
            );
        }
    }
}
