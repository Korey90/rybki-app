<?php

namespace Database\Seeders;

use App\Enums\UserAccountType;
use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Availability\Models\AvailabilityBlock;
use App\Modules\Availability\Models\AvailabilityRule;
use App\Modules\Bookings\Enums\BookingPriceLineType;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Enums\ReservationStatus;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Bookings\Models\BookingPriceLine;
use App\Modules\Bookings\Models\CancellationPolicy;
use App\Modules\Bookings\Models\SpotReservation;
use App\Modules\Fisheries\Enums\FishingSpotStatus;
use App\Modules\Fisheries\Enums\FishingVenueCommercialStatus;
use App\Modules\Fisheries\Enums\FishingVenueMediaType;
use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Enums\FishingVenueType;
use App\Modules\Fisheries\Models\Amenity;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Fisheries\Models\FishingVenueMedia;
use App\Modules\Fisheries\Models\FishSpecies;
use App\Modules\GovernmentData\Enums\FishingVenueClaimStatus;
use App\Modules\GovernmentData\Enums\GovernmentVenueRecordStatus;
use App\Modules\GovernmentData\Models\DataSource;
use App\Modules\GovernmentData\Models\FishingVenueClaim;
use App\Modules\GovernmentData\Models\FishingVenueSource;
use App\Modules\GovernmentData\Models\GovernmentVenueRecord;
use App\Modules\Organizations\Enums\OrganizationMemberRole;
use App\Modules\Organizations\Enums\OrganizationVerificationStatus;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Enums\PaymentReconciliationItemStatus;
use App\Modules\Payments\Enums\PaymentReconciliationRunStatus;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Enums\ProviderAccountOnboardingStatus;
use App\Modules\Payments\Enums\RefundStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentReconciliationItem;
use App\Modules\Payments\Models\PaymentReconciliationRun;
use App\Modules\Payments\Models\PaymentWebhookEvent;
use App\Modules\Payments\Models\ProviderAccount;
use App\Modules\Payments\Models\Refund;
use App\Modules\Pricing\Models\PricingRule;
use App\Modules\Settlements\Actions\GenerateSettlementPeriod;
use App\Modules\Settlements\PlatformCommissionSettings;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $users = $this->seedUsers();
            $this->seedPlatformCommission($users);
            $species = $this->seedFishSpecies();
            $amenities = $this->seedAmenities();
            $organizations = $this->seedOrganizations($users);
            $venues = $this->seedVenues($organizations, $species, $amenities);

            $this->seedOperatorConfiguration($organizations);
            $payments = $this->seedBookings($users, $venues);
            $this->seedSettlements($organizations);
            $this->seedGovernmentData($users, $organizations, $venues);
            $this->seedReconciliation($payments);
            $this->seedAuditLogs($users, $organizations, $venues);
        });
    }

    /**
     * @return array<string, User>
     */
    private function seedUsers(): array
    {
        return [
            'admin' => $this->upsertUser('Admin Rybki', 'admin@rybki.test', UserAccountType::VenueOwner, true, '+48500000001'),
            'owner' => $this->upsertUser('Anna Operator', 'owner@rybki.test', UserAccountType::VenueOwner, false, '+48500000002'),
            'manager' => $this->upsertUser('Marek Manager', 'manager@rybki.test', UserAccountType::VenueOwner, false, '+48500000003'),
            'owner_south' => $this->upsertUser('Karol Właściciel', 'owner2@rybki.test', UserAccountType::VenueOwner, false, '+48500000004'),
            'angler' => $this->upsertUser('Jan Wędkarz', 'angler@rybki.test', UserAccountType::Angler, false, '+48500000005'),
            'angler_two' => $this->upsertUser('Ewa Wędkarz', 'angler2@rybki.test', UserAccountType::Angler, false, '+48500000006'),
        ];
    }

    private function upsertUser(string $name, string $email, UserAccountType $type, bool $isAdmin, string $phone): User
    {
        /** @var User $user */
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'phone' => $phone,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
                'account_type' => $type,
                'is_admin' => $isAdmin,
                'password' => Hash::make('password'),
            ],
        );

        return $user;
    }

    /**
     * @param  array<string, User>  $users
     */
    private function seedPlatformCommission(array $users): void
    {
        app(PlatformCommissionSettings::class)->setDefaultCommissionBps(1000, $users['admin']);
    }

    /**
     * @return array<string, FishSpecies>
     */
    private function seedFishSpecies(): array
    {
        $items = [
            'karp' => 'Karp',
            'amur' => 'Amur',
            'szczupak' => 'Szczupak',
            'sandacz' => 'Sandacz',
            'lin' => 'Lin',
            'sum' => 'Sum',
        ];

        $species = [];

        foreach ($items as $slug => $name) {
            /** @var FishSpecies $fishSpecies */
            $fishSpecies = FishSpecies::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name],
            );

            $species[$slug] = $fishSpecies;
        }

        return $species;
    }

    /**
     * @return array<string, Amenity>
     */
    private function seedAmenities(): array
    {
        $items = [
            'parking' => ['name' => 'Parking', 'icon' => 'parking-circle'],
            'pomosty' => ['name' => 'Pomosty', 'icon' => 'waves'],
            'nocleg' => ['name' => 'Nocleg', 'icon' => 'bed'],
            'sprzet' => ['name' => 'Wypożyczalnia sprzętu', 'icon' => 'briefcase'],
            'gastronomia' => ['name' => 'Gastronomia', 'icon' => 'utensils'],
            'toalety' => ['name' => 'Toalety', 'icon' => 'badge-info'],
        ];

        $amenities = [];

        foreach ($items as $slug => $attributes) {
            /** @var Amenity $amenity */
            $amenity = Amenity::query()->updateOrCreate(
                ['slug' => $slug],
                $attributes,
            );

            $amenities[$slug] = $amenity;
        }

        return $amenities;
    }

    /**
     * @param  array<string, User>  $users
     * @return array<string, Organization>
     */
    private function seedOrganizations(array $users): array
    {
        /** @var Organization $mazury */
        $mazury = Organization::query()->updateOrCreate(
            ['tax_id' => '5250000101'],
            [
                'name' => 'Mazurskie Łowiska',
                'legal_name' => 'Mazurskie Łowiska Sp. z o.o.',
                'regon' => '100000101',
                'country' => 'PL',
                'address' => 'ul. Jeziorna 12, 11-500 Giżycko',
                'verification_status' => OrganizationVerificationStatus::Verified,
            ],
        );

        /** @var Organization $south */
        $south = Organization::query()->updateOrCreate(
            ['tax_id' => '5250000202'],
            [
                'name' => 'Południowe Stawy',
                'legal_name' => 'Południowe Stawy Karol Nowak',
                'regon' => '100000202',
                'country' => 'PL',
                'address' => 'ul. Lipowa 7, 32-020 Wieliczka',
                'verification_status' => OrganizationVerificationStatus::Pending,
            ],
        );

        $this->attachMember($mazury, $users['owner'], OrganizationMemberRole::Owner);
        $this->attachMember($mazury, $users['manager'], OrganizationMemberRole::Manager);
        $this->attachMember($south, $users['owner_south'], OrganizationMemberRole::Owner);

        return [
            'mazury' => $mazury,
            'south' => $south,
        ];
    }

    private function attachMember(Organization $organization, User $user, OrganizationMemberRole $role): void
    {
        OrganizationMember::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
            ],
            ['role' => $role],
        );
    }

    /**
     * @param  array<string, Organization>  $organizations
     * @param  array<string, FishSpecies>  $species
     * @param  array<string, Amenity>  $amenities
     * @return array<string, FishingVenue>
     */
    private function seedVenues(array $organizations, array $species, array $amenities): array
    {
        $mazurskaZatoka = $this->upsertVenue($organizations['mazury'], [
            'name' => 'Łowisko Mazurska Zatoka',
            'slug' => 'lowisko-mazurska-zatoka',
            'description' => 'Komercyjne łowisko z pomostami, stanowiskami karpiowymi i spokojną zatoką na całodniowe zasiadki.',
            'venue_type' => FishingVenueType::Private,
            'commercial_status' => FishingVenueCommercialStatus::Bookable,
            'latitude' => '54.0323400',
            'longitude' => '21.7662100',
            'address' => 'ul. Jeziorna 12',
            'city' => 'Giżycko',
            'postal_code' => '11-500',
            'voivodeship' => 'warmińsko-mazurskie',
            'phone' => '+48500111222',
            'email' => 'kontakt@mazurskazatoka.test',
            'website' => 'https://mazurskazatoka.test',
            'rules' => 'Obowiązuje mata karpiowa, haczyki bezzadziorowe i zasada złów i wypuść dla ryb powyżej 5 kg.',
            'check_in_instructions' => 'Po przyjeździe zgłoś się w recepcji przy parkingu głównym.',
            'status' => FishingVenueStatus::Published,
            'published_at' => now()->subDays(18),
        ]);
        $this->syncVenueTaxonomy($mazurskaZatoka, $species, ['karp', 'amur', 'szczupak', 'lin'], $amenities, ['parking', 'pomosty', 'nocleg', 'toalety']);
        $this->seedVenueMedia($mazurskaZatoka, 'mazurska-zatoka');
        $this->seedSpots($mazurskaZatoka, [
            ['name' => 'Pomost A1', 'description' => 'Szeroki pomost z wygodnym dojściem i miejscem na dwa zestawy.', 'capacity' => 2, 'price' => 9000, 'sort' => 1],
            ['name' => 'Zatoka Ciszy', 'description' => 'Stanowisko osłonięte trzcinami, dobre na karpia i lina.', 'capacity' => 2, 'price' => 12000, 'sort' => 2],
            ['name' => 'Brzeg Północny', 'description' => 'Naturalny brzeg z dużą przestrzenią dla wędkarza.', 'capacity' => 1, 'price' => 7000, 'sort' => 3],
        ]);

        $lesneStawy = $this->upsertVenue($organizations['mazury'], [
            'name' => 'Leśne Stawy Premium',
            'slug' => 'lesne-stawy-premium',
            'description' => 'Kameralne stawy w lesie z limitem miejsc, nocnymi rezerwacjami i oddzielną strefą spinningową.',
            'venue_type' => FishingVenueType::Private,
            'commercial_status' => FishingVenueCommercialStatus::Bookable,
            'latitude' => '53.7894000',
            'longitude' => '21.5701200',
            'address' => 'Leśna Droga 4',
            'city' => 'Mikołajki',
            'postal_code' => '11-730',
            'voivodeship' => 'warmińsko-mazurskie',
            'phone' => '+48500333444',
            'email' => 'rezerwacje@lesnestawy.test',
            'website' => 'https://lesnestawy.test',
            'rules' => 'Rezerwacje nocne wymagają oświetlenia stanowiska i wcześniejszego potwierdzenia obecności.',
            'check_in_instructions' => 'Kod do bramy zostanie przekazany po potwierdzeniu płatności.',
            'status' => FishingVenueStatus::Published,
            'published_at' => now()->subDays(9),
        ]);
        $this->syncVenueTaxonomy($lesneStawy, $species, ['karp', 'sum', 'sandacz'], $amenities, ['parking', 'sprzet', 'gastronomia', 'toalety']);
        $this->seedVenueMedia($lesneStawy, 'lesne-stawy');
        $this->seedSpots($lesneStawy, [
            ['name' => 'Stanowisko Nocne 1', 'description' => 'Stanowisko z miejscem na namiot i podest.', 'capacity' => 2, 'price' => 15000, 'sort' => 1],
            ['name' => 'Spinning 3', 'description' => 'Krótkie rezerwacje dla spinningistów.', 'capacity' => 1, 'price' => 8000, 'sort' => 2],
        ]);

        $karpiowyZakatek = $this->upsertVenue($organizations['south'], [
            'name' => 'Karpiowy Zakątek',
            'slug' => 'karpiowy-zakatek',
            'description' => 'Rodzinne łowisko karpiowe pod Krakowem z prostym regulaminem i wygodnym parkingiem.',
            'venue_type' => FishingVenueType::Private,
            'commercial_status' => FishingVenueCommercialStatus::Bookable,
            'latitude' => '49.9862500',
            'longitude' => '20.0643400',
            'address' => 'ul. Lipowa 7',
            'city' => 'Wieliczka',
            'postal_code' => '32-020',
            'voivodeship' => 'małopolskie',
            'phone' => '+48500555666',
            'email' => 'kontakt@karpiowyzakatek.test',
            'website' => 'https://karpiowyzakatek.test',
            'rules' => 'Zakaz zanęcania ziarnem bez wcześniejszego przygotowania. Obowiązkowy podbierak.',
            'check_in_instructions' => 'Wejście od strony parkingu przy budynku gospodarczym.',
            'status' => FishingVenueStatus::Published,
            'published_at' => now()->subDays(4),
        ]);
        $this->syncVenueTaxonomy($karpiowyZakatek, $species, ['karp', 'amur', 'lin'], $amenities, ['parking', 'pomosty', 'gastronomia']);
        $this->seedVenueMedia($karpiowyZakatek, 'karpiowy-zakatek');
        $this->seedSpots($karpiowyZakatek, [
            ['name' => 'Karpiowe 1', 'description' => 'Najbliżej parkingu, dobre dla początkujących.', 'capacity' => 2, 'price' => 8500, 'sort' => 1],
            ['name' => 'Karpiowe 2', 'description' => 'Głębsza woda przy środku stawu.', 'capacity' => 2, 'price' => 9500, 'sort' => 2],
        ]);

        $publicVenue = $this->upsertVenue(null, [
            'name' => 'Jezioro Publiczne Wda',
            'slug' => 'jezioro-publiczne-wda',
            'description' => 'Profil informacyjny zaimportowany z danych publicznych. Operator może zgłosić zarządzanie tym łowiskiem.',
            'venue_type' => FishingVenueType::WodyPolskie,
            'commercial_status' => FishingVenueCommercialStatus::Claimable,
            'latitude' => '53.8774100',
            'longitude' => '18.1468600',
            'address' => 'okolice przystani gminnej',
            'city' => 'Wda',
            'postal_code' => '83-240',
            'voivodeship' => 'pomorskie',
            'phone' => null,
            'email' => null,
            'website' => 'https://dane-publiczne.example/wda',
            'rules' => 'Sprawdź aktualne przepisy lokalnego gospodarza wód przed połowem.',
            'check_in_instructions' => null,
            'status' => FishingVenueStatus::Published,
            'published_at' => now()->subDays(2),
        ]);
        $this->syncVenueTaxonomy($publicVenue, $species, ['szczupak', 'sandacz', 'lin'], $amenities, ['parking']);

        $additionalVenues = $this->seedAdditionalVenues($organizations, $species, $amenities);

        return [
            'mazurska_zatoka' => $mazurskaZatoka,
            'lesne_stawy' => $lesneStawy,
            'karpiowy_zakatek' => $karpiowyZakatek,
            'public_wda' => $publicVenue,
            ...$additionalVenues,
        ];
    }

    /**
     * @param  array<string, Organization>  $organizations
     * @param  array<string, FishSpecies>  $species
     * @param  array<string, Amenity>  $amenities
     * @return array<string, FishingVenue>
     */
    private function seedAdditionalVenues(array $organizations, array $species, array $amenities): array
    {
        $venueDefinitions = [
            [
                'key' => 'sosnowa_przystan',
                'organization' => 'mazury',
                'name' => 'Zalew Sosnowa Przystań',
                'slug' => 'zalew-sosnowa-przystan',
                'city' => 'Mrągowo',
                'postal_code' => '11-700',
                'voivodeship' => 'warmińsko-mazurskie',
                'latitude' => '53.8649200',
                'longitude' => '21.3055200',
                'price' => 8800,
                'species' => ['karp', 'amur', 'lin'],
                'amenities' => ['parking', 'pomosty', 'toalety'],
            ],
            [
                'key' => 'rybna_polana',
                'organization' => 'south',
                'name' => 'Stawy Rybna Polana',
                'slug' => 'stawy-rybna-polana',
                'city' => 'Niepołomice',
                'postal_code' => '32-005',
                'voivodeship' => 'małopolskie',
                'latitude' => '50.0401800',
                'longitude' => '20.2216500',
                'price' => 7600,
                'species' => ['karp', 'szczupak', 'lin'],
                'amenities' => ['parking', 'gastronomia', 'toalety'],
            ],
            [
                'key' => 'narew_24',
                'organization' => 'mazury',
                'name' => 'Łowisko Narew 24',
                'slug' => 'lowisko-narew-24',
                'city' => 'Łomża',
                'postal_code' => '18-400',
                'voivodeship' => 'podlaskie',
                'latitude' => '53.1778200',
                'longitude' => '22.0785700',
                'price' => 9900,
                'species' => ['sum', 'sandacz', 'szczupak'],
                'amenities' => ['parking', 'sprzet', 'toalety'],
            ],
            [
                'key' => 'ostoja_karpia',
                'organization' => 'south',
                'name' => 'Ostoja Karpia',
                'slug' => 'ostoja-karpia',
                'city' => 'Pszczyna',
                'postal_code' => '43-200',
                'voivodeship' => 'śląskie',
                'latitude' => '49.9788600',
                'longitude' => '18.9470800',
                'price' => 10500,
                'species' => ['karp', 'amur', 'sum'],
                'amenities' => ['parking', 'pomosty', 'nocleg'],
            ],
            [
                'key' => 'blekitny_zakret',
                'organization' => 'mazury',
                'name' => 'Błękitny Zakręt',
                'slug' => 'blekitny-zakret',
                'city' => 'Kościerzyna',
                'postal_code' => '83-400',
                'voivodeship' => 'pomorskie',
                'latitude' => '54.1229200',
                'longitude' => '17.9769800',
                'price' => 8200,
                'species' => ['szczupak', 'lin', 'sandacz'],
                'amenities' => ['parking', 'pomosty'],
            ],
            [
                'key' => 'cichy_brzeg',
                'organization' => 'south',
                'name' => 'Staw Cichy Brzeg',
                'slug' => 'staw-cichy-brzeg',
                'city' => 'Busko-Zdrój',
                'postal_code' => '28-100',
                'voivodeship' => 'świętokrzyskie',
                'latitude' => '50.4702200',
                'longitude' => '20.7185400',
                'price' => 6900,
                'species' => ['karp', 'lin', 'amur'],
                'amenities' => ['parking', 'toalety'],
            ],
            [
                'key' => 'pod_lasem',
                'organization' => 'mazury',
                'name' => 'Łowisko Pod Lasem',
                'slug' => 'lowisko-pod-lasem',
                'city' => 'Tuchola',
                'postal_code' => '89-500',
                'voivodeship' => 'kujawsko-pomorskie',
                'latitude' => '53.5878500',
                'longitude' => '17.8593200',
                'price' => 7400,
                'species' => ['karp', 'szczupak', 'sum'],
                'amenities' => ['parking', 'sprzet', 'toalety'],
            ],
            [
                'key' => 'dolina_amura',
                'organization' => 'south',
                'name' => 'Dolina Amura',
                'slug' => 'dolina-amura',
                'city' => 'Rzeszów',
                'postal_code' => '35-001',
                'voivodeship' => 'podkarpackie',
                'latitude' => '50.0413200',
                'longitude' => '21.9990100',
                'price' => 9100,
                'species' => ['amur', 'karp', 'lin'],
                'amenities' => ['parking', 'pomosty', 'gastronomia'],
            ],
            [
                'key' => 'jezioro_brzozowe',
                'organization' => null,
                'name' => 'Jezioro Brzozowe',
                'slug' => 'jezioro-brzozowe',
                'city' => 'Łagów',
                'postal_code' => '66-220',
                'voivodeship' => 'lubuskie',
                'latitude' => '52.3347500',
                'longitude' => '15.2976500',
                'commercial_status' => FishingVenueCommercialStatus::Claimable,
                'venue_type' => FishingVenueType::Pzw,
                'species' => ['szczupak', 'sandacz', 'lin'],
                'amenities' => ['parking'],
            ],
            [
                'key' => 'stawy_lipowe',
                'organization' => 'mazury',
                'name' => 'Stawy Lipowe',
                'slug' => 'stawy-lipowe',
                'city' => 'Kórnik',
                'postal_code' => '62-035',
                'voivodeship' => 'wielkopolskie',
                'latitude' => '52.2478800',
                'longitude' => '17.0892900',
                'price' => 7900,
                'species' => ['karp', 'amur', 'lin'],
                'amenities' => ['parking', 'gastronomia', 'toalety'],
            ],
            [
                'key' => 'sandaczowa_rafa',
                'organization' => 'south',
                'name' => 'Łowisko Sandaczowa Rafa',
                'slug' => 'lowisko-sandaczowa-rafa',
                'city' => 'Drawsko Pomorskie',
                'postal_code' => '78-500',
                'voivodeship' => 'zachodniopomorskie',
                'latitude' => '53.5284500',
                'longitude' => '15.8093900',
                'price' => 11800,
                'species' => ['sandacz', 'szczupak', 'sum'],
                'amenities' => ['parking', 'sprzet', 'pomosty'],
            ],
            [
                'key' => 'karpikowo',
                'organization' => 'mazury',
                'name' => 'Karpikowo Rodzinne',
                'slug' => 'karpikowo-rodzinne',
                'city' => 'Zgierz',
                'postal_code' => '95-100',
                'voivodeship' => 'łódzkie',
                'latitude' => '51.8550600',
                'longitude' => '19.4068900',
                'price' => 6400,
                'species' => ['karp', 'amur', 'lin'],
                'amenities' => ['parking', 'gastronomia', 'toalety'],
            ],
            [
                'key' => 'sumowa_jama',
                'organization' => 'south',
                'name' => 'Sumowa Jama',
                'slug' => 'sumowa-jama',
                'city' => 'Opole',
                'postal_code' => '45-001',
                'voivodeship' => 'opolskie',
                'latitude' => '50.6751100',
                'longitude' => '17.9213000',
                'price' => 13200,
                'species' => ['sum', 'karp', 'sandacz'],
                'amenities' => ['parking', 'nocleg', 'sprzet'],
            ],
            [
                'key' => 'przy_mlynie',
                'organization' => 'mazury',
                'name' => 'Stawy Przy Młynie',
                'slug' => 'stawy-przy-mlynie',
                'city' => 'Płońsk',
                'postal_code' => '09-100',
                'voivodeship' => 'mazowieckie',
                'latitude' => '52.6232300',
                'longitude' => '20.3755200',
                'price' => 7200,
                'species' => ['karp', 'szczupak', 'lin'],
                'amenities' => ['parking', 'pomosty', 'toalety'],
            ],
            [
                'key' => 'zielona_grobla',
                'organization' => 'south',
                'name' => 'Łowisko Zielona Grobla',
                'slug' => 'lowisko-zielona-grobla',
                'city' => 'Milicz',
                'postal_code' => '56-300',
                'voivodeship' => 'dolnośląskie',
                'latitude' => '51.5278700',
                'longitude' => '17.2713700',
                'price' => 8400,
                'species' => ['karp', 'amur', 'szczupak'],
                'amenities' => ['parking', 'pomosty', 'sprzet'],
            ],
            [
                'key' => 'jezioro_debowe',
                'organization' => null,
                'name' => 'Jezioro Dębowe',
                'slug' => 'jezioro-debowe',
                'city' => 'Augustów',
                'postal_code' => '16-300',
                'voivodeship' => 'podlaskie',
                'latitude' => '53.8431900',
                'longitude' => '22.9797900',
                'commercial_status' => FishingVenueCommercialStatus::InformationOnly,
                'venue_type' => FishingVenueType::WodyPolskie,
                'species' => ['szczupak', 'sandacz', 'lin'],
                'amenities' => ['parking'],
            ],
            [
                'key' => 'kanal_notecki',
                'organization' => null,
                'name' => 'Kanał Notecki',
                'slug' => 'kanal-notecki',
                'city' => 'Nakło nad Notecią',
                'postal_code' => '89-100',
                'voivodeship' => 'kujawsko-pomorskie',
                'latitude' => '53.1395200',
                'longitude' => '17.6017900',
                'commercial_status' => FishingVenueCommercialStatus::Claimable,
                'venue_type' => FishingVenueType::Pzw,
                'species' => ['sandacz', 'sum', 'szczupak'],
                'amenities' => ['parking'],
            ],
            [
                'key' => 'staw_gminny_brzeziny',
                'organization' => null,
                'name' => 'Staw Gminny Brzeziny',
                'slug' => 'staw-gminny-brzeziny',
                'city' => 'Brzeziny',
                'postal_code' => '95-060',
                'voivodeship' => 'łódzkie',
                'latitude' => '51.8006900',
                'longitude' => '19.7516200',
                'commercial_status' => FishingVenueCommercialStatus::InformationOnly,
                'venue_type' => FishingVenueType::OtherPublic,
                'species' => ['karp', 'lin'],
                'amenities' => ['parking', 'toalety'],
            ],
            [
                'key' => 'wierzby',
                'organization' => 'mazury',
                'name' => 'Łowisko Wierzby',
                'slug' => 'lowisko-wierzby',
                'city' => 'Nałęczów',
                'postal_code' => '24-150',
                'voivodeship' => 'lubelskie',
                'latitude' => '51.2857700',
                'longitude' => '22.2154100',
                'price' => 7300,
                'species' => ['karp', 'lin', 'amur'],
                'amenities' => ['parking', 'pomosty'],
            ],
            [
                'key' => 'przystan_nad_warta',
                'organization' => 'south',
                'name' => 'Rybna Przystań nad Wartą',
                'slug' => 'rybna-przystan-nad-warta',
                'city' => 'Sieradz',
                'postal_code' => '98-200',
                'voivodeship' => 'łódzkie',
                'latitude' => '51.5958400',
                'longitude' => '18.7302300',
                'price' => 8600,
                'species' => ['sum', 'szczupak', 'karp'],
                'amenities' => ['parking', 'sprzet', 'toalety'],
            ],
            [
                'key' => 'zalew_krolewski',
                'organization' => null,
                'name' => 'Zalew Królewski',
                'slug' => 'zalew-krolewski',
                'city' => 'Kozienice',
                'postal_code' => '26-900',
                'voivodeship' => 'mazowieckie',
                'latitude' => '51.5903200',
                'longitude' => '21.5479800',
                'commercial_status' => FishingVenueCommercialStatus::Claimable,
                'venue_type' => FishingVenueType::WodyPolskie,
                'species' => ['szczupak', 'sandacz', 'sum'],
                'amenities' => ['parking', 'pomosty'],
            ],
            [
                'key' => 'sielawa_park',
                'organization' => 'mazury',
                'name' => 'Sielawa Park',
                'slug' => 'sielawa-park',
                'city' => 'Ełk',
                'postal_code' => '19-300',
                'voivodeship' => 'warmińsko-mazurskie',
                'latitude' => '53.8282400',
                'longitude' => '22.3646900',
                'price' => 9700,
                'species' => ['sandacz', 'szczupak', 'lin'],
                'amenities' => ['parking', 'nocleg', 'gastronomia'],
            ],
        ];

        $venues = [];

        foreach ($venueDefinitions as $definition) {
            $organizationKey = $definition['organization'];
            $organization = is_string($organizationKey) ? $organizations[$organizationKey] : null;
            $commercialStatus = $definition['commercial_status'] ?? FishingVenueCommercialStatus::Bookable;
            $venueType = $definition['venue_type'] ?? FishingVenueType::Private;

            $venue = $this->upsertVenue($organization, [
                'name' => $definition['name'],
                'slug' => $definition['slug'],
                'description' => $organization instanceof Organization
                    ? "Rezerwowalne łowisko demo w miejscowości {$definition['city']}. Dane służą do testowania katalogu, filtrów i panelu operatora."
                    : "Publiczny profil demo w miejscowości {$definition['city']}. Dane służą do testowania katalogu i zgłoszeń operatorów.",
                'venue_type' => $venueType,
                'commercial_status' => $commercialStatus,
                'latitude' => $definition['latitude'],
                'longitude' => $definition['longitude'],
                'address' => 'adres demonstracyjny',
                'city' => $definition['city'],
                'postal_code' => $definition['postal_code'],
                'voivodeship' => $definition['voivodeship'],
                'phone' => $organization instanceof Organization ? '+48500999000' : null,
                'email' => $organization instanceof Organization ? "{$definition['slug']}@demo.rybki.test" : null,
                'website' => "https://{$definition['slug']}.demo.rybki.test",
                'rules' => $organization instanceof Organization
                    ? 'Regulamin demonstracyjny: obowiązuje porządek na stanowisku i zasada bezpiecznego obchodzenia się z rybami.'
                    : 'Profil informacyjny. Przed połowem sprawdź aktualne przepisy gospodarza wód.',
                'check_in_instructions' => $organization instanceof Organization
                    ? 'Po przyjeździe pokaż numer rezerwacji obsłudze łowiska.'
                    : null,
                'status' => FishingVenueStatus::Published,
                'published_at' => now()->subDays(30 - count($venues)),
            ]);

            $this->syncVenueTaxonomy($venue, $species, $definition['species'], $amenities, $definition['amenities']);
            $this->seedVenueMedia($venue, $definition['slug']);

            if ($organization instanceof Organization && isset($definition['price'])) {
                $this->seedSpots($venue, [
                    [
                        'name' => 'Stanowisko 1',
                        'description' => 'Podstawowe stanowisko demo z wygodnym dojściem do wody.',
                        'capacity' => 2,
                        'price' => $definition['price'],
                        'sort' => 1,
                    ],
                    [
                        'name' => 'Stanowisko 2',
                        'description' => 'Drugie stanowisko demo z większą przestrzenią na sprzęt.',
                        'capacity' => 2,
                        'price' => $definition['price'] + 1800,
                        'sort' => 2,
                    ],
                ]);
            }

            $venues[$definition['key']] = $venue;
        }

        return $venues;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertVenue(?Organization $organization, array $attributes): FishingVenue
    {
        /** @var FishingVenue $venue */
        $venue = FishingVenue::query()->updateOrCreate(
            ['slug' => $attributes['slug']],
            [
                ...$attributes,
                'organization_id' => $organization?->id,
                'country' => 'PL',
            ],
        );

        return $venue;
    }

    /**
     * @param  array<string, FishSpecies>  $species
     * @param  array<int, string>  $speciesSlugs
     * @param  array<string, Amenity>  $amenities
     * @param  array<int, string>  $amenitySlugs
     */
    private function syncVenueTaxonomy(
        FishingVenue $venue,
        array $species,
        array $speciesSlugs,
        array $amenities,
        array $amenitySlugs,
    ): void {
        $venue->fishSpecies()->sync(
            array_map(fn (string $slug): int => $species[$slug]->id, $speciesSlugs),
        );
        $venue->amenities()->sync(
            array_map(fn (string $slug): int => $amenities[$slug]->id, $amenitySlugs),
        );
    }

    private function seedVenueMedia(FishingVenue $venue, string $prefix): void
    {
        foreach ([1, 2] as $index) {
            FishingVenueMedia::query()->updateOrCreate(
                [
                    'fishing_venue_id' => $venue->id,
                    'storage_path' => "demo/venues/{$prefix}-{$index}.jpg",
                ],
                [
                    'type' => FishingVenueMediaType::Image,
                    'sort_order' => $index,
                ],
            );
        }
    }

    /**
     * @param  array<int, array{name: string, description: string, capacity: int, price: int, sort: int}>  $spots
     */
    private function seedSpots(FishingVenue $venue, array $spots): void
    {
        foreach ($spots as $spotData) {
            /** @var FishingSpot $spot */
            $spot = FishingSpot::query()->updateOrCreate(
                [
                    'fishing_venue_id' => $venue->id,
                    'name' => $spotData['name'],
                ],
                [
                    'description' => $spotData['description'],
                    'capacity' => $spotData['capacity'],
                    'base_price_amount' => $spotData['price'],
                    'currency' => 'PLN',
                    'status' => FishingSpotStatus::Active,
                    'sort_order' => $spotData['sort'],
                ],
            );

            $this->seedAvailabilityForSpot($spot);
            $this->seedPricingForSpot($spot, $spotData['price']);
        }
    }

    private function seedAvailabilityForSpot(FishingSpot $spot): void
    {
        foreach ([1, 2, 3, 4, 5, 6, 7] as $dayOfWeek) {
            AvailabilityRule::query()->updateOrCreate(
                [
                    'fishing_spot_id' => $spot->id,
                    'day_of_week' => $dayOfWeek,
                ],
                [
                    'opens_at' => $dayOfWeek >= 6 ? '05:00:00' : '06:00:00',
                    'closes_at' => $dayOfWeek >= 6 ? '22:00:00' : '20:00:00',
                    'valid_from' => now()->startOfMonth()->toDateString(),
                    'valid_until' => now()->addMonths(6)->endOfMonth()->toDateString(),
                    'minimum_booking_minutes' => 120,
                    'maximum_booking_minutes' => $dayOfWeek >= 6 ? 1440 : 720,
                ],
            );
        }

        AvailabilityBlock::query()->updateOrCreate(
            [
                'fishing_spot_id' => $spot->id,
                'starts_at' => now()->addDays(14)->setTime(8, 0)->toDateTimeString(),
            ],
            [
                'ends_at' => now()->addDays(14)->setTime(14, 0)->toDateTimeString(),
                'reason' => 'Przegląd stanowiska',
            ],
        );
    }

    private function seedPricingForSpot(FishingSpot $spot, int $basePrice): void
    {
        foreach ([6, 7] as $dayOfWeek) {
            PricingRule::query()->updateOrCreate(
                [
                    'fishing_spot_id' => $spot->id,
                    'day_of_week' => $dayOfWeek,
                    'priority' => 10,
                ],
                [
                    'price_amount' => (int) round($basePrice * 1.2),
                    'currency' => 'PLN',
                    'minimum_duration_minutes' => 240,
                    'starts_at' => null,
                    'ends_at' => null,
                ],
            );
        }
    }

    /**
     * @param  array<string, Organization>  $organizations
     */
    private function seedOperatorConfiguration(array $organizations): void
    {
        foreach ($organizations as $key => $organization) {
            CancellationPolicy::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'name' => 'Standardowa polityka',
                ],
                [
                    'free_cancellation_hours' => $key === 'mazury' ? 48 : 24,
                    'late_refund_percentage' => $key === 'mazury' ? 50 : 25,
                    'is_default' => true,
                ],
            );

            ProviderAccount::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'provider' => PaymentProvider::Test,
                ],
                [
                    'external_account_id' => "acct_demo_{$key}",
                    'onboarding_status' => $key === 'mazury'
                        ? ProviderAccountOnboardingStatus::Enabled
                        : ProviderAccountOnboardingStatus::Pending,
                    'payments_enabled' => true,
                    'payouts_enabled' => $key === 'mazury',
                    'last_synced_at' => now()->subHours(3),
                ],
            );

            foreach ([PaymentProvider::StripeConnect, PaymentProvider::PayUMarketplace] as $provider) {
                ProviderAccount::query()->updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'provider' => $provider,
                    ],
                    [
                        'external_account_id' => $provider === PaymentProvider::StripeConnect
                            ? "acct_demo_stripe_{$key}"
                            : "payu-demo-submerchant-{$key}",
                        'onboarding_status' => ProviderAccountOnboardingStatus::Enabled,
                        'payments_enabled' => true,
                        'payouts_enabled' => true,
                        'last_synced_at' => now()->subHour(),
                    ],
                );
            }
        }
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, FishingVenue>  $venues
     * @return array<string, Payment>
     */
    private function seedBookings(array $users, array $venues): array
    {
        $spotA = $this->firstSpot($venues['mazurska_zatoka'], 'Pomost A1');
        $spotB = $this->firstSpot($venues['lesne_stawy'], 'Stanowisko Nocne 1');
        $spotC = $this->firstSpot($venues['karpiowy_zakatek'], 'Karpiowe 1');

        $confirmedBooking = $this->upsertBooking(
            'BK-DEMO-0001',
            $users['angler'],
            $venues['mazurska_zatoka'],
            $spotA,
            BookingStatus::Confirmed,
            ReservationStatus::Confirmed,
            now()->addDays(3)->setTime(8, 0),
            now()->addDays(3)->setTime(18, 0),
            12000,
        );
        $confirmedPayment = $this->upsertPayment($confirmedBooking, 'demo-payment-0001', PaymentStatus::Succeeded, now()->subDay());

        $pendingBooking = $this->upsertBooking(
            'BK-DEMO-0002',
            $users['angler_two'],
            $venues['lesne_stawy'],
            $spotB,
            BookingStatus::PaymentPending,
            ReservationStatus::Hold,
            now()->addDays(6)->setTime(18, 0),
            now()->addDays(7)->setTime(10, 0),
            18000,
            now()->addMinutes(45),
        );
        $pendingPayment = $this->upsertPayment($pendingBooking, 'demo-payment-0002', PaymentStatus::Processing, null);

        $cancelledBooking = $this->upsertBooking(
            'BK-DEMO-0003',
            $users['angler'],
            $venues['karpiowy_zakatek'],
            $spotC,
            BookingStatus::PartiallyRefunded,
            ReservationStatus::Cancelled,
            now()->subDays(2)->setTime(7, 0),
            now()->subDays(2)->setTime(15, 0),
            9500,
        );
        $refundedPayment = $this->upsertPayment($cancelledBooking, 'demo-payment-0003', PaymentStatus::PartiallyRefunded, now()->subDays(4));

        Refund::query()->updateOrCreate(
            [
                'booking_id' => $cancelledBooking->id,
                'payment_id' => $refundedPayment->id,
                'provider_refund_id' => 'test_refund_demo_0003',
            ],
            [
                'amount' => 4750,
                'currency' => 'PLN',
                'reason' => 'Częściowy zwrot po anulacji',
                'status' => RefundStatus::Succeeded,
                'initiated_by_user_id' => $users['owner_south']->id,
            ],
        );

        PaymentWebhookEvent::query()->updateOrCreate(
            [
                'provider' => PaymentProvider::Test,
                'provider_event_id' => 'evt_demo_payment_0001',
            ],
            [
                'event_type' => 'payment.succeeded',
                'payload' => [
                    'payment_id' => $confirmedPayment->provider_payment_id,
                    'booking_number' => $confirmedBooking->booking_number,
                ],
                'processed_at' => now()->subDay(),
                'failed_at' => null,
                'error' => null,
            ],
        );

        return [
            'confirmed' => $confirmedPayment,
            'pending' => $pendingPayment,
            'refunded' => $refundedPayment,
        ];
    }

    /**
     * @param  array<string, Organization>  $organizations
     */
    private function seedSettlements(array $organizations): void
    {
        $generator = app(GenerateSettlementPeriod::class);
        $periodStart = now()->startOfMonth();
        $periodEnd = now()->endOfMonth();

        foreach ($organizations as $organization) {
            $generator->handle($organization, $periodStart, $periodEnd);
        }
    }

    private function firstSpot(FishingVenue $venue, string $name): FishingSpot
    {
        /** @var FishingSpot $spot */
        $spot = $venue->spots()->where('name', $name)->firstOrFail();

        return $spot;
    }

    private function upsertBooking(
        string $bookingNumber,
        User $user,
        FishingVenue $venue,
        FishingSpot $spot,
        BookingStatus $bookingStatus,
        ReservationStatus $reservationStatus,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        int $amount,
        ?CarbonInterface $holdExpiresAt = null,
    ): Booking {
        $platformFee = (int) round($amount * 0.1);

        /** @var Booking $booking */
        $booking = Booking::query()->updateOrCreate(
            ['booking_number' => $bookingNumber],
            [
                'user_id' => $user->id,
                'organization_id' => $venue->organization_id,
                'fishing_venue_id' => $venue->id,
                'status' => $bookingStatus,
                'currency' => 'PLN',
                'subtotal_amount' => $amount,
                'discount_amount' => 0,
                'total_amount' => $amount,
                'commission_bps' => 1000,
                'platform_fee_amount' => $platformFee,
                'operator_amount' => $amount - $platformFee,
                'customer_name' => $user->name,
                'customer_email' => $user->email,
                'customer_phone' => $user->phone,
                'cancellation_policy_snapshot' => [
                    'name' => 'Standardowa polityka',
                    'free_cancellation_hours' => 24,
                    'late_refund_percentage' => 50,
                ],
                'confirmed_at' => $bookingStatus === BookingStatus::Confirmed || $bookingStatus === BookingStatus::PartiallyRefunded
                    ? now()->subDays(1)
                    : null,
                'cancelled_at' => $reservationStatus === ReservationStatus::Cancelled ? now()->subDay() : null,
            ],
        );

        SpotReservation::query()->updateOrCreate(
            [
                'booking_id' => $booking->id,
                'fishing_spot_id' => $spot->id,
            ],
            [
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => $reservationStatus,
                'hold_expires_at' => $holdExpiresAt,
            ],
        );

        BookingPriceLine::query()->updateOrCreate(
            [
                'booking_id' => $booking->id,
                'type' => BookingPriceLineType::Spot,
            ],
            [
                'description' => "Rezerwacja: {$spot->name}",
                'quantity' => 1,
                'unit_amount' => $amount,
                'total_amount' => $amount,
            ],
        );

        return $booking;
    }

    private function upsertPayment(Booking $booking, string $suffix, PaymentStatus $status, ?CarbonInterface $paidAt): Payment
    {
        /** @var Payment $payment */
        $payment = Payment::query()->updateOrCreate(
            ['idempotency_key' => "seed-{$suffix}"],
            [
                'booking_id' => $booking->id,
                'provider' => PaymentProvider::Test,
                'provider_payment_id' => "test_{$suffix}",
                'status' => $status,
                'amount' => $booking->total_amount,
                'currency' => $booking->currency,
                'paid_at' => $paidAt,
                'failed_at' => $status === PaymentStatus::Failed ? now()->subHours(2) : null,
            ],
        );

        return $payment;
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, Organization>  $organizations
     * @param  array<string, FishingVenue>  $venues
     */
    private function seedGovernmentData(array $users, array $organizations, array $venues): void
    {
        /** @var DataSource $source */
        $source = DataSource::query()->updateOrCreate(
            [
                'provider' => 'demo-public-data',
                'dataset_name' => 'Publiczne profile łowisk',
            ],
            [
                'dataset_url' => 'https://dane-publiczne.example/lowiska-demo.json',
                'last_successful_sync_at' => now()->subHours(8),
            ],
        );

        $payload = [
            'external_id' => 'PL-WP-WDA-001',
            'license' => 'demo',
            'source' => 'seed',
        ];

        GovernmentVenueRecord::query()->updateOrCreate(
            [
                'data_source_id' => $source->id,
                'external_id' => 'PL-WP-WDA-001',
            ],
            [
                'name' => 'Jezioro Publiczne Wda',
                'description' => 'Publiczny akwen informacyjny z możliwością zgłoszenia operatora.',
                'venue_type' => FishingVenueType::WodyPolskie,
                'latitude' => '53.8774100',
                'longitude' => '18.1468600',
                'address' => 'okolice przystani gminnej',
                'city' => 'Wda',
                'postal_code' => '83-240',
                'voivodeship' => 'pomorskie',
                'country' => 'PL',
                'source_payload' => $payload,
                'source_updated_at' => now()->subDays(3),
                'status' => GovernmentVenueRecordStatus::Imported,
                'imported_fishing_venue_id' => $venues['public_wda']->id,
                'matched_fishing_venue_id' => null,
            ],
        );

        GovernmentVenueRecord::query()->updateOrCreate(
            [
                'data_source_id' => $source->id,
                'external_id' => 'PL-PZW-MAZ-042',
            ],
            [
                'name' => 'Kanał Mazurski - odcinek demo',
                'description' => 'Rekord oczekujący na decyzję importu.',
                'venue_type' => FishingVenueType::Pzw,
                'latitude' => '54.1900000',
                'longitude' => '21.6200000',
                'address' => 'odcinek leśny',
                'city' => 'Węgorzewo',
                'postal_code' => '11-600',
                'voivodeship' => 'warmińsko-mazurskie',
                'country' => 'PL',
                'source_payload' => ['external_id' => 'PL-PZW-MAZ-042', 'source' => 'seed'],
                'source_updated_at' => now()->subDays(5),
                'status' => GovernmentVenueRecordStatus::Pending,
                'imported_fishing_venue_id' => null,
                'matched_fishing_venue_id' => null,
            ],
        );

        FishingVenueSource::query()->updateOrCreate(
            [
                'fishing_venue_id' => $venues['public_wda']->id,
                'data_source_id' => $source->id,
                'external_id' => 'PL-WP-WDA-001',
            ],
            [
                'source_payload' => $payload,
                'source_updated_at' => now()->subDays(3),
                'last_synced_at' => now()->subHours(8),
            ],
        );

        FishingVenueClaim::query()->updateOrCreate(
            [
                'fishing_venue_id' => $venues['public_wda']->id,
                'organization_id' => $organizations['mazury']->id,
            ],
            [
                'status' => FishingVenueClaimStatus::Pending,
                'evidence' => 'Umowa dzierżawy i potwierdzenie opieki nad akwenem - dane demonstracyjne.',
                'reviewed_by' => null,
                'reviewed_at' => null,
            ],
        );

        FishingVenueClaim::query()->updateOrCreate(
            [
                'fishing_venue_id' => $venues['karpiowy_zakatek']->id,
                'organization_id' => $organizations['south']->id,
            ],
            [
                'status' => FishingVenueClaimStatus::Approved,
                'evidence' => 'Wpis do ewidencji działalności i dokument własności gruntu - dane demonstracyjne.',
                'reviewed_by' => $users['admin']->id,
                'reviewed_at' => now()->subDays(2),
            ],
        );
    }

    /**
     * @param  array<string, Payment>  $payments
     */
    private function seedReconciliation(array $payments): void
    {
        /** @var PaymentReconciliationRun $run */
        $run = PaymentReconciliationRun::query()->updateOrCreate(
            [
                'provider' => PaymentProvider::Test,
                'started_at' => now()->subHour()->startOfMinute(),
            ],
            [
                'status' => PaymentReconciliationRunStatus::Completed,
                'checked_count' => count($payments),
                'mismatch_count' => 1,
                'finished_at' => now()->subMinutes(52),
                'summary' => [
                    'ok' => 2,
                    'needs_review' => 1,
                    'currency' => 'PLN',
                ],
            ],
        );

        foreach ($payments as $key => $payment) {
            PaymentReconciliationItem::query()->updateOrCreate(
                [
                    'payment_reconciliation_run_id' => $run->id,
                    'payment_id' => $payment->id,
                ],
                [
                    'status' => $key === 'pending'
                        ? PaymentReconciliationItemStatus::NeedsReview
                        : PaymentReconciliationItemStatus::Ok,
                    'reason' => $key === 'pending'
                        ? 'Płatność nadal w statusie processing w bramce testowej.'
                        : 'Status zgodny z bramką testową.',
                    'details' => [
                        'provider_status' => $payment->status->value,
                        'local_status' => $payment->status->value,
                    ],
                ],
            );
        }
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, Organization>  $organizations
     * @param  array<string, FishingVenue>  $venues
     */
    private function seedAuditLogs(array $users, array $organizations, array $venues): void
    {
        $logs = [
            [
                'action' => 'organization.created',
                'actor' => $users['owner'],
                'organization' => $organizations['mazury'],
                'subject' => $organizations['mazury'],
                'after' => ['name' => $organizations['mazury']->name],
                'created_at' => now()->subDays(20),
            ],
            [
                'action' => 'fishing_venue.published',
                'actor' => $users['manager'],
                'organization' => $organizations['mazury'],
                'subject' => $venues['mazurska_zatoka'],
                'after' => ['status' => FishingVenueStatus::Published->value],
                'created_at' => now()->subDays(18),
            ],
            [
                'action' => 'venue_claim.submitted',
                'actor' => $users['owner'],
                'organization' => $organizations['mazury'],
                'subject' => $venues['public_wda'],
                'after' => ['status' => FishingVenueClaimStatus::Pending->value],
                'created_at' => now()->subDay(),
            ],
        ];

        foreach ($logs as $log) {
            $subject = $log['subject'];

            AuditLog::query()->updateOrCreate(
                [
                    'action' => $log['action'],
                    'subject_type' => $subject::class,
                    'subject_id' => $subject->id,
                ],
                [
                    'actor_user_id' => $log['actor']->id,
                    'organization_id' => $log['organization']->id,
                    'before' => null,
                    'after' => $log['after'],
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Rybki Demo Seeder',
                    'created_at' => $log['created_at'],
                ],
            );
        }
    }
}
