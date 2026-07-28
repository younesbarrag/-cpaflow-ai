<?php

namespace App\Models;

use App\Enums\OfferStatus;
use Database\Factories\OfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'destination_url', 'payout', 'status', 'description'])]
class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'payout' => 'decimal:2',
            'status' => OfferStatus::class,
        ];
    }

    /**
     * @param  Builder<Offer>  $query
     * @return Builder<Offer>
     */
    public function scopeStatus(
        Builder $query,
        ?OfferStatus $status,
    ): Builder {
        if ($status === null) {
            return $query;
        }

        return $query->where('status', $status->value);
    }

    /**
     * @param  Builder<Offer>  $query
     * @return Builder<Offer>
     */
    public function scopeSearch(
        Builder $query,
        ?string $search,
    ): Builder {
        if ($search === null || $search === '') {
            return $query;
        }

        return $query->where('name', 'like', '%'.$search.'%');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Campaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }
}
