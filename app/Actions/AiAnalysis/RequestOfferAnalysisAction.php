<?php

namespace App\Actions\AiAnalysis;

use App\Enums\AiProcessStatus;
use App\Models\AiAnalysis;
use App\Models\Offer;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type TriggerResult array{analysis: AiAnalysis, dispatch: bool}
 */
final class RequestOfferAnalysisAction
{
    /**
     * @return TriggerResult
     */
    public function execute(Offer $offer): array
    {
        return DB::transaction(function () use ($offer): array {
            $offer->lockForUpdate();
            $offer->refresh();

            $existing = $offer->analysis;

            if ($existing !== null && in_array($existing->status, [
                AiProcessStatus::Pending,
                AiProcessStatus::Processing,
            ], true)) {
                return [
                    'analysis' => $existing,
                    'dispatch' => false,
                ];
            }

            if ($existing !== null) {
                $existing->update([
                    'status' => AiProcessStatus::Pending,
                    'score' => null,
                    'summary' => null,
                    'strengths' => null,
                    'weaknesses' => null,
                    'recommendations' => null,
                    'input_hash' => null,
                    'provider' => null,
                    'model' => null,
                    'error_message' => null,
                    'completed_at' => null,
                ]);

                return [
                    'analysis' => $existing->refresh(),
                    'dispatch' => true,
                ];
            }

            $analysis = AiAnalysis::create([
                'offer_id' => $offer->id,
                'status' => AiProcessStatus::Pending,
            ]);

            return [
                'analysis' => $analysis,
                'dispatch' => true,
            ];
        });
    }
}
