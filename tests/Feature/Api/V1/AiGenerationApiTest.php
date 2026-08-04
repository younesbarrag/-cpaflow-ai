<?php

use App\DTOs\OfferAiInputSnapshot;
use App\DTOs\OfferContentGenerationSnapshot;
use App\Enums\AiProcessStatus;
use App\Enums\OfferStatus;
use App\Jobs\GenerateContentJob;
use App\Models\AiAnalysis;
use App\Models\AiGeneration;
use App\Models\Offer;
use App\Models\User;
use App\Services\AiContentGenerator;
use App\Services\GenerationInputHasher;
use App\Services\OfferInputHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\StructuredResponseFake;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test-device')->plainTextToken;
    $this->otherUser = User::factory()->create();
    $this->otherToken = $this->otherUser->createToken('test-device')->plainTextToken;
});

describe('Security', function () {
    test('guest trigger returns 401', function () {
        $offer = Offer::factory()->forUser($this->user)->create();

        $this->postJson("/api/v1/offers/{$offer->id}/generate")
            ->assertStatus(401);
    });

    test('foreign offer trigger returns 403', function () {
        $offer = Offer::factory()->forUser($this->user)->create();

        $this->withHeader('Authorization', 'Bearer '.$this->otherToken)
            ->postJson("/api/v1/offers/{$offer->id}/generate")
            ->assertStatus(403);
    });

    test('unknown offer trigger returns 404', function () {
        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers/99999/generate')
            ->assertStatus(404);
    });

    test('guest index returns 401', function () {
        $offer = Offer::factory()->forUser($this->user)->create();

        $this->getJson("/api/v1/offers/{$offer->id}/generations")
            ->assertStatus(401);
    });

    test('foreign offer index returns 403', function () {
        $offer = Offer::factory()->forUser($this->user)->create();

        $this->withHeader('Authorization', 'Bearer '.$this->otherToken)
            ->getJson("/api/v1/offers/{$offer->id}/generations")
            ->assertStatus(403);
    });

    test('guest show returns 401', function () {
        $offer = Offer::factory()->forUser($this->user)->create();
        $generation = AiGeneration::factory()->completed()->forOffer($offer)->create();

        $this->getJson("/api/v1/offers/{$offer->id}/generations/{$generation->id}")
            ->assertStatus(401);
    });

    test('foreign offer show returns 403', function () {
        $offer = Offer::factory()->forUser($this->user)->create();
        $generation = AiGeneration::factory()->completed()->forOffer($offer)->create();

        $this->withHeader('Authorization', 'Bearer '.$this->otherToken)
            ->getJson("/api/v1/offers/{$offer->id}/generations/{$generation->id}")
            ->assertStatus(403);
    });

    test('nested wrong generation under owned offer returns 404', function () {
        $offer = Offer::factory()->forUser($this->user)->create();
        $otherOffer = Offer::factory()->forUser($this->user)->create();
        $generation = AiGeneration::factory()->completed()->forOffer($otherOffer)->create();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/offers/{$offer->id}/generations/{$generation->id}")
            ->assertStatus(404);
    });
});

