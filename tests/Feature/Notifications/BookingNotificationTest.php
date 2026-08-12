<?php

use App\Jobs\ExpireBookingHoldsJob;
use App\Jobs\ReconcilePaymentsJob;
use App\Jobs\SendBookingReminderNotificationsJob;
use App\Models\User;
use App\Modules\Bookings\Actions\CancelBooking;
use App\Modules\Bookings\Enums\BookingPriceLineType;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Enums\ReservationStatus;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Bookings\Models\BookingNotificationLog;
use App\Modules\Fisheries\Enums\FishingVenueCommercialStatus;
use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Payments\Actions\ConfirmBookingAfterPayment;
use App\Modules\Payments\Actions\CreateBookingPayment;
use App\Modules\Payments\Actions\HandlePaymentWebhook;
use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentReconciliationRun;
use App\Modules\Payments\PaymentGatewayManager;
use App\Notifications\Bookings\BookingCancelledNotification;
use App\Notifications\Bookings\BookingConfirmedNotification;
use App\Notifications\Bookings\BookingPaymentPendingNotification;
use App\Notifications\Bookings\BookingReminderNotification;
use App\Notifications\Bookings\PaymentFailedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

afterEach(function (): void {
    Carbon::setTestNow();
});

test('booking payment creation dispatches pending payment notification once', function () {
    Notification::fake();
    $customer = User::factory()->create();
    $booking = phase14Booking($customer, BookingStatus::PaymentPending, ReservationStatus::Hold, '2026-08-20 10:00:00');

    app(CreateBookingPayment::class)->handle($booking);
    app(CreateBookingPayment::class)->handle($booking);

    Notification::assertSentToTimes($customer, BookingPaymentPendingNotification::class, 1);

    expect(BookingNotificationLog::query()->count())->toBe(1);
});

test('payment success, failure and cancellation dispatch booking notifications', function () {
    Notification::fake();
    $customer = User::factory()->create();

    $confirmedBooking = phase14Booking($customer, BookingStatus::PaymentPending, ReservationStatus::Hold, '2026-08-20 10:00:00');
    app(ConfirmBookingAfterPayment::class)->handle($confirmedBooking);

    Notification::assertSentTo($customer, BookingConfirmedNotification::class);

    $failedBooking = phase14Booking($customer, BookingStatus::PaymentPending, ReservationStatus::Hold, '2026-08-21 10:00:00');
    $payment = phase14Payment($failedBooking, PaymentStatus::Created);

    app(HandlePaymentWebhook::class)->handle(
        gateway: app(PaymentGatewayManager::class)->for(PaymentProvider::Test->value),
        payload: [
            'provider_event_id' => 'test_evt_'.Str::uuid(),
            'event_type' => 'payment.failed',
            'provider_payment_id' => $payment->provider_payment_id,
            'status' => PaymentStatus::Failed->value,
        ],
    );

    Notification::assertSentTo($customer, PaymentFailedNotification::class);

    $cancelledBooking = phase14Booking($customer, BookingStatus::Confirmed, ReservationStatus::Confirmed, '2026-08-22 10:00:00');
    phase14Payment($cancelledBooking, PaymentStatus::Succeeded);

    app(CancelBooking::class)->handle($cancelledBooking, $customer, CancelBooking::ACTOR_ANGLER);

    Notification::assertSentTo($customer, BookingCancelledNotification::class);
});

test('booking reminder job sends reminders idempotently', function () {
    Notification::fake();
    Carbon::setTestNow('2026-08-12 10:00:00');

    $customer = User::factory()->create();
    phase14Booking($customer, BookingStatus::Confirmed, ReservationStatus::Confirmed, '2026-08-13 10:30:00');
    phase14Booking($customer, BookingStatus::Confirmed, ReservationStatus::Confirmed, '2026-08-13 13:00:00');

    $job = new SendBookingReminderNotificationsJob(24, 60);
    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);

    Notification::assertSentToTimes($customer, BookingReminderNotification::class, 1);

    expect(BookingNotificationLog::query()->where('type', 'booking.reminder')->count())->toBe(1);
});

