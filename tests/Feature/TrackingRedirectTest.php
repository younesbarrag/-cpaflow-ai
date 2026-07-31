<?php

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Offer;
use App\Models\TrackingClick;
use App\Models\TrackingLink;
use App\Models\User;
use App\Services\TrackingLink\IpHasher;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;

it('records one click for a valid active link', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => 'https://example.com/offer',
    ]);
    $campaign = Campaign::factory()->for($offer)->active()->create();
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    get(route('tracking.redirect', $trackingLink->code));

    assertDatabaseHas('tracking_clicks', [
        'tracking_link_id' => $trackingLink->id,
    ]);
});

it('redirects to Offer.destination_url', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => 'https://example.com/offer',
    ]);
    $campaign = Campaign::factory()->for($offer)->active()->create();
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $response = get(route('tracking.redirect', $trackingLink->code));

    $response->assertRedirect('https://example.com/offer');
});

it('returns 302 status', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => 'https://example.com/offer',
    ]);
    $campaign = Campaign::factory()->for($offer)->active()->create();
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $response = get(route('tracking.redirect', $trackingLink->code));

    $response->assertStatus(302);
});

it('returns exact redirect location', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => 'https://example.com/offer?param=value',
    ]);
    $campaign = Campaign::factory()->for($offer)->active()->create();
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $response = get(route('tracking.redirect', $trackingLink->code));

    $response->assertRedirect('https://example.com/offer?param=value');
});

it('sets created_at as the click-record time', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => 'https://example.com/offer',
    ]);
    $campaign = Campaign::factory()->for($offer)->active()->create();
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    get(route('tracking.redirect', $trackingLink->code));

    $click = TrackingClick::where('tracking_link_id', $trackingLink->id)->first();

    expect($click)->not->toBeNull();
    expect($click->created_at)->not->toBeNull();
    expect($click->created_at->diffInSeconds(now()))->toBeLessThan(5);
});

it('click belongs to the correct TrackingLink', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => 'https://example.com/offer',
    ]);
    $campaign = Campaign::factory()->for($offer)->active()->create();
    $link1 = TrackingLink::factory()->for($campaign)->create();
    $link2 = TrackingLink::factory()->for($campaign)->create();

    get(route('tracking.redirect', $link1->code));

    assertDatabaseHas('tracking_clicks', [
        'tracking_link_id' => $link1->id,
    ]);
    assertDatabaseCount('tracking_clicks', 1);
});

it('returns 404 for unknown code', function (): void {
    $response = get('/t/nonexistent123');

    $response->assertNotFound();
    assertDatabaseCount('tracking_clicks', 0);
});

it('returns 404 for draft Campaign', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => 'https://example.com/offer',
    ]);
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Draft,
    ]);
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $response = get(route('tracking.redirect', $trackingLink->code));

    $response->assertNotFound();
    assertDatabaseCount('tracking_clicks', 0);
});

it('returns 404 for suspended Campaign', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => 'https://example.com/offer',
    ]);
    $campaign = Campaign::factory()->for($offer)->suspended()->create();
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $response = get(route('tracking.redirect', $trackingLink->code));

    $response->assertNotFound();
    assertDatabaseCount('tracking_clicks', 0);
});

it('does not reveal Campaign state in 404 responses', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => 'https://example.com/offer',
    ]);

    $draftCampaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Draft,
    ]);
    $draftLink = TrackingLink::factory()->for($draftCampaign)->create();

    $suspendedCampaign = Campaign::factory()->for($offer)->suspended()->create();
    $suspendedLink = TrackingLink::factory()->for($suspendedCampaign)->create();

    $unknownResponse = get('/t/nonexistent123');
    $draftResponse = get(route('tracking.redirect', $draftLink->code));
    $suspendedResponse = get(route('tracking.redirect', $suspendedLink->code));

    expect($unknownResponse->status())->toBe($draftResponse->status());
    expect($draftResponse->status())->toBe($suspendedResponse->status());
});

it('works without authentication', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => 'https://example.com/offer',
    ]);
    $campaign = Campaign::factory()->for($offer)->active()->create();
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $response = get(route('tracking.redirect', $trackingLink->code));

    $response->assertStatus(302);
    $response->assertRedirect('https://example.com/offer');
});

