<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Webhooks\StripeWebhookController;

// External System Actions (Stateless, No CSRF)
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);
