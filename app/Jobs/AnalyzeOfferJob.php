<?php

namespace App\Jobs;

use App\DTOs\OfferAiInputSnapshot;
use App\Enums\AiProcessStatus;
use App\Models\AiAnalysis;
use App\Services\AiOfferAnalyzer;
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

class AnalyzeOfferJob implements ShouldBeUnique, ShouldQueue
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
        public readonly int $analysisId,
    ) {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        $analysis = AiAnalysis::find($this->analysisId);

        return $analysis !== null ? (string) $analysis->offer_id : (string) $this->analysisId;
    }

    public function handle(
        AiOfferAnalyzer $analyzer,
        OfferInputHasher $hasher,
    ): void {
        $analysis = AiAnalysis::find($this->analysisId);

        if ($analysis === null) {
            return;
        }

        if ($analysis->status === AiProcessStatus::Completed || $analysis->status === AiProcessStatus::Failed) {
            return;
        }

        if ($analysis->status === AiProcessStatus::Pending) {
            $analysis->update(['status' => AiProcessStatus::Processing]);
            $analysis->refresh();
        }

        $offer = $analysis->offer;

        if ($offer === null) {
            $this->failAnalysis($analysis, 'Offre introuvable.');

            return;
        }

        $snapshot = OfferAiInputSnapshot::fromOffer($offer);
        $inputHash = $hasher->compute($snapshot);

        try {
            $result = $analyzer->analyze($snapshot);
        } catch (PrismRateLimitedException|PrismProviderOverloadedException $e) {
            Log::warning('AI provider transient failure', [
                'analysis_id' => $analysis->id,
                'offer_id' => $offer->id,
                'category' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            throw $e;
        } catch (PrismException $e) {
            Log::warning('AI provider permanent failure', [
                'analysis_id' => $analysis->id,
                'offer_id' => $offer->id,
                'category' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            $this->failAnalysis($analysis, "L'analyse IA n'a pas pu être terminée. Veuillez réessayer.");

            return;
        } catch (\Throwable $e) {
            Log::warning('AI analysis unexpected failure', [
                'analysis_id' => $analysis->id,
                'offer_id' => $offer->id,
                'category' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            $this->failAnalysis($analysis, "L'analyse IA n'a pas pu être terminée. Veuillez réessayer.");

            return;
        }

        $this->validateAndPersist($analysis, $result, $inputHash);
    }

    public function failed(\Throwable $exception): void
    {
        $analysis = AiAnalysis::find($this->analysisId);

        if ($analysis === null) {
            return;
        }

        $this->failAnalysis($analysis, "L'analyse IA n'a pas pu être terminée. Veuillez réessayer.");

        Log::warning('AI analysis job failed permanently', [
            'analysis_id' => $analysis->id,
            'offer_id' => $analysis->offer_id,
            'message' => $exception->getMessage(),
        ]);
    }

    /**
     * @param  array{score: int, summary: string, strengths: list<string>, weaknesses: list<string>, recommendations: list<string>}  $result
     */
    private function validateAndPersist(AiAnalysis $analysis, array $result, string $inputHash): void
    {
        $validator = Validator::make($result, [
            'score' => ['required', 'integer', 'min:0', 'max:100'],
            'summary' => ['required', 'string', 'max:1000'],
            'strengths' => ['present', 'array', 'max:5'],
            'strengths.*' => ['present', 'string', 'max:200'],
            'weaknesses' => ['present', 'array', 'max:5'],
            'weaknesses.*' => ['present', 'string', 'max:200'],
            'recommendations' => ['present', 'array', 'max:5'],
            'recommendations.*' => ['present', 'string', 'max:200'],
        ]);

        if ($validator->fails()) {
            Log::warning('AI analysis output validation failed', [
                'analysis_id' => $analysis->id,
                'offer_id' => $analysis->offer_id,
                'errors' => $validator->errors()->toArray(),
            ]);

            $this->failAnalysis($analysis, "L'analyse IA n'a pas pu être terminée. Veuillez réessayer.");

            return;
        }

        $analysis->update([
            'status' => AiProcessStatus::Completed,
            'score' => $result['score'],
            'summary' => $result['summary'],
            'strengths' => $result['strengths'],
            'weaknesses' => $result['weaknesses'],
            'recommendations' => $result['recommendations'],
            'input_hash' => $inputHash,
            'provider' => config('ai.provider'),
            'model' => config('ai.model'),
            'error_message' => null,
            'completed_at' => now(),
        ]);
    }

    private function failAnalysis(AiAnalysis $analysis, string $message): void
    {
        $analysis->update([
            'status' => AiProcessStatus::Failed,
            'error_message' => $message,
        ]);
    }
}
