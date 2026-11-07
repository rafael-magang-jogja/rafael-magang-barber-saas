<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('queue:cancel-expired', function (\App\Repositories\QueueRepository $queueRepo) {
    $result = $queueRepo->cancelExpiredQueuesAndBookings(force: true);
    $this->info("Berhasil membatalkan {$result['cancelled_tickets']} antrean expired dan {$result['cancelled_bookings']} booking terlewat.");
})->purpose('Batalkan tiket antrean dan booking yang sudah melewati hari operasional');

\Illuminate\Support\Facades\Schedule::command('queue:cancel-expired')->dailyAt('00:01');

Artisan::command('attendance:close-daily', function (\App\Services\OwnerManagementService $ownerService) {
    $result = $ownerService->autoCloseDailyAttendances();
    $this->info("Otomatisasi absensi berhasil: {$result['auto_checked_out']} checkout otomatis, {$result['marked_absent']} alpa tercatat.");
})->purpose('Tutup otomatis absensi lupa checkout dan catat alpa lewat 23:59');

\Illuminate\Support\Facades\Schedule::command('attendance:close-daily')->dailyAt('00:01');


