<?php

use App\Models\Campaign;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

/*
 * N+1 Query Verification
 *
 * These tests verify that key pages render without lazy-loading N+1 issues.
 * The approach: seed a fixed number of related records, enable query logging,
 * hit the page, then assert total queries stay bounded.
 *
 * We do not assert exact counts (framework internals vary). Instead we assert
 * an upper bound that would be violated by a per-row N+1 query pattern.
 */

describe('N+1 — Dashboard', function () {
    test('dashboard renders with bounded queries regardless of offer/campaign count', function () {
        $offers = Offer::factory()->for($this->user)->count(10)->create();
        foreach ($offers as $offer) {
            Campaign::factory()->for($offer)->count(3)->create();
        }
        // 10 offers × 3 campaigns = 30 campaigns total

        Model::preventLazyLoading(! $this->app->isProduction());

        DB::enableQueryLog();
        $response = $this->actingAs($this->user)->get(route('dashboard'));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk();

        // Dashboard runs: 2 COUNT queries, 1 query for recent offers, 1 query for recent campaigns
        // (with eager-loaded offer). Total <= 4 data queries + auth/session overhead.
        // With N+1 on 30 campaigns, total would be 1 + 30 = 31+ queries for campaigns alone.
        $this->assertLessThanOrEqual(12, count($queries),
            'Dashboard has too many queries ('.count($queries).'). Possible N+1 on campaigns or offers.'
        );
    });
});

describe('N+1 — Offer Index', function () {
    test('offer index renders with bounded queries on 15+ offers', function () {
        Offer::factory()->for($this->user)->count(20)->create();

        Model::preventLazyLoading(! $this->app->isProduction());

        DB::enableQueryLog();
        $response = $this->actingAs($this->user)->get(route('offers.index'));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk();

        // Offer index: 1 paginate query + auth/session. No relationships accessed.
        // With 20 offers it should be ~3-5 total queries.
        $this->assertLessThanOrEqual(8, count($queries),
            'Offer index has too many queries ('.count($queries).'). Possible N+1.'
        );
    });
});

describe('N+1 — Campaign Index', function () {
    test('campaign index renders with bounded queries on 15+ campaigns', function () {
        $offer = Offer::factory()->for($this->user)->create();
        Campaign::factory()->for($offer)->count(20)->create();

        Model::preventLazyLoading(! $this->app->isProduction());

        DB::enableQueryLog();
        $response = $this->actingAs($this->user)->get(route('campaigns.index'));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk();

        // Campaign index: 1 paginate query (with eager-loaded offer) + auth/session.
        // With eager loading, 20 campaigns should NOT produce 20+ additional queries.
        // Upper bound: ~6 queries total.
        $this->assertLessThanOrEqual(8, count($queries),
            'Campaign index has too many queries ('.count($queries).'). Possible N+1 on offer relationship.'
        );
    });
});

describe('N+1 — Campaign Show', function () {
    test('campaign show renders with bounded queries even with multiple tracking links', function () {
        $offer = Offer::factory()->for($this->user)->create();
        $campaign = Campaign::factory()->for($offer)->create();

        // Create 10 tracking links for this campaign
        foreach (range(1, 10) as $i) {
            $campaign->trackingLinks()->create([
                'code' => 'code_'.$i.'_'.Str::random(20),
            ]);
        }

        Model::preventLazyLoading(! $this->app->isProduction());

        DB::enableQueryLog();
        $response = $this->actingAs($this->user)->get(route('campaigns.show', $campaign));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk();

        // Campaign show: 1 campaign load, 1 offer load, 1 tracking links load + auth/session.
        // With eager loading, 10 tracking links should NOT produce 10+ additional queries.
        // Upper bound: ~6 queries total.
        $this->assertLessThanOrEqual(8, count($queries),
            'Campaign show has too many queries ('.count($queries).'). Possible N+1 on trackingLinks or offer.'
        );
    });
});
