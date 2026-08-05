<?php

namespace App\Actions\Conversion;

use App\Enums\ConversionStatus;
use App\Exceptions\InvalidConversionTransition;
use App\Models\Campaign;
use App\Models\Conversion;
use Illuminate\Support\Facades\DB;

final class ReviewConversionAction
{
    public function execute(
        Campaign $campaign,
        int $conversionId,
        ConversionStatus $targetStatus,
    ): Conversion {
        return DB::transaction(function () use (
            $campaign,
            $conversionId,
            $targetStatus,
        ): Conversion {
            $conversion = $campaign->conversions()
                ->whereKey($conversionId)
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = $conversion->status;

            if ($currentStatus === $targetStatus) {
                return $conversion;
            }

            if ($currentStatus !== ConversionStatus::Pending) {
                throw new InvalidConversionTransition(
                    from: $currentStatus,
                    to: $targetStatus,
                );
            }

            $conversion->status = $targetStatus;
            $conversion->save();

            return $conversion->refresh();
        });
    }
}
