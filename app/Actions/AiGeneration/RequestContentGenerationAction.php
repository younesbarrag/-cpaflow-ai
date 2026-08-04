<?php

namespace App\Actions\AiGeneration;

use App\DTOs\OfferAiInputSnapshot;
use App\Enums\AiProcessStatus;
use App\Models\AiGeneration;
use App\Models\Offer;
use App\Services\OfferInputHasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type TriggerResult array{generation: AiGeneration, dispatch: bool}
 */
final class RequestContentGenerationAction
{
    public function __construct(
        private readonly OfferInputHasher $offerInputHasher,
    ) {}

    /**
     * @return TriggerResult
     *
     * @throws ValidationException
     */
    public function execute(Offer $offer): array
    {
        return DB::transaction(function () use ($offer): array {
            $offer->lockForUpdate();
            $offer->refresh();

            $existing = $offer->generations()
                ->whereIn('status', [AiProcessStatus::Pending, AiProcessStatus::Processing])
                ->orderBy('id', 'asc')
                ->first();

            if ($existing !== null) {
                return [
                    'generation' => $existing,
                    'dispatch' => false,
                ];
            }

            $analysis = $offer->analysis;

            if ($analysis === null || $analysis->status !== AiProcessStatus::Completed) {
                $this->rejectAnalysisPrerequisite();
            }

            $currentOfferHash = $this->offerInputHasher->compute(
                OfferAiInputSnapshot::fromOffer($offer),
            );

            if ($currentOfferHash !== $analysis->input_hash) {
                $this->rejectAnalysisPrerequisite();
            }

            $generation = AiGeneration::create([
                'offer_id' => $offer->id,
                'status' => AiProcessStatus::Pending,
            ]);

            return [
                'generation' => $generation,
                'dispatch' => true,
            ];
        });
    }

    /**
     * @return never
     *
     * @throws ValidationException
     */
    private function rejectAnalysisPrerequisite(): void
    {
        throw ValidationException::withMessages([
            'offer' => ['Une analyse IA terminée et à jour est requise avant de générer du contenu.'],
        ]);
    }
}
