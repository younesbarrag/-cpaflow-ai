<?php

namespace App\Models;

use Database\Factories\TrackingLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackingLink extends Model
{
    /** @use HasFactory<TrackingLinkFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(TrackingClick::class);
    }
}
