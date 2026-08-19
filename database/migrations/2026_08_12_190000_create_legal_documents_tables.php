<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('slug');
            $table->string('version', 32);
            $table->string('title');
            $table->string('summary', 500)->nullable();
            $table->longText('body');
            $table->boolean('is_active')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'version']);
            $table->index(['type', 'is_active']);
            $table->unique(['slug', 'version']);
        });

        Schema::create('legal_acceptances', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('customer_email')->nullable();
            $table->string('document_type');
            $table->string('document_version', 32);
            $table->string('context', 40);
            $table->boolean('marketing_consent')->default(false);
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('accepted_at');
            $table->timestamps();

            $table->index(['user_id', 'document_type', 'accepted_at']);
            $table->index(['booking_id', 'document_type']);
            $table->index(['document_type', 'document_version']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('marketing_consent_at')->nullable()->after('phone_verified_at');
            $table->string('marketing_consent_document_version', 32)->nullable()->after('marketing_consent_at');
        });

        $this->insertDefaultDocuments();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['marketing_consent_at', 'marketing_consent_document_version']);
        });

        Schema::dropIfExists('legal_acceptances');
        Schema::dropIfExists('legal_documents');
    }

    private function insertDefaultDocuments(): void
    {
        $now = now();
        $version = '2026.08.12-mvp';
        $documents = [
            [
                'type' => 'TERMS',
                'slug' => 'regulamin',
                'title' => 'Regulamin platformy Rybki',
                'summary' => 'Roboczy regulamin MVP marketplace rezerwacji łowisk.',
                'body' => $this->draftBody('Regulamin', [
                    'Platforma umożliwia wyszukiwanie łowisk, składanie rezerwacji i obsługę płatności.',
                    'Operator łowiska odpowiada za poprawność opisu, dostępność stanowisk, regulamin lokalny i realizację usługi.',
                    'Użytkownik powinien podawać prawdziwe dane kontaktowe oraz przestrzegać zasad danego łowiska.',
                    'Treść wymaga finalnej weryfikacji właściciela projektu albo prawnika przed produkcyjnym uruchomieniem.',
                ]),
            ],
            [
                'type' => 'PRIVACY_POLICY',
                'slug' => 'polityka-prywatnosci',
                'title' => 'Polityka prywatności',
                'summary' => 'Roboczy opis przetwarzania danych w MVP.',
                'body' => $this->draftBody('Polityka prywatności', [
                    'Aplikacja przetwarza dane konta, dane rezerwacji, dane płatności, dane kontaktowe i podstawowe logi bezpieczeństwa.',
                    'Dane są używane do założenia konta, obsługi rezerwacji, płatności, powiadomień, rozliczeń i bezpieczeństwa platformy.',
                    'Użytkownik może poprosić o eksport danych oraz skorzystać z procesu usunięcia konta dostępnego w ustawieniach profilu.',
                    'Zakres, podstawy prawne i okresy przechowywania wymagają finalnej weryfikacji prawnej.',
                ]),
            ],
            [
                'type' => 'CANCELLATION_RULES',
                'slug' => 'zasady-anulowania',
                'title' => 'Zasady anulowania',
                'summary' => 'Roboczy opis anulowania rezerwacji i zwrotów.',
                'body' => $this->draftBody('Zasady anulowania', [
                    'Warunki anulowania wynikają z polityki przypisanej do rezerwacji i są pokazywane w checkoutcie oraz szczegółach rezerwacji.',
                    'Domyślnie MVP pokazuje darmowe anulowanie przed wskazanym terminem oraz przewidywany procent zwrotu po tym czasie.',
                    'Zwroty płatności są zależne od statusu płatności, providera oraz zasad operatora.',
                    'Finalna treść zasad anulowania musi zostać zatwierdzona przed uruchomieniem produkcyjnym.',
                ]),
            ],
            [
                'type' => 'CONTACT',
                'slug' => 'kontakt',
                'title' => 'Kontakt',
                'summary' => 'Robocze dane kontaktowe platformy.',
                'body' => $this->draftBody('Kontakt', [
                    'Adres kontaktowy platformy, dane firmy i kanały wsparcia należy uzupełnić przed publikacją.',
                    'W sprawach rezerwacji użytkownik powinien podawać numer rezerwacji oraz adres email użyty w checkoutcie.',
                    'W sprawach operatorów należy podać nazwę organizacji i nazwę łowiska.',
                ]),
            ],
            [
                'type' => 'OPERATOR_INFO',
                'slug' => 'dla-operatorow',
                'title' => 'Informacje dla operatorów',
                'summary' => 'Robocze zasady współpracy dla właścicieli łowisk.',
                'body' => $this->draftBody('Informacje dla operatorów', [
                    'Operatorzy mogą tworzyć organizacje, publikować łowiska, zarządzać stanowiskami, dostępnością, cennikiem i rezerwacjami.',
                    'Operator odpowiada za zgodność oferty z rzeczywistymi warunkami łowiska oraz obsługę klienta na miejscu.',
                    'Rozliczenia i prowizje platformy są prezentowane w panelu operatora i panelu admina.',
                    'Model współpracy i obowiązki operatora wymagają finalnego potwierdzenia prawnego.',
                ]),
            ],
        ];

        foreach ($documents as $document) {
            DB::table('legal_documents')->insert([
                ...$document,
                'version' => $version,
                'is_active' => true,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @param  array<int, string>  $paragraphs
     */
    private function draftBody(string $title, array $paragraphs): string
    {
        return "# {$title}\n\nWersja robocza MVP. Ten dokument jest technicznym placeholderem do weryfikacji prawnej.\n\n- ".implode("\n- ", $paragraphs);
    }
};
