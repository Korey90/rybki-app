<?php

namespace App\Http\Controllers;

use App\Modules\Legal\Enums\LegalDocumentType;
use App\Modules\Legal\Models\LegalDocument;
use App\Modules\Legal\Support\CurrentLegalDocuments;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LegalDocumentController extends Controller
{
    public function __construct(private readonly CurrentLegalDocuments $currentLegalDocuments) {}

    public function show(Request $request, string $slug): Response
    {
        $type = $this->typeFromSlug($slug);
        abort_if($type === null, 404);

        $document = $this->currentLegalDocuments->get($type);

        return Inertia::render('legal/show', [
            'document' => $this->documentPayload($document),
            'documents' => $this->currentLegalDocuments->payload(),
        ]);
    }

    private function typeFromSlug(string $slug): ?LegalDocumentType
    {
        foreach (LegalDocumentType::cases() as $type) {
            if ($type->slug() === $slug) {
                return $type;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function documentPayload(LegalDocument $document): array
    {
        return [
            'type' => $document->type->value,
            'slug' => $document->slug,
            'version' => $document->version,
            'title' => $document->title,
            'summary' => $document->summary,
            'body' => $document->body,
            'published_at' => $document->published_at?->toIso8601String(),
            'is_draft_placeholder' => str_contains($document->body, 'placeholderem do weryfikacji prawnej'),
        ];
    }
}
