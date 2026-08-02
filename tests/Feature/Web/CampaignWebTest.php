<?php

use App\Enums\CampaignStatus;
use App\Enums\OfferStatus;
use App\Models\Campaign;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('Campaign Web — Guest Access', function () {
    test('guest is redirected from campaigns index', function () {
        $response = $this->get(route('campaigns.index'));
        $response->assertRedirect(route('login'));
    });

    test('guest is redirected from campaign create page', function () {
        $response = $this->get(route('campaigns.create'));
        $response->assertRedirect(route('login'));
    });
});

describe('Campaign Web — Index', function () {
    test('authenticated user can view campaigns index', function () {
        $response = $this->actingAs($this->user)->get(route('campaigns.index'));
        $response->assertOk();
    });

    test('user sees only their own campaigns', function () {
        $otherUser = User::factory()->create();
        $myOffer = Offer::factory()->for($this->user)->create();
        $otherOffer = Offer::factory()->for($otherUser)->create();
        $myCampaign = Campaign::factory()->for($myOffer)->create(['name' => 'My Campaign']);
        $otherCampaign = Campaign::factory()->for($otherOffer)->create(['name' => 'Other Campaign']);

        $response = $this->actingAs($this->user)->get(route('campaigns.index'));
        $response->assertOk();
        $response->assertSee('My Campaign');
        $response->assertDontSee('Other Campaign');
    });

    test('empty state renders when no campaigns exist', function () {
        $response = $this->actingAs($this->user)->get(route('campaigns.index'));
        $response->assertOk();
        $response->assertSee('No campaigns yet');
    });
});

describe('Campaign Web — Create', function () {
    test('create form renders with eligible offers', function () {
        Offer::factory()->for($this->user)->draft()->create();
        Offer::factory()->for($this->user)->state(['status' => OfferStatus::Archived])->create();

        $response = $this->actingAs($this->user)->get(route('campaigns.create'));
        $response->assertOk();
        $response->assertSee('Create Campaign');
    });

    test('campaign creation succeeds', function () {
        $offer = Offer::factory()->for($this->user)->draft()->create();

        $response = $this->actingAs($this->user)->post(route('campaigns.store'), [
            'offer_id' => $offer->id,
            'name' => 'New Campaign',
            'traffic_source' => 'Google Ads',
            'budget' => '500.00',
        ]);

        $response->assertRedirect(route('campaigns.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('campaigns', [
            'name' => 'New Campaign',
            'offer_id' => $offer->id,
        ]);
    });

    test('archived offer cannot be used for campaign', function () {
        $offer = Offer::factory()->for($this->user)->state(['status' => OfferStatus::Archived])->create();

        $response = $this->actingAs($this->user)->post(route('campaigns.store'), [
            'offer_id' => $offer->id,
            'name' => 'Should Fail',
            'traffic_source' => 'Facebook',
            'budget' => '100.00',
        ]);

        $response->assertSessionHasErrors('offer_id');
    });
});

describe('Campaign Web — Show', function () {
    test('campaign show page renders', function () {
        $offer = Offer::factory()->for($this->user)->create();
        $campaign = Campaign::factory()->for($offer)->create(['name' => 'Show Campaign']);

        $response = $this->actingAs($this->user)->get(route('campaigns.show', $campaign));
        $response->assertOk();
        $response->assertSee('Show Campaign');
        $response->assertSee('Campaign Details');
    });

    test('foreign campaign cannot be viewed', function () {
        $otherUser = User::factory()->create();
        $offer = Offer::factory()->for($otherUser)->create();
        $campaign = Campaign::factory()->for($offer)->create();

        $response = $this->actingAs($this->user)->get(route('campaigns.show', $campaign));
        $response->assertStatus(403);
    });
});