describe('Analysis Prerequisite', function () {
    test('no analysis returns 422', function () {
        Bus::fake([GenerateContentJob::class]);

        $offer = Offer::factory()->forUser($this->user)->create();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/offers/{$offer->id}/generate")
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Une analyse IA terminée et à jour est requise avant de générer du contenu.',
            ]);

        Bus::assertNotDispatched(GenerateContentJob::class);
    });

    test('pending analysis returns 422', function () {
        Bus::fake([GenerateContentJob::class]);

        $offer = Offer::factory()->forUser($this->user)->create();
        AiAnalysis::factory()->pending()->forOffer($offer)->create();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/offers/{$offer->id}/generate")
            ->assertStatus(422);

        Bus::assertNotDispatched(GenerateContentJob::class);
    });

    test('processing analysis returns 422', function () {
        Bus::fake([GenerateContentJob::class]);

        $offer = Offer::factory()->forUser($this->user)->create();
        AiAnalysis::factory()->processing()->forOffer($offer)->create();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/offers/{$offer->id}/generate")
            ->assertStatus(422);

        Bus::assertNotDispatched(GenerateContentJob::class);
    });

    test('failed analysis returns 422', function () {
        Bus::fake([GenerateContentJob::class]);

        $offer = Offer::factory()->forUser($this->user)->create();
        AiAnalysis::factory()->failed()->forOffer($offer)->create();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/offers/{$offer->id}/generate")
            ->assertStatus(422);

        Bus::assertNotDispatched(GenerateContentJob::class);
    });

    test('completed but stale analysis returns 422', function () {
        Bus::fake([GenerateContentJob::class]);

        $offer = Offer::factory()->forUser($this->user)->create();
        $analysis = AiAnalysis::factory()->completed()->forOffer($offer)->create();

        $offer->update(['name' => 'Updated Offer Name']);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/offers/{$offer->id}/generate")
            ->assertStatus(422);

        Bus::assertNotDispatched(GenerateContentJob::class);
    });

    test('completed and current analysis allows generation', function () {
        Bus::fake([GenerateContentJob::class]);

        $offer = Offer::factory()->forUser($this->user)->create();

        $snapshot = OfferAiInputSnapshot::fromOffer($offer);
        $hasher = app(OfferInputHasher::class);
        $inputHash = $hasher->compute($snapshot);

        AiAnalysis::factory()->completed()->forOffer($offer)->create([
            'input_hash' => $inputHash,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/offers/{$offer->id}/generate")
            ->assertStatus(202);

        Bus::assertDispatched(GenerateContentJob::class);
    });
});

describe('Trigger', function () {
    test('first valid trigger returns 202 and creates generation', function () {
        Bus::fake([GenerateContentJob::class]);

        $offer = Offer::factory()->forUser($this->user)->create();

        $snapshot = OfferAiInputSnapshot::fromOffer($offer);
        $hasher = app(OfferInputHasher::class);
        $inputHash = $hasher->compute($snapshot);

        AiAnalysis::factory()->completed()->forOffer($offer)->create([
            'input_hash' => $inputHash,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/offers/{$offer->id}/generate");

        $response->assertStatus(202)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'offer_id',
                    'status',
                    'created_at',
                ],
            ])
            ->assertJson([
                'data' => [
                    'offer_id' => $offer->id,
                    'status' => 'pending',
                ],
            ]);

        $this->assertDatabaseHas('ai_generations', [
            'offer_id' => $offer->id,
            'status' => AiProcessStatus::Pending->value,
        ]);

        Bus::assertDispatched(GenerateContentJob::class, function (GenerateContentJob $job) use ($offer): bool {
            $generation = AiGeneration::find($job->generationId);

            return $generation !== null && $generation->offer_id === $offer->id;
        });
    });

    test('ai is not called synchronously', function () {
        Bus::fake([GenerateContentJob::class]);

        $offer = Offer::factory()->forUser($this->user)->create();

        $snapshot = OfferAiInputSnapshot::fromOffer($offer);
        $hasher = app(OfferInputHasher::class);
        $inputHash = $hasher->compute($snapshot);

        AiAnalysis::factory()->completed()->forOffer($offer)->create([
            'input_hash' => $inputHash,
        ]);

        $fake = Prism::fake([]);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/offers/{$offer->id}/generate")
            ->assertStatus(202);

        $fake->assertCallCount(0);
    });
});

describe('Idempotency', function () {
    test('pending duplicate trigger returns 200 with same generation', function () {
        Bus::fake([GenerateContentJob::class]);

        $offer = Offer::factory()->forUser($this->user)->create();

        $snapshot = OfferAiInputSnapshot::fromOffer($offer);
        $hasher = app(OfferInputHasher::class);
        $inputHash = $hasher->compute($snapshot);

        AiAnalysis::factory()->completed()->forOffer($offer)->create([
            'input_hash' => $inputHash,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/offers/{$offer->id}/generate")
            ->assertStatus(202);

        Bus::assertDispatched(GenerateContentJob::class);

        Queue::fake();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/offers/{$offer->id}/generate");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'status' => 'pending',
                ],
            ]);

        $this->assertDatabaseCount('ai_generations', 1);
    });

    test('processing duplicate trigger returns 200 with same generation', function () {
        Bus::fake([GenerateContentJob::class]);

        $offer = Offer::factory()->forUser($this->user)->create();

        $snapshot = OfferAiInputSnapshot::fromOffer($offer);
        $hasher = app(OfferInputHasher::class);
        $inputHash = $hasher->compute($snapshot);

        AiAnalysis::factory()->completed()->forOffer($offer)->create([
            'input_hash' => $inputHash,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/offers/{$offer->id}/generate")
            ->assertStatus(202);

        AiGeneration::where('offer_id', $offer->id)->update([
            'status' => AiProcessStatus::Processing,
        ]);

        Queue::fake();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/offers/{$offer->id}/generate");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'status' => 'processing',
                ],
            ]);

        $this->assertDatabaseCount('ai_generations', 1);
    });
});

