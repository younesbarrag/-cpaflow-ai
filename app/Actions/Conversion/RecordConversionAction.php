<?php

namespace App\Actions\Conversion;

use App\Enums\ConversionStatus;
use App\Exceptions\DuplicateConversionException;
use App\Models\Campaign;
use App\Models\Conversion;
use Illuminate\Database\UniqueConstraintViolationException;

final class RecordConversionAction
{
    public function execute(
        Campaign $campaign,
        string $externalId,
        ?string $source = null,
    ): Conversion {
        $campaign->load('offer');

        $revenue = $campaign->offer->payout;

        try {
            $conversion = $campaign->conversions()->create([
                'external_id' => $externalId,
                'source' => $source,
                'revenue' => $revenue,
                'status' => ConversionStatus::Pending,
                'converted_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            $this->throwIfExternalIdCollision($externalId, $e);
            throw $e;
        }

        return $conversion;
    }

    private function throwIfExternalIdCollision(
        string $externalId,
        UniqueConstraintViolationException $e,
    ): void {
        $message = strtolower($e->getMessage());

        if (str_contains($message, 'external_id')) {
            throw new DuplicateConversionException($externalId, $e);
        }
    }
}
