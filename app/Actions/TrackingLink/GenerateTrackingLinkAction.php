<?php

namespace App\Actions\TrackingLink;

use App\Exceptions\CannotGenerateTrackingLink;
use App\Models\Campaign;
use App\Models\TrackingLink;
use App\Services\TrackingLink\TrackingCodeGenerator;
use Illuminate\Database\UniqueConstraintViolationException;

final class GenerateTrackingLinkAction
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private TrackingCodeGenerator $codeGenerator,
    ) {}

    public function execute(Campaign $campaign): TrackingLink
    {
        $attempts = 0;

        while (true) {
            $attempts++;

            try {
                return $campaign->trackingLinks()->create([
                    'code' => $this->codeGenerator->generate(),
                ]);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempts >= self::MAX_ATTEMPTS) {
                    throw new CannotGenerateTrackingLink(
                        attempts: $attempts,
                        previous: $e,
                    );
                }
            }
        }
    }
}
