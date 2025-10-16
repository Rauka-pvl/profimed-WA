<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Artisan;

Route::post('/webhook/whatsapp', [WebhookController::class, 'handleIncoming'])->name('webhook.whatsapp');
Route::get('/send-reminders', function () {
    return Artisan::call('reminders:send');
});
