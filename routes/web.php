<?php

use App\Http\Controllers\Account\BookingController as AccountBookingController;
use App\Http\Controllers\Account\FavoriteVenueController as AccountFavoriteVenueController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\FishingVenueClaimController as AdminFishingVenueClaimController;
use App\Http\Controllers\Admin\FishingVenueController as AdminFishingVenueController;
use App\Http\Controllers\Admin\FishingVenueReviewController as AdminFishingVenueReviewController;
use App\Http\Controllers\Admin\GovernmentDataController as AdminGovernmentDataController;
use App\Http\Controllers\Admin\OperatorController as AdminOperatorController;
use App\Http\Controllers\Admin\OrganizationController as AdminOrganizationController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\PaymentProviderController as AdminPaymentProviderController;
use App\Http\Controllers\Admin\PaymentReconciliationController as AdminPaymentReconciliationController;
use App\Http\Controllers\Admin\SettlementController as AdminSettlementController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\AuthenticatedHomeController;
use App\Http\Controllers\LegalDocumentController;
use App\Http\Controllers\Marketplace\CheckoutController as MarketplaceCheckoutController;
use App\Http\Controllers\Marketplace\FishingVenueClaimController as MarketplaceFishingVenueClaimController;
use App\Http\Controllers\Marketplace\FishingVenueController as MarketplaceFishingVenueController;
use App\Http\Controllers\Marketplace\FishingVenueFavoriteController as MarketplaceFishingVenueFavoriteController;
use App\Http\Controllers\Marketplace\FishingVenueReviewController as MarketplaceFishingVenueReviewController;
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
use App\Http\Controllers\Operator\FishingVenueReviewController as OperatorFishingVenueReviewController;
use App\Http\Controllers\Operator\OrganizationController as OperatorOrganizationController;
use App\Http\Controllers\Operator\PricingRuleController as OperatorPricingRuleController;
use App\Http\Controllers\Operator\SettlementController as OperatorSettlementController;
use Illuminate\Support\Facades\Route;

Route::get('/', MarketplaceHomeController::class)->name('home');
Route::get('regulamin', [LegalDocumentController::class, 'show'])
    ->defaults('slug', 'regulamin')
    ->name('legal.terms');
Route::get('polityka-prywatnosci', [LegalDocumentController::class, 'show'])
    ->defaults('slug', 'polityka-prywatnosci')
    ->name('legal.privacy');
Route::get('zasady-anulowania', [LegalDocumentController::class, 'show'])
    ->defaults('slug', 'zasady-anulowania')
    ->name('legal.cancellation');
Route::get('kontakt', [LegalDocumentController::class, 'show'])
    ->defaults('slug', 'kontakt')
    ->name('legal.contact');
Route::get('dla-operatorow', [LegalDocumentController::class, 'show'])
    ->defaults('slug', 'dla-operatorow')
    ->name('legal.operators');
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
    Route::get('konto/ulubione', [AccountFavoriteVenueController::class, 'index'])
        ->name('account.favorites.index');

    Route::post('lowiska/{fishingVenue:slug}/claim', [MarketplaceFishingVenueClaimController::class, 'store'])
        ->middleware('throttle:claims')
        ->name('marketplace.fishing-venues.claims.store');
    Route::post('lowiska/{fishingVenue:slug}/favorite', [MarketplaceFishingVenueFavoriteController::class, 'store'])
        ->name('marketplace.fishing-venues.favorites.store');
    Route::delete('lowiska/{fishingVenue:slug}/favorite', [MarketplaceFishingVenueFavoriteController::class, 'destroy'])
        ->name('marketplace.fishing-venues.favorites.destroy');
    Route::post('lowiska/{fishingVenue:slug}/reviews', [MarketplaceFishingVenueReviewController::class, 'store'])
        ->middleware('throttle:checkout')
        ->name('marketplace.fishing-venues.reviews.store');
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

        Route::get('reviews', [OperatorFishingVenueReviewController::class, 'index'])
            ->name('reviews.index');
        Route::patch('reviews/{fishingVenueReview}/response', [OperatorFishingVenueReviewController::class, 'respond'])
            ->name('reviews.response');

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
        Route::get('users', [AdminUserController::class, 'index'])
            ->name('users.index');
        Route::get('users/export', [AdminUserController::class, 'export'])
            ->name('users.export');

        Route::get('operators', [AdminOperatorController::class, 'index'])
            ->name('operators.index');
        Route::get('operators/export', [AdminOperatorController::class, 'export'])
            ->name('operators.export');

        Route::get('organizations', [AdminOrganizationController::class, 'index'])
            ->name('organizations.index');
        Route::get('organizations/export', [AdminOrganizationController::class, 'export'])
            ->name('organizations.export');

        Route::get('fishing-venues', [AdminFishingVenueController::class, 'index'])
            ->name('fishing-venues.index');
        Route::get('fishing-venues/export', [AdminFishingVenueController::class, 'export'])
            ->name('fishing-venues.export');
        Route::patch('fishing-venues/{fishingVenue}', [AdminFishingVenueController::class, 'moderate'])
            ->name('fishing-venues.moderate');
        Route::patch('fishing-venues/media/{media}', [AdminFishingVenueController::class, 'moderateVenueMedia'])
            ->name('fishing-venues.media.moderate');
        Route::patch('fishing-venues/spot-media/{media}', [AdminFishingVenueController::class, 'moderateSpotMedia'])
            ->name('fishing-venues.spot-media.moderate');

        Route::get('bookings', [AdminBookingController::class, 'index'])
            ->name('bookings.index');
        Route::get('bookings/export', [AdminBookingController::class, 'export'])
            ->name('bookings.export');

        Route::get('payments', [AdminPaymentController::class, 'index'])
            ->name('payments.index');
        Route::get('payments/export', [AdminPaymentController::class, 'export'])
            ->name('payments.export');

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

        Route::get('reviews', [AdminFishingVenueReviewController::class, 'index'])
            ->name('reviews.index');
        Route::patch('reviews/{fishingVenueReview}', [AdminFishingVenueReviewController::class, 'moderate'])
            ->name('reviews.moderate');

        Route::get('venue-claims', [AdminFishingVenueClaimController::class, 'index'])
            ->name('venue-claims.index');
        Route::patch('venue-claims/{fishingVenueClaim}', [AdminFishingVenueClaimController::class, 'review'])
            ->name('venue-claims.review');
    });

require __DIR__.'/settings.php';
