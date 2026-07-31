<?php

namespace App\Models;

use Database\Factories\TrackingClickFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingClick extends Model
{
    /** @use HasFactory<TrackingClickFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tracking_link_id',
        'ip_hash',
        'user_agent',
        'referer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
    ];

    public function trackingLink(): BelongsTo
    {
        return $this->belongsTo(TrackingLink::class);
    }
}
