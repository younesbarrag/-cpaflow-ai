<?php

use App\Actions\TrackingLink\GenerateTrackingLinkAction;
use App\Enums\CampaignStatus;
use App\Exceptions\CannotGenerateTrackingLink;
use App\Models\Campaign;
use App\Models\TrackingLink;
use App\Services\TrackingLink\TrackingCodeGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

final class SequenceTrackingCodeGenerator extends TrackingCodeGenerator
{
    public int $calls = 0;

    /**
     * @param  array<int, string>  $codes
     */
    public function __construct(
        private array $codes,
    ) {}

    public function generate(): string
    {
        $this->calls++;

        $code = array_shift($this->codes);

        if (! is_string($code)) {
            throw new RuntimeException(
                'No tracking code is configured for this test.'
            );
        }

        return $code;
    }
}

it('enforces tracking code uniqueness at database level', function (): void {
    $campaign = Campaign::factory()->create([
        'status' => CampaignStatus::Active,
    ]);

    $code = str_repeat('A', 32);

    TrackingLink::factory()
        ->for($campaign)
        ->create([
            'code' => $code,
        ]);

    expect(
        fn () => TrackingLink::factory()
            ->for($campaign)
            ->create([
                'code' => $code,
            ])
    )->toThrow(UniqueConstraintViolationException::class);

    assertDatabaseCount('tracking_links', 1);
});

it('retries after a unique code collision and persists the next code', function (): void {
    $campaign = Campaign::factory()->create([
        'status' => CampaignStatus::Active,
    ]);

    $duplicateCode = str_repeat('B', 32);
    $uniqueCode = str_repeat('C', 32);

    TrackingLink::factory()
        ->for($campaign)
        ->create([
            'code' => $duplicateCode,
        ]);

    $generator = new SequenceTrackingCodeGenerator([
        $duplicateCode,
        $uniqueCode,
    ]);

    $action = new GenerateTrackingLinkAction($generator);

    $trackingLink = $action->execute($campaign);

    expect($generator->calls)
        ->toBe(2)
        ->and($trackingLink->code)
        ->toBe($uniqueCode)
        ->and($trackingLink->campaign_id)
        ->toBe($campaign->id);

    assertDatabaseCount('tracking_links', 2);

    assertDatabaseHas('tracking_links', [
        'campaign_id' => $campaign->id,
        'code' => $uniqueCode,
    ]);
});

it('throws a domain exception after five verified collisions', function (): void {
    $campaign = Campaign::factory()->create([
        'status' => CampaignStatus::Active,
    ]);

    $duplicateCode = str_repeat('D', 32);

    TrackingLink::factory()
        ->for($campaign)
        ->create([
            'code' => $duplicateCode,
        ]);

    $generator = new SequenceTrackingCodeGenerator(
        array_fill(0, 5, $duplicateCode)
    );

    $action = new GenerateTrackingLinkAction($generator);

    expect(
        fn () => $action->execute($campaign)
    )->toThrow(CannotGenerateTrackingLink::class);

    expect($generator->calls)->toBe(5);

    assertDatabaseCount('tracking_links', 1);
});

it('does not retry an unrelated database exception', function (): void {
    $generator = new SequenceTrackingCodeGenerator([
        str_repeat('E', 32),
    ]);

    $campaign = Campaign::factory()->create([
        'status' => CampaignStatus::Active,
    ]);

    $campaignId = $campaign->id;

    $campaign->delete();

    $action = new GenerateTrackingLinkAction($generator);

    expect(
        fn () => $action->execute($campaign)
    )->toThrow(QueryException::class);

    expect($generator->calls)->toBe(1);

    assertDatabaseCount('tracking_links', 0);
});