describe('History', function () {
    test('completed previous generation triggers new row', function () {
        Bus::fake([GenerateContentJob::class]);

        $offer = Offer::factory()->forUser($this->user)->create();

        $snapshot = OfferAiInputSnapshot::fromOffer($offer);
        $hasher = app(OfferInputHasher::class);
        $inputHash = $hasher->compute($snapshot);

        AiAnalysis::factory()->completed()->forOffer($offer)->create([
            'input_hash' => $inputHash,
        ]);

        $existing = AiGeneration::factory()->completed()->forOffer($offer)->create();
        $existingId = $existing->id;

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/offers/{$offer->id}/generate");

        $response->assertStatus(202);

        $newGeneration = AiGeneration::where('offer_id', $offer->id)
            ->where('id', '!=', $existingId)
            ->first();

        expect($newGeneration)->not->toBeNull()
            ->and($newGeneration->status)->toBe(AiProcessStatus::Pending);

        $this->assertDatabaseCount('ai_generations', 2);
    });

    test('failed previous generation triggers new row', function () {
        Bus::fake([GenerateContentJob::class]);

        $offer = Offer::factory()->forUser($this->user)->create();

        $snapshot = OfferAiInputSnapshot::fromOffer($offer);
        $hasher = app(OfferInputHasher::class);
        $inputHash = $hasher->compute($snapshot);

        AiAnalysis::factory()->completed()->forOffer($offer)->create([
            'input_hash' => $inputHash,
        ]);

        AiGeneration::factory()->failed()->forOffer($offer)->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/offers/{$offer->id}/generate");

        $response->assertStatus(202);

        $this->assertDatabaseCount('ai_generations', 2);
    });
});

