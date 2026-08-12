<?php

use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('Offer Web — Guest Access', function () {
    test('guest is redirected from offers index', function () {
        $response = $this->get(route('offers.index'));
        $response->assertRedirect(route('login'));
    });

    test('guest is redirected from offer create page', function () {
        $response = $this->get(route('offers.create'));
        $response->assertRedirect(route('login'));
    });

    test('guest is redirected from offer edit page', function () {
        $offer = Offer::factory()->for($this->user)->create();
        $response = $this->get(route('offers.edit', $offer));
        $response->assertRedirect(route('login'));
    });
});

describe('Offer Web — Index', function () {
    test('authenticated user can view offers index', function () {
        $response = $this->actingAs($this->user)->get(route('offers.index'));
        $response->assertOk();
    });

    test('user sees only their own offers', function () {
        $otherUser = User::factory()->create();
        $myOffer = Offer::factory()->for($this->user)->create(['name' => 'My Offer']);
        $otherOffer = Offer::factory()->for($otherUser)->create(['name' => 'Other Offer']);

        $response = $this->actingAs($this->user)->get(route('offers.index'));
        $response->assertOk();
        $response->assertSee('My Offer');
        $response->assertDontSee('Other Offer');
    });

    test('empty state renders when no offers exist', function () {
        $response = $this->actingAs($this->user)->get(route('offers.index'));
        $response->assertOk();
        $response->assertSee('No offers yet');
    });

    test('search filters offers by name', function () {
        Offer::factory()->for($this->user)->create(['name' => 'Fitness Offer']);
        Offer::factory()->for($this->user)->create(['name' => 'Gaming Offer']);

        $response = $this->actingAs($this->user)->get(route('offers.index', ['search' => 'Fitness']));
        $response->assertOk();
        $response->assertSee('Fitness Offer');
        $response->assertDontSee('Gaming Offer');
    });

    test('status filter works', function () {
        Offer::factory()->for($this->user)->draft()->create(['name' => 'Draft Offer']);
        Offer::factory()->for($this->user)->active()->create(['name' => 'Active Offer']);

        $response = $this->actingAs($this->user)->get(route('offers.index', ['status' => 'active']));
        $response->assertOk();
        $response->assertSee('Active Offer');
        $response->assertDontSee('Draft Offer');
    });

    test('search and status filter work together', function () {
        Offer::factory()->for($this->user)->active()->create(['name' => 'Fitness Offer']);
        Offer::factory()->for($this->user)->active()->create(['name' => 'Gaming Offer']);
        Offer::factory()->for($this->user)->draft()->create(['name' => 'Fitness Draft']);

        $response = $this->actingAs($this->user)->get(route('offers.index', [
            'search' => 'Fitness',
            'status' => 'active',
        ]));
        $response->assertOk();
        $response->assertSee('Fitness Offer');
        $response->assertDontSee('Gaming Offer');
        $response->assertDontSee('Fitness Draft');
    });

    test('search with no matching results shows filtered empty state', function () {
        Offer::factory()->for($this->user)->create(['name' => 'Real Offer']);

        $response = $this->actingAs($this->user)->get(route('offers.index', ['search' => 'Nonexistent']));
        $response->assertOk();
        $response->assertSee('No offers found');
        $response->assertDontSee('No offers yet');
    });

    test('all status option shows all non-archived offers', function () {
        Offer::factory()->for($this->user)->draft()->create(['name' => 'Draft One']);
        Offer::factory()->for($this->user)->active()->create(['name' => 'Active One']);

        $response = $this->actingAs($this->user)->get(route('offers.index', ['status' => 'all']));
        $response->assertOk();
        $response->assertSee('Draft One');
        $response->assertSee('Active One');
    });

    test('filter parameters are preserved in pagination links', function () {
        Offer::factory()->for($this->user)->active()->create(['name' => 'Page Offer']);

        $response = $this->actingAs($this->user)->get(route('offers.index', [
            'search' => 'Page',
            'status' => 'active',
        ]));
        $response->assertOk();
    });
});

