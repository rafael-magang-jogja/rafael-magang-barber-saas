<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cleanup:orders', function () {
    // 1. Hapus data dummy dengan nama ngawur
    $junkNames = ['gfcscdsv a', 'dsadsa', 'gvcbvcbv'];
    $junkProfiles = \App\Models\CustomerProfile::whereIn('name', $junkNames)->get();
    foreach ($junkProfiles as $p) {
        $orders = \App\Models\Order::where('customer_profile_id', $p->id)->get();
        foreach ($orders as $o) {
            $o->payments()->delete();
            $o->items()->delete();
            \App\Models\CommissionEntry::where('order_id', $o->id)->delete();
            if ($o->queue_ticket_id) {
                \App\Models\QueueTicketItem::where('queue_ticket_id', $o->queue_ticket_id)->delete();
                \App\Models\QueueTicketEvent::where('queue_ticket_id', $o->queue_ticket_id)->delete();
                \App\Models\QueueTicket::where('id', $o->queue_ticket_id)->delete();
            }
            $o->delete();
        }
        $p->delete();
    }

    // 2. Hapus invoice duplikat testing agar data bersih dan unik
    $duplicateNumbers = [
        'INV-SDR-20261027-P7PN', // Rafael (barber kosong '-')
        'INV-SDR-20261027-7HZE', // Rafael duplikat
        'INV-SDR-20261027-VU0S', // Gilang duplikat
        'INV-SDR-20261027-5YK7', // Budi duplikat
    ];
    foreach ($duplicateNumbers as $num) {
        $o = \App\Models\Order::where('number', $num)->first();
        if ($o) {
            $o->payments()->delete();
            $o->items()->delete();
            \App\Models\CommissionEntry::where('order_id', $o->id)->delete();
            if ($o->queue_ticket_id) {
                \App\Models\QueueTicketItem::where('queue_ticket_id', $o->queue_ticket_id)->delete();
                \App\Models\QueueTicketEvent::where('queue_ticket_id', $o->queue_ticket_id)->delete();
                \App\Models\QueueTicket::where('id', $o->queue_ticket_id)->delete();
            }
            $o->delete();
        }
    }

    $this->info('Cleanup completed successfully.');
})->purpose('Bersihkan data transaksi dummy dan duplikat testing');

