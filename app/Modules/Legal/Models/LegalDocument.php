<?php

namespace App\Modules\Legal\Models;

use App\Modules\Legal\Enums\LegalDocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property LegalDocumentType $type
 * @property string $slug
 * @property string $version
 * @property string $title
 * @property string|null $summary
 * @property string $body
 * @property bool $is_active
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['type', 'slug', 'version', 'title', 'summary', 'body', 'is_active', 'published_at'])]
class LegalDocument extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LegalDocumentType::class,
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
