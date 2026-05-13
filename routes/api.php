<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Meta WhatsApp Webhook
Route::get('/webhook', [WebhookController::class, 'verify']);
Route::post('/webhook', [WebhookController::class, 'receive']);