it('resolves a generated KAN-14 URL through KAN-15 route', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => 'https://example.com/offer',
    ]);
    $campaign = Campaign::factory()->for($offer)->active()->create();
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $generatedUrl = url('/t/'.$trackingLink->code);
    $routeUrl = route('tracking.redirect', $trackingLink->code);

    expect($generatedUrl)->toBe($routeUrl);

    $response = get($generatedUrl);

    $response->assertStatus(302);
    $response->assertRedirect('https://example.com/offer');
});

it('stores User-Agent from request header', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => 'https://example.com/offer',
    ]);
    $campaign = Campaign::factory()->for($offer)->active()->create();
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    get(route('tracking.redirect', $trackingLink->code), [
        'HTTP_USER_AGENT' => 'TestBot/1.0',
    ]);

    assertDatabaseHas('tracking_clicks', [
        'tracking_link_id' => $trackingLink->id,
        'user_agent' => 'TestBot/1.0',
    ]);
});

it('stores Referer from request header', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => 'https://example.com/offer',
    ]);
    $campaign = Campaign::factory()->for($offer)->active()->create();
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    get(route('tracking.redirect', $trackingLink->code), [
        'HTTP_REFERER' => 'https://referrer.com/page',
    ]);

    assertDatabaseHas('tracking_clicks', [
        'tracking_link_id' => $trackingLink->id,
        'referer' => 'https://referrer.com/page',
    ]);
});

it('stores UTM values from query string', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => 'https://example.com/offer',
    ]);
    $campaign = Campaign::factory()->for($offer)->active()->create();
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    get(route('tracking.redirect', $trackingLink->code).'?utm_source=facebook&utm_medium=cpc&utm_campaign=spring&utm_term=shoes&utm_content=banner');

    assertDatabaseHas('tracking_clicks', [
        'tracking_link_id' => $trackingLink->id,
        'utm_source' => 'facebook',
        'utm_medium' => 'cpc',
        'utm_campaign' => 'spring',
        'utm_term' => 'shoes',
        'utm_content' => 'banner',
    ]);
});

it('stores empty metadata as null', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => 'https://example.com/offer',
    ]);
    $campaign = Campaign::factory()->for($offer)->active()->create();
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    get(route('tracking.redirect', $trackingLink->code), [
        'HTTP_USER_AGENT' => '',
        'HTTP_REFERER' => '',
    ]);

    $click = TrackingClick::where('tracking_link_id', $trackingLink->id)->first();
    expect($click->user_agent)->toBeNull();
    expect($click->referer)->toBeNull();
});

it('truncates oversized User-Agent to 512 characters', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => 'https://example.com/offer',
    ]);
    $campaign = Campaign::factory()->for($offer)->active()->create();
    $trackingLink = TrackingLink::factory()->for($campaign)->create();
    $longAgent = str_repeat('A', 600);

    get(route('tracking.redirect', $trackingLink->code), [
        'HTTP_USER_AGENT' => $longAgent,
    ]);

    $click = TrackingClick::where('tracking_link_id', $trackingLink->id)->first();
    expect($click->user_agent)->toHaveLength(512);
});

it('never stores raw IP address', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => 'https://example.com/offer',
    ]);
    $campaign = Campaign::factory()->for($offer)->active()->create();
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    get(route('tracking.redirect', $trackingLink->code));

    $click = TrackingClick::where('tracking_link_id', $trackingLink->id)->first();
    expect($click->ip_hash)->not->toContain('127.0.0.1');
});

it('records a valid IP hash', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => 'https://example.com/offer',
    ]);
    $campaign = Campaign::factory()->for($offer)->active()->create();
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    get(route('tracking.redirect', $trackingLink->code));

    $click = TrackingClick::where('tracking_link_id', $trackingLink->id)->first();
    expect($click->ip_hash)->toBeString()->toHaveLength(64);
});

it('returns 404 for javascript: destination', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => 'javascript:alert(1)',
    ]);
    $campaign = Campaign::factory()->for($offer)->active()->create();
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $response = get(route('tracking.redirect', $trackingLink->code));

    $response->assertNotFound();
    assertDatabaseCount('tracking_clicks', 0);
});