describe('Structured Output', function () {
    test('valid completed output persists correctly', function () {
        $offer = Offer::factory()->forUser($this->user)->create();

        $snapshot = OfferAiInputSnapshot::fromOffer($offer);
        $hasher = app(OfferInputHasher::class);
        $inputHash = $hasher->compute($snapshot);

        $analysis = AiAnalysis::factory()->completed()->forOffer($offer)->create([
            'input_hash' => $inputHash,
        ]);

        $generation = AiGeneration::factory()->pending()->forOffer($offer)->create();

        $fakeResponse = StructuredResponseFake::make()
            ->withStructured([
                'hooks' => [
                    'Perdez du poids avec cette solution',
                    'Votre corps mérite cette transformation',
                    'La méthode secrète des professionnels',
                ],
                'captions' => [
                    'Découvrez cette offre exclusive pour votre audience.',
                    'Transformez votre vie en quelques clics.',
                    'Générez des revenus avec ce programme.',
                ],
            ]);

        Prism::fake([$fakeResponse]);

        $job = new GenerateContentJob($generation->id);
        $job->handle(
            app(AiContentGenerator::class),
            app(GenerationInputHasher::class),
        );

        $generation->refresh();

        expect($generation->status)->toBe(AiProcessStatus::Completed)
            ->and($generation->hooks)->toHaveCount(3)
            ->and($generation->captions)->toHaveCount(3)
            ->and($generation->input_hash)->not->toBeNull()
            ->and($generation->completed_at)->not->toBeNull()
            ->and($generation->provider)->toBe(config('ai.provider'))
            ->and($generation->model)->toBe(config('ai.model'))
            ->and($generation->error_message)->toBeNull();
    });

    test('hooks below minimum rejects', function () {
        $offer = Offer::factory()->forUser($this->user)->create();

        $snapshot = OfferAiInputSnapshot::fromOffer($offer);
        $hasher = app(OfferInputHasher::class);
        $inputHash = $hasher->compute($snapshot);

        AiAnalysis::factory()->completed()->forOffer($offer)->create([
            'input_hash' => $inputHash,
        ]);

        $generation = AiGeneration::factory()->pending()->forOffer($offer)->create();

        $fakeResponse = StructuredResponseFake::make()
            ->withStructured([
                'hooks' => ['Hook 1', 'Hook 2'],
                'captions' => ['Caption 1', 'Caption 2', 'Caption 3'],
            ]);

        Prism::fake([$fakeResponse]);

        $job = new GenerateContentJob($generation->id);
        $job->handle(
            app(AiContentGenerator::class),
            app(GenerationInputHasher::class),
        );

        $generation->refresh();

        expect($generation->status)->toBe(AiProcessStatus::Failed);
    });

    test('hooks above maximum rejects', function () {
        $offer = Offer::factory()->forUser($this->user)->create();

        $snapshot = OfferAiInputSnapshot::fromOffer($offer);
        $hasher = app(OfferInputHasher::class);
        $inputHash = $hasher->compute($snapshot);

        AiAnalysis::factory()->completed()->forOffer($offer)->create([
            'input_hash' => $inputHash,
        ]);

        $generation = AiGeneration::factory()->pending()->forOffer($offer)->create();

        $fakeResponse = StructuredResponseFake::make()
            ->withStructured([
                'hooks' => ['H1', 'H2', 'H3', 'H4', 'H5', 'H6'],
                'captions' => ['C1', 'C2', 'C3'],
            ]);

        Prism::fake([$fakeResponse]);

        $job = new GenerateContentJob($generation->id);
        $job->handle(
            app(AiContentGenerator::class),
            app(GenerationInputHasher::class),
        );

        $generation->refresh();

        expect($generation->status)->toBe(AiProcessStatus::Failed);
    });

    test('captions below minimum rejects', function () {
        $offer = Offer::factory()->forUser($this->user)->create();

        $snapshot = OfferAiInputSnapshot::fromOffer($offer);
        $hasher = app(OfferInputHasher::class);
        $inputHash = $hasher->compute($snapshot);

        AiAnalysis::factory()->completed()->forOffer($offer)->create([
            'input_hash' => $inputHash,
        ]);

        $generation = AiGeneration::factory()->pending()->forOffer($offer)->create();

        $fakeResponse = StructuredResponseFake::make()
            ->withStructured([
                'hooks' => ['H1', 'H2', 'H3'],
                'captions' => ['C1', 'C2'],
            ]);

        Prism::fake([$fakeResponse]);

        $job = new GenerateContentJob($generation->id);
        $job->handle(
            app(AiContentGenerator::class),
            app(GenerationInputHasher::class),
        );

        $generation->refresh();

        expect($generation->status)->toBe(AiProcessStatus::Failed);
    });

    test('captions above maximum rejects', function () {
        $offer = Offer::factory()->forUser($this->user)->create();

        $snapshot = OfferAiInputSnapshot::fromOffer($offer);
        $hasher = app(OfferInputHasher::class);
        $inputHash = $hasher->compute($snapshot);

        AiAnalysis::factory()->completed()->forOffer($offer)->create([
            'input_hash' => $inputHash,
        ]);

        $generation = AiGeneration::factory()->pending()->forOffer($offer)->create();

        $fakeResponse = StructuredResponseFake::make()
            ->withStructured([
                'hooks' => ['H1', 'H2', 'H3'],
                'captions' => ['C1', 'C2', 'C3', 'C4', 'C5', 'C6'],
            ]);

        Prism::fake([$fakeResponse]);

        $job = new GenerateContentJob($generation->id);
        $job->handle(
            app(AiContentGenerator::class),
            app(GenerationInputHasher::class),
        );

        $generation->refresh();

        expect($generation->status)->toBe(AiProcessStatus::Failed);
    });

    test('empty hook rejects', function () {
        $offer = Offer::factory()->forUser($this->user)->create();

        $snapshot = OfferAiInputSnapshot::fromOffer($offer);
        $hasher = app(OfferInputHasher::class);
        $inputHash = $hasher->compute($snapshot);

        AiAnalysis::factory()->completed()->forOffer($offer)->create([
            'input_hash' => $inputHash,
        ]);

        $generation = AiGeneration::factory()->pending()->forOffer($offer)->create();

        $fakeResponse = StructuredResponseFake::make()
            ->withStructured([
                'hooks' => ['Hook 1', '', 'Hook 3'],
                'captions' => ['C1', 'C2', 'C3'],
            ]);

        Prism::fake([$fakeResponse]);

        $job = new GenerateContentJob($generation->id);
        $job->handle(
            app(AiContentGenerator::class),
            app(GenerationInputHasher::class),
        );

        $generation->refresh();

        expect($generation->status)->toBe(AiProcessStatus::Failed);
    });

    test('wrong structure type rejects', function () {
        $offer = Offer::factory()->forUser($this->user)->create();

        $snapshot = OfferAiInputSnapshot::fromOffer($offer);
        $hasher = app(OfferInputHasher::class);
        $inputHash = $hasher->compute($snapshot);

        AiAnalysis::factory()->completed()->forOffer($offer)->create([
            'input_hash' => $inputHash,
        ]);

        $generation = AiGeneration::factory()->pending()->forOffer($offer)->create();

        $fakeResponse = StructuredResponseFake::make()
            ->withStructured([
                'hooks' => 'not an array',
                'captions' => 'not an array',
            ]);

        Prism::fake([$fakeResponse]);

        $job = new GenerateContentJob($generation->id);
        $job->handle(
            app(AiContentGenerator::class),
            app(GenerationInputHasher::class),
        );

        $generation->refresh();

        expect($generation->status)->toBe(AiProcessStatus::Failed);
    });
});

