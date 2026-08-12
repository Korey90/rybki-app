<?php

use App\Http\Controllers\Api\BookingHoldController;
use App\Http\Controllers\Api\BookingPaymentController;
use App\Http\Controllers\Api\FishingVenueAvailabilityController;
use App\Http\Controllers\Api\PayUPaymentWebhookController;
use App\Http\Controllers\Api\PublicFishingVenueController;
use App\Http\Controllers\Api\StripePaymentWebhookController;
use App\Http\Controllers\Api\TestPaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('fishing-venues', [PublicFishingVenueController::class, 'index'])
    ->middleware('throttle:public-api')
    ->name('api.fishing-venues.index');
Route::get('fishing-venues/{fishingVenue:slug}', [PublicFishingVenueController::class, 'show'])
    ->middleware('throttle:public-api')
    ->name('api.fishing-venues.show');

Route::get('fishing-venues/{fishingVenue}/availability', FishingVenueAvailabilityController::class)
    ->middleware('throttle:public-api')
    ->name('api.fishing-venues.availability');

Route::post('booking-holds', [BookingHoldController::class, 'store'])
    ->middleware('throttle:booking-api')
    ->name('api.booking-holds.store');

Route::post('bookings/{booking}/payment', [BookingPaymentController::class, 'store'])
    ->middleware('throttle:booking-api')
    ->name('api.bookings.payment.store');

Route::post('webhooks/test-payment', [TestPaymentWebhookController::class, 'store'])
    ->middleware('throttle:webhooks')
    ->name('api.webhooks.test-payment');
Route::post('webhooks/stripe-connect', StripePaymentWebhookController::class)
    ->middleware('throttle:webhooks')
    ->name('api.webhooks.stripe-connect');
Route::post('webhooks/payu-marketplace', PayUPaymentWebhookController::class)
    ->middleware('throttle:webhooks')
    ->name('api.webhooks.payu-marketplace');
