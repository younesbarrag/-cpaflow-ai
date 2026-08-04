<?php

namespace App\Jobs;

use App\DTOs\OfferAiInputSnapshot;
use App\DTOs\OfferContentGenerationSnapshot;
use App\Enums\AiProcessStatus;
use App\Models\AiGeneration;
use App\Services\AiContentGenerator;
use App\Services\GenerationInputHasher;
use App\Services\OfferInputHasher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Exceptions\PrismProviderOverloadedException;
use Prism\Prism\Exceptions\PrismRateLimitedException;

class GenerateContentJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 60];

    public function __construct(
        public readonly int $generationId,
    ) {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        $generation = AiGeneration::find($this->generationId);

        return $generation !== null ? (string) $generation->offer_id : (string) $this->generationId;
    }

    public function handle(
        AiContentGenerator $generator,
        GenerationInputHasher $hasher,
    ): void {
        $generation = AiGeneration::find($this->generationId);

        if ($generation === null) {
            return;
        }

        if ($generation->status === AiProcessStatus::Completed || $generation->status === AiProcessStatus::Failed) {
            return;
        }

        if ($generation->status === AiProcessStatus::Pending) {
            $generation->update(['status' => AiProcessStatus::Processing]);
            $generation->refresh();
        }

        $offer = $generation->offer;

        if ($offer === null) {
            $this->failGeneration($generation, 'Offre introuvable.');

            return;
        }

        $analysis = $offer->analysis;

        if ($analysis === null || $analysis->status !== AiProcessStatus::Completed) {
            $this->failGeneration($generation, 'Une analyse IA terminée et à jour est requise avant de générer du contenu.');

            return;
        }

        $offerInputHasher = app(OfferInputHasher::class);
        $currentOfferHash = $offerInputHasher->compute(
            OfferAiInputSnapshot::fromOffer($offer),
        );

        if ($currentOfferHash !== $analysis->input_hash) {
            $this->failGeneration($generation, 'Une analyse IA terminée et à jour est requise avant de générer du contenu.');

            return;
        }

        $snapshot = OfferContentGenerationSnapshot::fromOfferAndAnalysis($offer, $analysis);
        $inputHash = $hasher->compute($snapshot);

        try {
            $result = $generator->generate($snapshot);
        } catch (PrismRateLimitedException|PrismProviderOverloadedException $e) {
            Log::warning('AI provider transient failure', [
                'generation_id' => $generation->id,
                'offer_id' => $offer->id,
                'category' => get_class($e),
            ]);

            throw $e;
        } catch (PrismException $e) {
            Log::warning('AI provider permanent failure', [
                'generation_id' => $generation->id,
                'offer_id' => $offer->id,
                'category' => get_class($e),
            ]);

            $this->failGeneration($generation, "La génération de contenu n'a pas pu être terminée. Veuillez réessayer.");

            return;
        } catch (\Throwable $e) {
            Log::warning('AI generation unexpected failure', [
                'generation_id' => $generation->id,
                'offer_id' => $offer->id,
                'category' => get_class($e),
            ]);

            $this->failGeneration($generation, "La génération de contenu n'a pas pu être terminée. Veuillez réessayer.");

            return;
        }

        $this->validateAndPersist($generation, $result, $inputHash);
    }

    public function failed(\Throwable $exception): void
    {
        $generation = AiGeneration::find($this->generationId);

        if ($generation === null) {
            return;
        }

        $this->failGeneration($generation, "La génération de contenu n'a pas pu être terminée. Veuillez réessayer.");

        Log::warning('AI generation job failed permanently', [
            'generation_id' => $generation->id,
            'offer_id' => $generation->offer_id,
        ]);
    }

    /**
     * @param  array{hooks: list<string>, captions: list<string>}  $result
     */
    private function validateAndPersist(AiGeneration $generation, array $result, string $inputHash): void
    {
        $validator = Validator::make($result, [
            'hooks' => ['required', 'array', 'min:3', 'max:5'],
            'hooks.*' => ['required', 'string', 'max:200'],
            'captions' => ['required', 'array', 'min:3', 'max:5'],
            'captions.*' => ['required', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            Log::warning('AI generation output validation failed', [
                'generation_id' => $generation->id,
                'offer_id' => $generation->offer_id,
                'errors' => $validator->errors()->toArray(),
            ]);

            $this->failGeneration($generation, "La génération de contenu n'a pas pu être terminée. Veuillez réessayer.");

            return;
        }

        $hooks = array_map('trim', $result['hooks']);
        $hooks = array_filter($hooks, static fn (string $h): bool => $h !== '');
        $hooks = array_values($hooks);

        $captions = array_map('trim', $result['captions']);
        $captions = array_filter($captions, static fn (string $c): bool => $c !== '');
        $captions = array_values($captions);

        $generation->update([
            'status' => AiProcessStatus::Completed,
            'hooks' => $hooks,
            'captions' => $captions,
            'input_hash' => $inputHash,
            'provider' => config('ai.provider'),
            'model' => config('ai.model'),
            'error_message' => null,
            'completed_at' => now(),
        ]);
    }

    private function failGeneration(AiGeneration $generation, string $message): void
    {
        $generation->update([
            'status' => AiProcessStatus::Failed,
            'error_message' => $message,
        ]);
    }
}
