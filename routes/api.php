<?php

declare(strict_types=1);

use App\Http\Controllers\Api\XenditWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::post('/webhooks/xendit/invoice', [XenditWebhookController::class, 'handleInvoice'])
    ->name('api.webhooks.xendit.invoice');
