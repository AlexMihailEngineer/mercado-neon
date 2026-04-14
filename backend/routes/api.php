<?php

use App\Http\Controllers\Api\FanCourierWebhookController;
use App\Http\Controllers\Api\StripeWebhookController;
use Illuminate\Support\Facades\Route;

// External System Actions (Stateless, No CSRF)
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);
Route::post('/webhooks/fancourier', FanCourierWebhookController::class)->name('webhooks.fancourier');
