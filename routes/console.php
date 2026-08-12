<?php

use App\Jobs\ExpireBookingHoldsJob;
use App\Jobs\ReconcilePaymentsJob;
use App\Jobs\SendBookingReminderNotificationsJob;
use App\Jobs\SyncGovernmentDataJob;
use App\Modules\Bookings\Actions\ExpireBookingHolds;
use App\Modules\GovernmentData\Actions\ImportGovernmentVenueRecords;
use App\Modules\GovernmentData\Actions\SyncDaneGovPlFishingDistricts;
use App\Modules\GovernmentData\Enums\GovernmentDataSyncRunStatus;
use App\Modules\GovernmentData\Models\DataSource;
use App\Modules\Payments\Actions\ReconcilePayments;
use App\Modules\Payments\Enums\PaymentProvider;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command(
    'bookings:expire-holds',
    function (ExpireBookingHolds $expireBookingHolds): int {
        $expiredCount = $expireBookingHolds->handle();

        $this->info("Wygaszono {$expiredCount} holdow.");

        return 0;
    }
)->purpose('Expire stale booking holds');

Schedule::job(new ExpireBookingHoldsJob)
    ->name('bookings.expire-holds')
    ->everyMinute()
    ->withoutOverlapping();

Artisan::command(
    'government-data:sync {source_id : ID źródła danych} {path : Ścieżka do pliku JSON z rekordami}',
    function (string $source_id, string $path, ImportGovernmentVenueRecords $importer): int {
        $contents = file_get_contents($path);

        if ($contents === false) {
            $this->error("Nie można odczytać pliku: {$path}");

            return 1;
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->error("Nieprawidłowy JSON: {$exception->getMessage()}");

            return 1;
        }

        $records = is_array($decoded) && array_key_exists('records', $decoded) ? $decoded['records'] : $decoded;

        if (! is_array($records) || ! array_is_list($records)) {
            $this->error('Plik musi zawierać listę rekordów albo obiekt z polem records.');

            return 1;
        }

        $source = DataSource::query()->findOrFail((int) $source_id);
        $result = $importer->handle($source, $records);

        $this->info(
            "Przetworzono {$result['records']} rekordów. Utworzono {$result['venues_created']} łowisk, zaktualizowano {$result['venues_updated']}."
        );

        return 0;
    }
)->purpose('Import normalized public fishing venue records from JSON');

Artisan::command(
    'government-data:sync-real {--limit= : Opcjonalny limit rekordów do pobrania z realnego źródła}',
    function (SyncDaneGovPlFishingDistricts $sync): int {
        $limit = $this->option('limit');
        $run = $sync->handle(is_numeric($limit) ? (int) $limit : null);

        if ($run->status === GovernmentDataSyncRunStatus::Failed) {
            $this->error("Sync danych publicznych nieudany: {$run->error}");

            return 1;
        }

        $this->info(
            "Sync #{$run->id}: pobrano {$run->records_seen}, do decyzji {$run->records_needing_review}, kandydaci {$run->records_with_candidates}."
        );

        return 0;
    }
)->purpose('Sync real public fishing venue records from dane.gov.pl');

Artisan::command(
    'payments:reconcile {provider=test : Provider płatności} {--stale-minutes=30 : Po ilu minutach payment CREATED/PROCESSING wymaga review}',
    function (string $provider, ReconcilePayments $reconcilePayments): int {
        if (! PaymentProvider::tryFrom($provider) instanceof PaymentProvider) {
            $this->error("Nieobsługiwany provider reconciliation: {$provider}");

            return 1;
        }

        $staleMinutes = (int) $this->option('stale-minutes');
        $run = $reconcilePayments->handle($provider, $staleMinutes);

        $this->info(
            "Reconciliation #{$run->id}: sprawdzono {$run->checked_count}, niespójności {$run->mismatch_count}."
        );

        return $run->mismatch_count > 0 ? 2 : 0;
    }
)->purpose('Detect local payment and booking state mismatches');

Artisan::command(
    'bookings:send-reminders {--hours=24 : Ile godzin przed rozpoczęciem wysłać przypomnienie} {--window-minutes=60 : Szerokość okna wysyłki w minutach}',
    function (): int {
        $job = new SendBookingReminderNotificationsJob(
            hoursBeforeStart: (int) $this->option('hours'),
            windowMinutes: (int) $this->option('window-minutes'),
        );

        app()->call([$job, 'handle']);

        $this->info('Przypomnienia o rezerwacjach zostały przetworzone.');

        return 0;
    }
)->purpose('Send booking reminder notifications for upcoming confirmed reservations');

Schedule::job(new ReconcilePaymentsJob(null, 30))
    ->name('payments.reconcile-active-provider')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::job(new SendBookingReminderNotificationsJob(24, 60))
    ->name('bookings.send-reminders')
    ->hourly()
    ->withoutOverlapping();

Schedule::job(new SyncGovernmentDataJob)
    ->name('government-data.sync-real')
    ->weeklyOn(1, '03:30')
    ->withoutOverlapping();