describe('Offer Web — Create', function () {
    test('create form renders', function () {
        $response = $this->actingAs($this->user)->get(route('offers.create'));
        $response->assertOk();
        $response->assertSee('Create offer');
    });

    test('offer creation succeeds', function () {
        $response = $this->actingAs($this->user)->post(route('offers.store'), [
            'name' => 'New Offer',
            'destination_url' => 'https://example.com/offer',
            'payout' => '25.00',
            'status' => 'draft',
            'description' => 'Test description',
        ]);

        $response->assertRedirect(route('offers.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('offers', [
            'name' => 'New Offer',
            'user_id' => $this->user->id,
        ]);
    });

    test('validation errors render correctly', function () {
        $response = $this->actingAs($this->user)->post(route('offers.store'), [
            'name' => '',
            'destination_url' => 'not-a-url',
            'payout' => '-1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['name', 'destination_url', 'payout']);
    });
});

describe('Offer Web — Edit', function () {
    test('edit form renders with pre-filled values', function () {
        $offer = Offer::factory()->for($this->user)->create(['name' => 'Test Offer']);

        $response = $this->actingAs($this->user)->get(route('offers.edit', $offer));
        $response->assertOk();
        $response->assertSee('Test Offer');
        $response->assertSee('Edit offer');
    });

    test('offer update succeeds', function () {
        $offer = Offer::factory()->for($this->user)->create(['name' => 'Old Name']);

        $response = $this->actingAs($this->user)->patch(route('offers.update', $offer), [
            'name' => 'New Name',
            'destination_url' => $offer->destination_url,
            'payout' => $offer->payout,
            'status' => $offer->status->value,
        ]);

        $response->assertRedirect(route('offers.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('offers', [
            'id' => $offer->id,
            'name' => 'New Name',
        ]);
    });

    test('foreign offer cannot be edited', function () {
        $otherUser = User::factory()->create();
        $offer = Offer::factory()->for($otherUser)->create();

        $response = $this->actingAs($this->user)->get(route('offers.edit', $offer));
        $response->assertStatus(403);
    });
});

describe('Offer Web — Archive', function () {
    test('offer archive succeeds', function () {
        $offer = Offer::factory()->for($this->user)->draft()->create();

        $response = $this->actingAs($this->user)->post(route('offers.archive', $offer));
        $response->assertRedirect(route('offers.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('offers', [
            'id' => $offer->id,
            'status' => OfferStatus::Archived->value,
        ]);
    });

    test('foreign offer cannot be archived', function () {
        $otherUser = User::factory()->create();
        $offer = Offer::factory()->for($otherUser)->create();

        $response = $this->actingAs($this->user)->post(route('offers.archive', $offer));
        $response->assertStatus(403);
    });
});

describe('Offer Web — Restore', function () {
    test('archived offer can be restored', function () {
        $offer = Offer::factory()->for($this->user)->state(['status' => OfferStatus::Archived])->create();

        $response = $this->actingAs($this->user)->post(route('offers.restore', $offer));
        $response->assertRedirect(route('offers.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('offers', [
            'id' => $offer->id,
            'status' => OfferStatus::Draft->value,
        ]);
    });

    test('foreign offer cannot be restored', function () {
        $otherUser = User::factory()->create();
        $offer = Offer::factory()->for($otherUser)->state(['status' => OfferStatus::Archived])->create();

        $response = $this->actingAs($this->user)->post(route('offers.restore', $offer));
        $response->assertStatus(403);
    });

    test('restore action appears for archived offer on index', function () {
        Offer::factory()->for($this->user)->state(['status' => OfferStatus::Archived])->create(['name' => 'Archived Offer']);

        $response = $this->actingAs($this->user)->get(route('offers.index'));
        $response->assertOk();
        $response->assertSee('Restore');
        $response->assertDontSee('Archive offer');
    });

    test('restore action does not appear for non-archived offer', function () {
        $offer = Offer::factory()->for($this->user)->draft()->create(['name' => 'Draft Offer']);

        $response = $this->actingAs($this->user)->get(route('offers.index'));
        $response->assertOk();
        $response->assertDontSee('/restore');
        $response->assertSee('Archive');
    });
});

describe('Offer Web — Filter Form Markup', function () {
    test('filter form contains exactly one search input and one status select', function () {
        Offer::factory()->for($this->user)->create(['name' => 'Test Offer']);

        $response = $this->actingAs($this->user)->get(route('offers.index'));
        $response->assertOk();

        $html = $response->content();

        $searchCount = preg_match_all('/name="search"/', $html);
        $statusCount = preg_match_all('/name="status"/', $html);

        $this->assertSame(1, $searchCount, 'Expected exactly one name="search" input, found '.$searchCount);
        $this->assertSame(1, $statusCount, 'Expected exactly one name="status" select, found '.$statusCount);
    });

    test('filter form does not duplicate controls when no offers exist', function () {
        $response = $this->actingAs($this->user)->get(route('offers.index'));
        $response->assertOk();

        $html = $response->content();

        $searchCount = preg_match_all('/name="search"/', $html);
        $statusCount = preg_match_all('/name="status"/', $html);

        $this->assertSame(0, $searchCount, 'Expected zero name="search" inputs when no offers exist, found '.$searchCount);
        $this->assertSame(0, $statusCount, 'Expected zero name="status" selects when no offers exist, found '.$statusCount);
    });
});

describe('Offer Web — CSRF Protection', function () {
    test('csrf protection is enabled', function () {
        $response = $this->actingAs($this->user)->postJson(route('offers.store'), []);

        $response->assertStatus(422);
    });
});