describe('Campaign Web — Edit', function () {
    test('edit form renders', function () {
        $offer = Offer::factory()->for($this->user)->create();
        $campaign = Campaign::factory()->for($offer)->create(['name' => 'Edit Campaign']);

        $response = $this->actingAs($this->user)->get(route('campaigns.edit', $campaign));
        $response->assertOk();
        $response->assertSee('Edit Campaign');
    });

    test('campaign update succeeds', function () {
        $offer = Offer::factory()->for($this->user)->create();
        $campaign = Campaign::factory()->for($offer)->create(['name' => 'Old Name']);

        $response = $this->actingAs($this->user)->patch(route('campaigns.update', $campaign), [
            'name' => 'New Name',
            'traffic_source' => $campaign->traffic_source,
            'budget' => $campaign->budget,
        ]);

        $response->assertRedirect(route('campaigns.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'name' => 'New Name',
        ]);
    });

    test('foreign campaign cannot be edited', function () {
        $otherUser = User::factory()->create();
        $offer = Offer::factory()->for($otherUser)->create();
        $campaign = Campaign::factory()->for($offer)->create();

        $response = $this->actingAs($this->user)->get(route('campaigns.edit', $campaign));
        $response->assertStatus(403);
    });
});

describe('Campaign Web — Lifecycle', function () {
    test('draft campaign can be activated', function () {
        $offer = Offer::factory()->for($this->user)->create();
        $campaign = Campaign::factory()->for($offer)->create();

        $response = $this->actingAs($this->user)->post(route('campaigns.activate', $campaign));
        $response->assertRedirect(route('campaigns.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'status' => CampaignStatus::Active->value,
        ]);
    });

    test('active campaign can be suspended', function () {
        $offer = Offer::factory()->for($this->user)->create();
        $campaign = Campaign::factory()->for($offer)->active()->create();

        $response = $this->actingAs($this->user)->post(route('campaigns.suspend', $campaign));
        $response->assertRedirect(route('campaigns.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'status' => CampaignStatus::Suspended->value,
        ]);
    });

    test('foreign campaign cannot be activated', function () {
        $otherUser = User::factory()->create();
        $offer = Offer::factory()->for($otherUser)->create();
        $campaign = Campaign::factory()->for($offer)->create();

        $response = $this->actingAs($this->user)->post(route('campaigns.activate', $campaign));
        $response->assertStatus(403);
    });
});

describe('Campaign Web — Tracking Links', function () {
    test('active campaign shows generate link button', function () {
        $offer = Offer::factory()->for($this->user)->create();
        $campaign = Campaign::factory()->for($offer)->active()->create();

        $response = $this->actingAs($this->user)->get(route('campaigns.show', $campaign));
        $response->assertOk();
        $response->assertSee('Generate Link');
    });

    test('active campaign can generate tracking link', function () {
        $offer = Offer::factory()->for($this->user)->create();
        $campaign = Campaign::factory()->for($offer)->active()->create();

        $response = $this->actingAs($this->user)->post(route('campaigns.tracking-links.store', $campaign));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tracking_links', [
            'campaign_id' => $campaign->id,
        ]);
    });

    test('draft campaign can generate tracking link', function () {
        $offer = Offer::factory()->for($this->user)->create();
        $campaign = Campaign::factory()->for($offer)->create();

        $response = $this->actingAs($this->user)->post(route('campaigns.tracking-links.store', $campaign));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tracking_links', [
            'campaign_id' => $campaign->id,
        ]);
    });

    test('generated tracking url is displayed', function () {
        $offer = Offer::factory()->for($this->user)->create();
        $campaign = Campaign::factory()->for($offer)->active()->create();

        $this->actingAs($this->user)->post(route('campaigns.tracking-links.store', $campaign));

        $response = $this->actingAs($this->user)->get(route('campaigns.show', $campaign));
        $response->assertOk();
        $response->assertSee('/t/');
    });
});

describe('Campaign Web — Flash Messages', function () {
    test('success flash renders after campaign creation', function () {
        $offer = Offer::factory()->for($this->user)->create();

        $response = $this->actingAs($this->user)->post(route('campaigns.store'), [
            'offer_id' => $offer->id,
            'name' => 'Flash Test',
            'traffic_source' => 'Test',
            'budget' => '100.00',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Campaign created successfully.');
    });
});

describe('Campaign Web — Navigation', function () {
    test('navigation links are present', function () {
        $response = $this->actingAs($this->user)->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('Overview');
        $response->assertSee('Offers');
        $response->assertSee('Campaigns');
    });

    test('dashboard renders without analytics', function () {
        $response = $this->actingAs($this->user)->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('Welcome back');
        $response->assertSee('Create Offer');
        $response->assertSee('Create Campaign');
    });
});
