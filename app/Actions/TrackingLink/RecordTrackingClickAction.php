<?php

namespace App\Actions\TrackingLink;

use App\Models\TrackingClick;
use App\Models\TrackingLink;
use App\Services\TrackingLink\IpHasher;

final class RecordTrackingClickAction
{
    public function __construct(
        private IpHasher $ipHasher,
    ) {}

    public function execute(
        TrackingLink $trackingLink,
        ?string $ip,
        ?string $userAgent,
        ?string $referer,
        ?string $utmSource,
        ?string $utmMedium,
        ?string $utmCampaign,
        ?string $utmTerm,
        ?string $utmContent,
    ): TrackingClick {
        return $trackingLink->clicks()->create([
            'ip_hash' => $this->ipHasher->hash($ip),
            'user_agent' => $this->truncate($userAgent, 512),
            'referer' => $this->truncate($referer, 2048),
            'utm_source' => $this->truncate($utmSource, 255),
            'utm_medium' => $this->truncate($utmMedium, 255),
            'utm_campaign' => $this->truncate($utmCampaign, 255),
            'utm_term' => $this->truncate($utmTerm, 255),
            'utm_content' => $this->truncate($utmContent, 255),
        ]);
    }

    private function truncate(?string $value, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($trimmed, 0, $max);
        }

        return substr($trimmed, 0, $max);
    }
}