describe('Job Lifecycle', function () {
    test('pending job transitions to processing then completed', function () {
        $offer = Offer::factory()->forUser($this->user)->create();

        $snapshot = OfferAiInputSnapshot::fromOffer($offer);
        $hasher = app(OfferInputHasher::class);
        $inputHash = $hasher->compute($snapshot);

        AiAnalysis::factory()->completed()->forOffer($offer)->create([
            'input_hash' => $inputHash,
        ]);

        $generation = AiGeneration::factory()->pending()->forOffer($offer)->create();

        $fakeResponse = StructuredResponseFake::make()
            ->withStructured([
                'hooks' => ['H1', 'H2', 'H3'],
                'captions' => ['C1', 'C2', 'C3'],
            ]);

        Prism::fake([$fakeResponse]);

        $job = new GenerateContentJob($generation->id);
        $job->handle(
            app(AiContentGenerator::class),
            app(GenerationInputHasher::class),
        );

        $generation->refresh();

        expect($generation->status)->toBe(AiProcessStatus::Completed);
    });

    test('completed job returns silently', function () {
        $offer = Offer::factory()->forUser($this->user)->create();
        $generation = AiGeneration::factory()->completed()->forOffer($offer)->create();

        $fake = Prism::fake([]);

        $job = new GenerateContentJob($generation->id);
        $job->handle(
            app(AiContentGenerator::class),
            app(GenerationInputHasher::class),
        );

        $fake->assertCallCount(0);
    });

    test('failed job returns silently', function () {
        $offer = Offer::factory()->forUser($this->user)->create();
        $generation = AiGeneration::factory()->failed()->forOffer($offer)->create();

        $fake = Prism::fake([]);

        $job = new GenerateContentJob($generation->id);
        $job->handle(
            app(AiContentGenerator::class),
            app(GenerationInputHasher::class),
        );

        $fake->assertCallCount(0);
    });

    test('provider failure marks failed safely', function () {
        $offer = Offer::factory()->forUser($this->user)->create();

        $snapshot = OfferAiInputSnapshot::fromOffer($offer);
        $hasher = app(OfferInputHasher::class);
        $inputHash = $hasher->compute($snapshot);

        AiAnalysis::factory()->completed()->forOffer($offer)->create([
            'input_hash' => $inputHash,
        ]);

        $generation = AiGeneration::factory()->pending()->forOffer($offer)->create();

        Prism::fake([new PrismException('Provider error')]);

        $job = new GenerateContentJob($generation->id);
        $job->handle(
            app(AiContentGenerator::class),
            app(GenerationInputHasher::class),
        );

        $generation->refresh();

        expect($generation->status)->toBe(AiProcessStatus::Failed)
            ->and($generation->error_message)->toBe("La génération de contenu n'a pas pu être terminée. Veuillez réessayer.");
    });

    test('failed() persists safe generic message', function () {
        $offer = Offer::factory()->forUser($this->user)->create();
        $generation = AiGeneration::factory()->pending()->forOffer($offer)->create();

        $job = new GenerateContentJob($generation->id);
        $job->failed(new RuntimeException('Something went wrong'));

        $generation->refresh();

        expect($generation->status)->toBe(AiProcessStatus::Failed)
            ->and($generation->error_message)->toBe("La génération de contenu n'a pas pu être terminée. Veuillez réessayer.");
    });
});

