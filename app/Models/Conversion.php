<?php

namespace App\Models;

use App\Enums\ConversionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Conversion extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'external_id',
        'source',
        'revenue',
        'status',
        'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'revenue' => 'decimal:2',
            'status' => ConversionStatus::class,
            'converted_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