it('returns 404 for data: destination', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => 'data:text/html,<script>alert(1)</script>',
    ]);
    $campaign = Campaign::factory()->for($offer)->active()->create();
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $response = get(route('tracking.redirect', $trackingLink->code));

    $response->assertNotFound();
    assertDatabaseCount('tracking_clicks', 0);
});

it('returns 404 for file: destination', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => 'file:///etc/passwd',
    ]);
    $campaign = Campaign::factory()->for($offer)->active()->create();
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $response = get(route('tracking.redirect', $trackingLink->code));

    $response->assertNotFound();
    assertDatabaseCount('tracking_clicks', 0);
});

it('returns 404 for relative destination', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => '/admin',
    ]);
    $campaign = Campaign::factory()->for($offer)->active()->create();
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $response = get(route('tracking.redirect', $trackingLink->code));

    $response->assertNotFound();
    assertDatabaseCount('tracking_clicks', 0);
});

it('returns 404 for malformed destination', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => '://invalid',
    ]);
    $campaign = Campaign::factory()->for($offer)->active()->create();
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $response = get(route('tracking.redirect', $trackingLink->code));

    $response->assertNotFound();
    assertDatabaseCount('tracking_clicks', 0);
});

it('returns 404 for hostless destination', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => 'http://',
    ]);
    $campaign = Campaign::factory()->for($offer)->active()->create();
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $response = get(route('tracking.redirect', $trackingLink->code));

    $response->assertNotFound();
    assertDatabaseCount('tracking_clicks', 0);
});

it('returns 404 for empty destination', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => '',
    ]);
    $campaign = Campaign::factory()->for($offer)->active()->create();
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $response = get(route('tracking.redirect', $trackingLink->code));

    $response->assertNotFound();
    assertDatabaseCount('tracking_clicks', 0);
});

it('unsafe destinations create no click', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => 'javascript:alert(1)',
    ]);
    $campaign = Campaign::factory()->for($offer)->active()->create();
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    get(route('tracking.redirect', $trackingLink->code));

    assertDatabaseCount('tracking_clicks', 0);
});

it('TrackingLink::clicks() returns related clicks', function (): void {
    $trackingLink = TrackingLink::factory()->create();
    TrackingClick::factory()->count(3)->for($trackingLink)->create();

    expect($trackingLink->clicks()->count())->toBe(3);
});

it('TrackingClick::trackingLink() returns the parent', function (): void {
    $trackingLink = TrackingLink::factory()->create();
    $click = TrackingClick::factory()->for($trackingLink)->create();

    expect($click->trackingLink->id)->toBe($trackingLink->id);
});

it('deleting TrackingLink deletes related clicks', function (): void {
    $trackingLink = TrackingLink::factory()->create();
    TrackingClick::factory()->count(3)->for($trackingLink)->create();

    assertDatabaseCount('tracking_clicks', 3);

    $trackingLink->delete();

    assertDatabaseCount('tracking_clicks', 0);
});

it('loads relationships using bounded eager-loading', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => 'https://example.com/offer',
    ]);
    $campaign = Campaign::factory()->for($offer)->active()->create();
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $loaded = TrackingLink::with('campaign.offer')
        ->where('code', $trackingLink->code)
        ->first();

    expect($loaded->campaign)->not->toBeNull();
    expect($loaded->campaign->offer)->not->toBeNull();
    expect($loaded->campaign->offer->destination_url)->toBe('https://example.com/offer');
});

it('returns 404 when Campaign relation is null', function (): void {
    $trackingLink = TrackingLink::factory()->create();
    $code = $trackingLink->code;

    $trackingLink->update(['campaign_id' => null]);

    $response = get('/t/'.$code);

    $response->assertNotFound();
});

it('reports click persistence exception but still redirects', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create([
        'destination_url' => 'https://example.com/offer',
    ]);
    $campaign = Campaign::factory()->for($offer)->active()->create();
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $mockHasher = Mockery::mock(IpHasher::class);
    $mockHasher->shouldReceive('hash')
        ->once()
        ->andThrow(new RuntimeException('Database connection lost'));

    $this->app->instance(IpHasher::class, $mockHasher);

    $response = get(route('tracking.redirect', $trackingLink->code));

    $response->assertStatus(302);
    $response->assertRedirect('https://example.com/offer');
});