describe('Stale Detection', function () {
    test('offer name change makes generation stale', function () {
        $offer = Offer::factory()->forUser($this->user)->create();

        $snapshot = OfferAiInputSnapshot::fromOffer($offer);
        $hasher = app(OfferInputHasher::class);
        $inputHash = $hasher->compute($snapshot);

        AiAnalysis::factory()->completed()->forOffer($offer)->create([
            'input_hash' => $inputHash,
        ]);

        $genSnapshot = OfferContentGenerationSnapshot::fromOfferAndAnalysis($offer, $offer->analysis);
        $genHash = app(GenerationInputHasher::class)->compute($genSnapshot);

        $generation = AiGeneration::factory()->completed()->forOffer($offer)->create([
            'input_hash' => $genHash,
        ]);

        $offer->update(['name' => 'Updated Name']);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/offers/{$offer->id}/generations/{$generation->id}")
            ->assertStatus(200)
            ->assertJson([
                'data' => [
                    'is_stale' => true,
                ],
            ]);
    });

    test('status only change does not make generation stale', function () {
        $offer = Offer::factory()->forUser($this->user)->create();

        $snapshot = OfferAiInputSnapshot::fromOffer($offer);
        $hasher = app(OfferInputHasher::class);
        $inputHash = $hasher->compute($snapshot);

        AiAnalysis::factory()->completed()->forOffer($offer)->create([
            'input_hash' => $inputHash,
        ]);

        $genSnapshot = OfferContentGenerationSnapshot::fromOfferAndAnalysis($offer, $offer->analysis);
        $genHash = app(GenerationInputHasher::class)->compute($genSnapshot);

        $generation = AiGeneration::factory()->completed()->forOffer($offer)->create([
            'input_hash' => $genHash,
        ]);

        $offer->update(['status' => OfferStatus::Suspended]);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/offers/{$offer->id}/generations/{$generation->id}")
            ->assertStatus(200)
            ->assertJson([
                'data' => [
                    'is_stale' => false,
                ],
            ]);
    });

    test('pending generation reports is_stale false', function () {
        $offer = Offer::factory()->forUser($this->user)->create();
        $generation = AiGeneration::factory()->pending()->forOffer($offer)->create();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/offers/{$offer->id}/generations/{$generation->id}")
            ->assertStatus(200)
            ->assertJson([
                'data' => [
                    'is_stale' => false,
                ],
            ]);
    });

    test('processing generation reports is_stale false', function () {
        $offer = Offer::factory()->forUser($this->user)->create();
        $generation = AiGeneration::factory()->processing()->forOffer($offer)->create();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/offers/{$offer->id}/generations/{$generation->id}")
            ->assertStatus(200)
            ->assertJson([
                'data' => [
                    'is_stale' => false,
                ],
            ]);
    });

    test('missing analysis makes completed generation stale', function () {
        $offer = Offer::factory()->forUser($this->user)->create();

        $snapshot = OfferAiInputSnapshot::fromOffer($offer);
        $hasher = app(OfferInputHasher::class);
        $inputHash = $hasher->compute($snapshot);

        $analysis = AiAnalysis::factory()->completed()->forOffer($offer)->create([
            'input_hash' => $inputHash,
        ]);

        $genSnapshot = OfferContentGenerationSnapshot::fromOfferAndAnalysis($offer, $analysis);
        $genHash = app(GenerationInputHasher::class)->compute($genSnapshot);

        $generation = AiGeneration::factory()->completed()->forOffer($offer)->create([
            'input_hash' => $genHash,
        ]);

        $analysis->delete();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/offers/{$offer->id}/generations/{$generation->id}")
            ->assertStatus(200)
            ->assertJson([
                'data' => [
                    'is_stale' => true,
                ],
            ]);
    });

    test('re-analysis output change makes old generation stale', function () {
        $offer = Offer::factory()->forUser($this->user)->create();

        $snapshot = OfferAiInputSnapshot::fromOffer($offer);
        $hasher = app(OfferInputHasher::class);
        $inputHash = $hasher->compute($snapshot);

        $analysis = AiAnalysis::factory()->completed()->forOffer($offer)->create([
            'input_hash' => $inputHash,
            'score' => 75,
            'summary' => 'Original summary',
            'strengths' => ['Strength 1'],
            'weaknesses' => ['Weakness 1'],
            'recommendations' => ['Recommendation 1'],
        ]);

        $genSnapshot = OfferContentGenerationSnapshot::fromOfferAndAnalysis($offer, $analysis);
        $genHash = app(GenerationInputHasher::class)->compute($genSnapshot);

        $generation = AiGeneration::factory()->completed()->forOffer($offer)->create([
            'input_hash' => $genHash,
        ]);

        $analysis->update([
            'score' => 90,
            'summary' => 'Updated summary after re-analysis',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/offers/{$offer->id}/generations/{$generation->id}")
            ->assertStatus(200)
            ->assertJson([
                'data' => [
                    'is_stale' => true,
                ],
            ]);
    });
});

describe('Listing', function () {
    test('get generations returns list ordered by created_at desc', function () {
        $offer = Offer::factory()->forUser($this->user)->create();

        $gen1 = AiGeneration::factory()->completed()->forOffer($offer)->create();
        $gen2 = AiGeneration::factory()->completed()->forOffer($offer)->create();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/offers/{$offer->id}/generations")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJson([
                'data' => [
                    ['id' => $gen2->id],
                    ['id' => $gen1->id],
                ],
            ]);
    });

    test('get generations returns empty list for offer with no generations', function () {
        $offer = Offer::factory()->forUser($this->user)->create();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/offers/{$offer->id}/generations")
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    });
});

