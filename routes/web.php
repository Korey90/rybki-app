<?php

use App\Http\Controllers\Account\BookingController as AccountBookingController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\FishingVenueClaimController as AdminFishingVenueClaimController;
use App\Http\Controllers\Admin\GovernmentDataController as AdminGovernmentDataController;
use App\Http\Controllers\Admin\PaymentProviderController as AdminPaymentProviderController;
use App\Http\Controllers\Admin\PaymentReconciliationController as AdminPaymentReconciliationController;
use App\Http\Controllers\Admin\SettlementController as AdminSettlementController;
use App\Http\Controllers\Auth\AuthenticatedHomeController;
use App\Http\Controllers\Marketplace\CheckoutController as MarketplaceCheckoutController;
use App\Http\Controllers\Marketplace\FishingVenueClaimController as MarketplaceFishingVenueClaimController;
use App\Http\Controllers\Marketplace\FishingVenueController as MarketplaceFishingVenueController;
use App\Http\Controllers\Marketplace\HomeController as MarketplaceHomeController;
use App\Http\Controllers\Operator\AvailabilityBlockController as OperatorAvailabilityBlockController;
use App\Http\Controllers\Operator\AvailabilityRuleController as OperatorAvailabilityRuleController;
use App\Http\Controllers\Operator\BookingController as OperatorBookingController;
use App\Http\Controllers\Operator\CalendarController as OperatorCalendarController;
use App\Http\Controllers\Operator\CancellationPolicyController as OperatorCancellationPolicyController;
use App\Http\Controllers\Operator\DashboardController as OperatorDashboardController;
use App\Http\Controllers\Operator\FishingSpotController as OperatorFishingSpotController;
use App\Http\Controllers\Operator\FishingSpotMediaController as OperatorFishingSpotMediaController;
use App\Http\Controllers\Operator\FishingVenueController as OperatorFishingVenueController;
use App\Http\Controllers\Operator\FishingVenueMediaController as OperatorFishingVenueMediaController;
use App\Http\Controllers\Operator\OrganizationController as OperatorOrganizationController;
use App\Http\Controllers\Operator\PricingRuleController as OperatorPricingRuleController;
use App\Http\Controllers\Operator\SettlementController as OperatorSettlementController;
use Illuminate\Support\Facades\Route;

Route::get('/', MarketplaceHomeController::class)->name('home');
Route::get('lowiska', [MarketplaceFishingVenueController::class, 'index'])
    ->name('marketplace.fishing-venues.index');
Route::get('lowiska/{fishingVenue:slug}', [MarketplaceFishingVenueController::class, 'show'])
    ->name('marketplace.fishing-venues.show');
Route::post('checkout', [MarketplaceCheckoutController::class, 'store'])
    ->middleware('throttle:checkout')
    ->name('checkout.store');
Route::get('checkout/{booking}', [MarketplaceCheckoutController::class, 'show'])
    ->name('checkout.show');
Route::post('checkout/{booking}/test-payment', [MarketplaceCheckoutController::class, 'confirmTestPayment'])
    ->middleware('throttle:checkout')
    ->name('checkout.test-payment');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', AuthenticatedHomeController::class)->name('dashboard');

    Route::get('konto/rezerwacje', [AccountBookingController::class, 'index'])
        ->name('account.bookings.index');
    Route::get('konto/rezerwacje/{booking}', [AccountBookingController::class, 'show'])
        ->name('account.bookings.show');
    Route::patch('konto/rezerwacje/{booking}/cancel', [AccountBookingController::class, 'cancel'])
        ->middleware('throttle:checkout')
        ->name('account.bookings.cancel');

    Route::post('lowiska/{fishingVenue:slug}/claim', [MarketplaceFishingVenueClaimController::class, 'store'])
        ->middleware('throttle:claims')
        ->name('marketplace.fishing-venues.claims.store');
});

