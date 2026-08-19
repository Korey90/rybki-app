<?php

namespace App\Modules\Legal\Support;

use App\Modules\Legal\Enums\LegalDocumentType;
use App\Modules\Legal\Models\LegalDocument;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

class CurrentLegalDocuments
{
    public function get(LegalDocumentType $type): LegalDocument
    {
        $document = LegalDocument::query()
            ->where('type', $type->value)
            ->where('is_active', true)
            ->latest('published_at')
            ->latest()
            ->first();

        if (! $document instanceof LegalDocument) {
            throw new RuntimeException("Missing active legal document [{$type->value}].");
        }

        return $document;
    }

    /**
     * @param  array<int, LegalDocumentType>  $types
     * @return Collection<int, LegalDocument>
     */
    public function many(array $types): Collection
    {
        return LegalDocument::query()
            ->whereIn('type', array_map(fn (LegalDocumentType $type): string => $type->value, $types))
            ->where('is_active', true)
            ->latest('published_at')
            ->latest()
            ->get()
            ->unique(fn (LegalDocument $document): string => $document->type->value)
            ->values();
    }

    /**
     * @return array<string, array<string, string|null>>
     */
    public function payload(): array
    {
        return $this->many(LegalDocumentType::cases())
            ->mapWithKeys(fn (LegalDocument $document): array => [
                $document->type->value => [
                    'title' => $document->title,
                    'slug' => $document->slug,
                    'version' => $document->version,
                    'url' => url($document->slug),
                ],
            ])
            ->all();
    }
}
