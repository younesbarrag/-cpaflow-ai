<?php

use App\Actions\Dashboard\GetDashboardStatisticsAction;
use App\DTOs\OfferAiInputSnapshot;
use App\DTOs\OfferContentGenerationSnapshot;
use App\Enums\AiProcessStatus;
use App\Models\AiAnalysis;
use App\Models\AiGeneration;
use App\Models\Campaign;
use App\Models\CampaignExpense;
use App\Models\Conversion;
use App\Models\Offer;
use App\Models\TrackingClick;
use App\Models\User;
use App\Services\GenerationInputHasher;
use App\Services\OfferInputHasher;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seeder = new DemoDataSeeder;
});

it('creates the demo admin account', function (): void {
    $this->seeder->run();

    $admin = User::where('email', 'admin@example.test')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->name)->toBe('Demo Admin')
        ->and($admin->role->value)->toBe('admin');
});

it('creates the primary affiliate account', function (): void {
    $this->seeder->run();

    $affiliate = User::where('email', 'affiliate@example.test')->first();

    expect($affiliate)->not->toBeNull()
        ->and($affiliate->name)->toBe('Demo Affiliate')
        ->and($affiliate->role->value)->toBe('affiliate');
});

it('creates the secondary affiliate account', function (): void {
    $this->seeder->run();

    $affiliate2 = User::where('email', 'affiliate2@example.test')->first();

    expect($affiliate2)->not->toBeNull()
        ->and($affiliate2->name)->toBe('Demo Affiliate 2')
        ->and($affiliate2->role->value)->toBe('affiliate');
});

it('creates exactly 3 demo offers', function (): void {
    $this->seeder->run();

    $affiliate = User::where('email', 'affiliate@example.test')->first();
    $count = Offer::where('user_id', $affiliate->id)
        ->where('name', 'like', 'DEMO%')
        ->count();

    expect($count)->toBe(3);
});

it('creates exactly 2 demo campaigns', function (): void {
    $this->seeder->run();

    $count = Campaign::where('name', 'like', 'DEMO%')->count();

    expect($count)->toBe(2);
});

it('creates exactly 3 demo tracking clicks', function (): void {
    $this->seeder->run();

    $count = TrackingClick::where('utm_content', 'like', 'demo-click-%')->count();

    expect($count)->toBe(3);
});

it('creates exactly 3 demo conversions', function (): void {
    $this->seeder->run();

    $count = Conversion::where('external_id', 'like', 'demo-conversion-%')->count();

    expect($count)->toBe(3);
});

it('creates exactly 2 demo campaign expenses', function (): void {
    $this->seeder->run();

    $count = CampaignExpense::where('description', 'like', 'DEMO%')->count();

    expect($count)->toBe(2);
});

it('creates a completed non-stale AiAnalysis', function (): void {
    $this->seeder->run();

    $activeOffer = Offer::where('name', 'DEMO — Fitness Offer')->first();
    $analysis = AiAnalysis::where('offer_id', $activeOffer->id)->first();

    expect($analysis)->not->toBeNull()
        ->and($analysis->status)->toBe(AiProcessStatus::Completed)
        ->and($analysis->score)->toBe(85);

    $snapshot = OfferAiInputSnapshot::fromOffer($activeOffer);
    $expectedHash = (new OfferInputHasher)->compute($snapshot);

    expect($analysis->input_hash)->toBe($expectedHash);
});

it('creates a completed non-stale AiGeneration', function (): void {
    $this->seeder->run();

    $activeOffer = Offer::where('name', 'DEMO — Fitness Offer')->first();
    $analysis = AiAnalysis::where('offer_id', $activeOffer->id)->first();
    $generation = AiGeneration::where('offer_id', $activeOffer->id)->first();

    expect($generation)->not->toBeNull()
        ->and($generation->status)->toBe(AiProcessStatus::Completed)
        ->and($generation->hooks)->toBeArray()
        ->and($generation->captions)->toBeArray();

    $snapshot = OfferContentGenerationSnapshot::fromOfferAndAnalysis($activeOffer, $analysis);
    $expectedHash = (new GenerationInputHasher)->compute($snapshot);

    expect($generation->input_hash)->toBe($expectedHash);
});

it('produces correct all-time dashboard totals', function (): void {
    $this->seeder->run();

    $affiliate = User::where('email', 'affiliate@example.test')->first();
    $stats = (new GetDashboardStatisticsAction)->execute($affiliate);

    expect($stats['offer_count'])->toBe(3)
        ->and($stats['campaign_count'])->toBe(2)
        ->and($stats['active_campaign_count'])->toBe(1)
        ->and($stats['click_count'])->toBe(3)
        ->and($stats['conversion_count'])->toBe(3)
        ->and($stats['revenue'])->toBe(50.0)
        ->and($stats['total_expenses'])->toBe(70.0)
        ->and($stats['profit'])->toBe(-20.0);
});

it('is idempotent — running twice preserves counts', function (): void {
    $this->seeder->run();
    $this->seeder->run();

    $affiliate = User::where('email', 'affiliate@example.test')->first();

    expect(User::whereIn('email', ['admin@example.test', 'affiliate@example.test', 'affiliate2@example.test'])->count())->toBe(3)
        ->and(Offer::where('user_id', $affiliate->id)->where('name', 'like', 'DEMO%')->count())->toBe(3)
        ->and(Campaign::where('name', 'like', 'DEMO%')->count())->toBe(2)
        ->and(TrackingClick::where('utm_content', 'like', 'demo-click-%')->count())->toBe(3)
        ->and(Conversion::where('external_id', 'like', 'demo-conversion-%')->count())->toBe(3)
        ->and(CampaignExpense::where('description', 'like', 'DEMO%')->count())->toBe(2);
});

it('is idempotent — running twice preserves dashboard totals', function (): void {
    $this->seeder->run();
    $this->seeder->run();

    $affiliate = User::where('email', 'affiliate@example.test')->first();
    $stats = (new GetDashboardStatisticsAction)->execute($affiliate);

    expect($stats['revenue'])->toBe(50.0)
        ->and($stats['total_expenses'])->toBe(70.0)
        ->and($stats['profit'])->toBe(-20.0);
});

it('refuses to run in production environment', function (): void {
    $original = app()->environment();

    try {
        app()->bind('env', fn () => 'production');

        $this->seeder->run();

        $this->fail('Expected RuntimeException was not thrown.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('production');
    } finally {
        app()->bind('env', fn () => $original);
    }
});

it('does not make any network or queue calls', function (): void {
    Http::fake();
    Queue::fake();

    $this->seeder->run();

    $affiliate = User::where('email', 'affiliate@example.test')->first();

    expect($affiliate)->not->toBeNull();
});