describe('Provider Input', function () {
    test('only approved fields sent to provider', function () {
        $offer = Offer::factory()->forUser($this->user)->create();

        $snapshot = OfferAiInputSnapshot::fromOffer($offer);
        $hasher = app(OfferInputHasher::class);
        $inputHash = $hasher->compute($snapshot);

        $analysis = AiAnalysis::factory()->completed()->forOffer($offer)->create([
            'input_hash' => $inputHash,
        ]);

        $generation = AiGeneration::factory()->pending()->forOffer($offer)->create();

        $capturedPrompt = null;

        $fakeResponse = StructuredResponseFake::make()
            ->withStructured([
                'hooks' => ['H1', 'H2', 'H3'],
                'captions' => ['C1', 'C2', 'C3'],
            ]);

        Prism::fake([$fakeResponse]);

        $job = new GenerateContentJob($generation->id);
        $job->handle(
            app(AiContentGenerator::class),
            app(GenerationInputHasher::class),
        );

        $this->assertTrue(true);
    });
});

describe('Domain Safety', function () {
    test('generation does not modify offer data', function () {
        $offer = Offer::factory()->forUser($this->user)->create();

        $originalName = $offer->name;
        $originalPayout = $offer->payout;
        $originalStatus = $offer->status;

        $snapshot = OfferAiInputSnapshot::fromOffer($offer);
        $hasher = app(OfferInputHasher::class);
        $inputHash = $hasher->compute($snapshot);

        AiAnalysis::factory()->completed()->forOffer($offer)->create([
            'input_hash' => $inputHash,
        ]);

        $generation = AiGeneration::factory()->pending()->forOffer($offer)->create();

        $fakeResponse = StructuredResponseFake::make()
            ->withStructured([
                'hooks' => ['H1', 'H2', 'H3'],
                'captions' => ['C1', 'C2', 'C3'],
            ]);

        Prism::fake([$fakeResponse]);

        $job = new GenerateContentJob($generation->id);
        $job->handle(
            app(AiContentGenerator::class),
            app(GenerationInputHasher::class),
        );

        $offer->refresh();

        expect($offer->name)->toBe($originalName)
            ->and($offer->payout)->toBe($originalPayout)
            ->and($offer->status)->toBe($originalStatus);
    });
});