Route::middleware(['auth', 'verified'])
    ->prefix('operator')
    ->name('operator.')
    ->group(function () {
        Route::get('/', [OperatorDashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('organizations', [OperatorOrganizationController::class, 'index'])
            ->name('organizations.index');
        Route::post('organizations', [OperatorOrganizationController::class, 'store'])
            ->name('organizations.store');

        Route::get('bookings', [OperatorBookingController::class, 'index'])
            ->name('bookings.index');
        Route::get('bookings/{booking}', [OperatorBookingController::class, 'show'])
            ->name('bookings.show');
        Route::patch('bookings/{booking}/cancel', [OperatorBookingController::class, 'cancel'])
            ->middleware('throttle:checkout')
            ->name('bookings.cancel');

        Route::get('calendar', [OperatorCalendarController::class, 'index'])
            ->name('calendar.index');

        Route::get('settlements', [OperatorSettlementController::class, 'index'])
            ->name('settlements.index');
        Route::post('settlements', [OperatorSettlementController::class, 'store'])
            ->name('settlements.store');
        Route::get('settlements/{settlementPeriod}/export', [OperatorSettlementController::class, 'export'])
            ->name('settlements.export');

        Route::get('cancellation-policies', [OperatorCancellationPolicyController::class, 'index'])
            ->name('cancellation-policies.index');
        Route::post('cancellation-policies', [OperatorCancellationPolicyController::class, 'store'])
            ->middleware('throttle:checkout')
            ->name('cancellation-policies.store');
        Route::patch('cancellation-policies/{cancellationPolicy}/default', [OperatorCancellationPolicyController::class, 'makeDefault'])
            ->name('cancellation-policies.default');

        Route::get('fishing-venues', [OperatorFishingVenueController::class, 'index'])
            ->name('fishing-venues.index');
        Route::post('fishing-venues', [OperatorFishingVenueController::class, 'store'])
            ->name('fishing-venues.store');
        Route::get('fishing-venues/{fishingVenue}', [OperatorFishingVenueController::class, 'show'])
            ->name('fishing-venues.show');
        Route::patch('fishing-venues/{fishingVenue}/publish', [OperatorFishingVenueController::class, 'publish'])
            ->name('fishing-venues.publish');
        Route::post('fishing-venues/{fishingVenue}/media', [OperatorFishingVenueMediaController::class, 'store'])
            ->name('fishing-venues.media.store');
        Route::patch('fishing-venues/{fishingVenue}/media/order', [OperatorFishingVenueMediaController::class, 'reorder'])
            ->name('fishing-venues.media.reorder');
        Route::patch('fishing-venues/{fishingVenue}/media/{media}/primary', [OperatorFishingVenueMediaController::class, 'makePrimary'])
            ->name('fishing-venues.media.primary');
        Route::delete('fishing-venues/{fishingVenue}/media/{media}', [OperatorFishingVenueMediaController::class, 'destroy'])
            ->name('fishing-venues.media.destroy');
        Route::post('fishing-venues/{fishingVenue}/spots', [OperatorFishingSpotController::class, 'store'])
            ->name('fishing-venues.spots.store');
        Route::post('fishing-venues/{fishingVenue}/spots/{fishingSpot}/media', [OperatorFishingSpotMediaController::class, 'store'])
            ->name('fishing-venues.spots.media.store');
        Route::patch('fishing-venues/{fishingVenue}/spots/{fishingSpot}/media/order', [OperatorFishingSpotMediaController::class, 'reorder'])
            ->name('fishing-venues.spots.media.reorder');
        Route::patch('fishing-venues/{fishingVenue}/spots/{fishingSpot}/media/{media}/primary', [OperatorFishingSpotMediaController::class, 'makePrimary'])
            ->name('fishing-venues.spots.media.primary');
        Route::delete('fishing-venues/{fishingVenue}/spots/{fishingSpot}/media/{media}', [OperatorFishingSpotMediaController::class, 'destroy'])
            ->name('fishing-venues.spots.media.destroy');
        Route::post('fishing-venues/{fishingVenue}/availability-rules', [OperatorAvailabilityRuleController::class, 'store'])
            ->name('fishing-venues.availability-rules.store');
        Route::post('fishing-venues/{fishingVenue}/availability-blocks', [OperatorAvailabilityBlockController::class, 'store'])
            ->name('fishing-venues.availability-blocks.store');
        Route::post('fishing-venues/{fishingVenue}/pricing-rules', [OperatorPricingRuleController::class, 'store'])
            ->name('fishing-venues.pricing-rules.store');
    });

Route::middleware(['auth', 'verified', 'admin', 'throttle:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('audit-logs', [AdminAuditLogController::class, 'index'])
            ->name('audit-logs.index');

        Route::get('government-data', [AdminGovernmentDataController::class, 'index'])
            ->name('government-data.index');
        Route::post('government-data/sync', [AdminGovernmentDataController::class, 'sync'])
            ->name('government-data.sync');
        Route::post('government-data/records/{record}/import', [AdminGovernmentDataController::class, 'import'])
            ->name('government-data.records.import');
        Route::patch('government-data/records/{record}/match', [AdminGovernmentDataController::class, 'match'])
            ->name('government-data.records.match');
        Route::patch('government-data/records/{record}/skip', [AdminGovernmentDataController::class, 'skip'])
            ->name('government-data.records.skip');

        Route::get('payment-reconciliation', [AdminPaymentReconciliationController::class, 'index'])
            ->name('payment-reconciliation.index');
        Route::post('payment-reconciliation', [AdminPaymentReconciliationController::class, 'store'])
            ->name('payment-reconciliation.store');

        Route::get('payment-providers', [AdminPaymentProviderController::class, 'index'])
            ->name('payment-providers.index');
        Route::patch('payment-providers', [AdminPaymentProviderController::class, 'update'])
            ->name('payment-providers.update');

        Route::get('settlements', [AdminSettlementController::class, 'index'])
            ->name('settlements.index');
        Route::patch('settlements/commission', [AdminSettlementController::class, 'updateCommission'])
            ->name('settlements.commission.update');
        Route::post('settlements', [AdminSettlementController::class, 'store'])
            ->name('settlements.store');
        Route::patch('settlements/{settlementPeriod}/payout', [AdminSettlementController::class, 'updatePayout'])
            ->name('settlements.payout.update');
        Route::get('settlements/{settlementPeriod}/export', [AdminSettlementController::class, 'export'])
            ->name('settlements.export');

        Route::get('venue-claims', [AdminFishingVenueClaimController::class, 'index'])
            ->name('venue-claims.index');
        Route::patch('venue-claims/{fishingVenueClaim}', [AdminFishingVenueClaimController::class, 'review'])
            ->name('venue-claims.review');
    });

require __DIR__.'/settings.php';
