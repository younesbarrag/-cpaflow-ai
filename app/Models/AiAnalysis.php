<?php

namespace App\Models;

use App\Enums\AiProcessStatus;
use Database\Factories\AiAnalysisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'offer_id',
    'status',
    'score',
    'summary',
    'strengths',
    'weaknesses',
    'recommendations',
    'input_hash',
    'provider',
    'model',
    'error_message',
    'completed_at',
])]
class AiAnalysis extends Model
{
    /** @use HasFactory<AiAnalysisFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => AiProcessStatus::class,
            'score' => 'integer',
            'strengths' => 'array',
            'weaknesses' => 'array',
            'recommendations' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
