<?php

namespace App\Providers;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\GovernmentData\Models\FishingVenueClaim;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Payments\Clients\PayUMarketplaceHttpClient;
use App\Modules\Payments\Clients\StripeConnectHttpClient;
use App\Modules\Payments\Contracts\PayUMarketplaceClient;
use App\Modules\Payments\Contracts\StripeConnectClient;
use App\Modules\Payments\Models\Payment;
use App\Modules\Reviews\Models\FishingVenueReview;
use App\Policies\AuditLogPolicy;
use App\Policies\BookingPolicy;
use App\Policies\FishingSpotPolicy;
use App\Policies\FishingVenueClaimPolicy;
use App\Policies\FishingVenuePolicy;
use App\Policies\FishingVenueReviewPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\PaymentPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(StripeConnectClient::class, StripeConnectHttpClient::class);
        $this->app->bind(PayUMarketplaceClient::class, PayUMarketplaceHttpClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        $this->registerPolicies();

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );

        $this->configureRateLimits();
    }

    /**
     * Register authorization policies for module-owned models.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::policy(FishingSpot::class, FishingSpotPolicy::class);
        Gate::policy(FishingVenue::class, FishingVenuePolicy::class);
        Gate::policy(FishingVenueClaim::class, FishingVenueClaimPolicy::class);
        Gate::policy(FishingVenueReview::class, FishingVenueReviewPolicy::class);
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
    }

    /**
     * Configure named rate limits for sensitive application flows.
     */
    protected function configureRateLimits(): void
    {
        RateLimiter::for('public-api', fn (Request $request) => Limit::perMinute(120)->by($this->rateLimitKey($request)));

        RateLimiter::for('booking-api', fn (Request $request) => Limit::perMinute(20)->by($this->rateLimitKey($request)));

        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)->by($this->rateLimitKey($request)));

        RateLimiter::for('claims', fn (Request $request) => Limit::perMinute(5)->by($this->rateLimitKey($request)));

        RateLimiter::for('admin', fn (Request $request) => Limit::perMinute(120)->by($this->rateLimitKey($request)));

        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(240)->by($request->ip() ?? 'webhook'));
    }

    private function rateLimitKey(Request $request): string
    {
        return (string) ($request->user()?->getAuthIdentifier() ?? $request->ip() ?? 'guest');
    }
}
