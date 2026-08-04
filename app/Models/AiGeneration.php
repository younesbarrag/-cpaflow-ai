<?php

namespace App\Models;

use App\Enums\AiProcessStatus;
use Database\Factories\AiGenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'offer_id',
    'status',
    'hooks',
    'captions',
    'input_hash',
    'provider',
    'model',
    'error_message',
    'completed_at',
])]
class AiGeneration extends Model
{
    /** @use HasFactory<AiGenerationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => AiProcessStatus::class,
            'hooks' => 'array',
            'captions' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Offer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
