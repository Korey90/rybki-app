<?php

namespace App\Modules\Legal\Actions;

use App\Models\User;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Legal\Enums\LegalDocumentType;
use App\Modules\Legal\Models\LegalAcceptance;
use App\Modules\Legal\Support\CurrentLegalDocuments;
use Illuminate\Http\Request;

class RecordLegalAcceptance
{
    public function __construct(private readonly CurrentLegalDocuments $currentLegalDocuments) {}

    public function handle(
        LegalDocumentType $type,
        string $context,
        ?User $user = null,
        ?Booking $booking = null,
        ?string $customerEmail = null,
        ?Request $request = null,
        bool $marketingConsent = false,
    ): LegalAcceptance {
        $document = $this->currentLegalDocuments->get($type);

        return LegalAcceptance::create([
            'user_id' => $user?->id,
            'booking_id' => $booking?->id,
            'customer_email' => $customerEmail,
            'document_type' => $type,
            'document_version' => $document->version,
            'context' => $context,
            'marketing_consent' => $marketingConsent,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'accepted_at' => now(),
        ]);
    }
}