test('expire holds and reconciliation jobs run idempotent domain actions', function () {
    Carbon::setTestNow('2026-08-12 10:00:00');
    $booking = phase14Booking(User::factory()->create(), BookingStatus::PaymentPending, ReservationStatus::Hold, '2026-08-13 10:00:00');
    $reservation = $booking->spotReservations()->firstOrFail();
    $reservation->update(['hold_expires_at' => now()->subMinute()]);

    app()->call([new ExpireBookingHoldsJob, 'handle']);

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Expired);

    $staleBooking = phase14Booking(User::factory()->create(), BookingStatus::PaymentPending, ReservationStatus::Hold, '2026-08-14 10:00:00');
    $payment = phase14Payment($staleBooking, PaymentStatus::Created);
    $payment->forceFill(['created_at' => now()->subHour()])->save();

    app()->call([new ReconcilePaymentsJob(PaymentProvider::Test->value, 30), 'handle']);

    expect(PaymentReconciliationRun::query()->count())->toBe(1);
});

test('scheduler lists phase fourteen recurring jobs', function () {
    Artisan::call('schedule:list');
    $output = Artisan::output();

    expect($output)
        ->toContain('bookings.expire-holds')
        ->toContain('payments.reconcile-active-provider')
        ->toContain('bookings.send-reminders');
});

function phase14Booking(User $customer, BookingStatus $bookingStatus, ReservationStatus $reservationStatus, string $startsAt): Booking
{
    $venue = FishingVenue::factory()->create([
        'organization_id' => Organization::factory(),
        'status' => FishingVenueStatus::Published,
        'published_at' => now(),
        'commercial_status' => FishingVenueCommercialStatus::Bookable,
    ]);
    $spot = FishingSpot::factory()->create([
        'fishing_venue_id' => $venue->id,
        'base_price_amount' => 10000,
        'currency' => 'PLN',
    ]);

    $booking = Booking::create([
        'user_id' => $customer->id,
        'organization_id' => $venue->organization_id,
        'fishing_venue_id' => $venue->id,
        'status' => $bookingStatus,
        'currency' => 'PLN',
        'subtotal_amount' => 10000,
        'discount_amount' => 0,
        'total_amount' => 10000,
        'commission_bps' => 0,
        'platform_fee_amount' => 0,
        'operator_amount' => 10000,
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'confirmed_at' => $bookingStatus === BookingStatus::Confirmed ? now() : null,
    ]);

    $booking->spotReservations()->create([
        'fishing_spot_id' => $spot->id,
        'starts_at' => Carbon::parse($startsAt),
        'ends_at' => Carbon::parse($startsAt)->addHours(2),
        'status' => $reservationStatus,
        'hold_expires_at' => $reservationStatus === ReservationStatus::Hold ? now()->addMinutes(10) : null,
    ]);

    $booking->priceLines()->create([
        'type' => BookingPriceLineType::Spot,
        'description' => 'Stanowisko testowe',
        'quantity' => 1,
        'unit_amount' => 10000,
        'total_amount' => 10000,
    ]);

    return $booking->load(['spotReservations.spot', 'venue']);
}

function phase14Payment(Booking $booking, PaymentStatus $status): Payment
{
    return $booking->payments()->create([
        'provider' => PaymentProvider::Test,
        'provider_payment_id' => 'test_pay_'.Str::uuid(),
        'status' => $status,
        'amount' => 10000,
        'currency' => 'PLN',
        'idempotency_key' => 'phase14-payment-'.Str::uuid(),
        'paid_at' => $status === PaymentStatus::Succeeded ? now() : null,
        'failed_at' => $status === PaymentStatus::Failed ? now() : null,
    ]);
}
