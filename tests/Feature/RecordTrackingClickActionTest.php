<?php

use App\Actions\TrackingLink\RecordTrackingClickAction;
use App\Models\TrackingClick;
use App\Models\TrackingLink;
use App\Services\TrackingLink\IpHasher;

beforeEach(function (): void {
    $this->ipHasher = new IpHasher;
    $this->action = new RecordTrackingClickAction($this->ipHasher);
});

it('creates a tracking click with all metadata', function (): void {
    $trackingLink = TrackingLink::factory()->create();

    $click = $this->action->execute(
        $trackingLink,
        '192.168.1.1',
        'Mozilla/5.0',
        'https://example.com/page',
        'facebook',
        'cpc',
        'spring',
        'shoes',
        'banner',
    );

    expect($click)->toBeInstanceOf(TrackingClick::class);
    expect($click->tracking_link_id)->toBe($trackingLink->id);
    expect($click->ip_hash)->toBeString()->toHaveLength(64);
    expect($click->user_agent)->toBe('Mozilla/5.0');
    expect($click->referer)->toBe('https://example.com/page');
    expect($click->utm_source)->toBe('facebook');
    expect($click->utm_medium)->toBe('cpc');
    expect($click->utm_campaign)->toBe('spring');
    expect($click->utm_term)->toBe('shoes');
    expect($click->utm_content)->toBe('banner');
});

it('sets created_at as the click timestamp', function (): void {
    $trackingLink = TrackingLink::factory()->create();

    $click = $this->action->execute(
        $trackingLink,
        '192.168.1.1',
        null,
        null,
        null,
        null,
        null,
        null,
        null,
    );

    expect($click->created_at)->not->toBeNull();
    expect($click->created_at->diffInSeconds(now()))->toBeLessThan(5);
});

it('trims and stores User-Agent', function (): void {
    $trackingLink = TrackingLink::factory()->create();

    $click = $this->action->execute(
        $trackingLink,
        null,
        '  Mozilla/5.0  ',
        null,
        null,
        null,
        null,
        null,
        null,
    );

    expect($click->user_agent)->toBe('Mozilla/5.0');
});

it('trims and stores Referer', function (): void {
    $trackingLink = TrackingLink::factory()->create();

    $click = $this->action->execute(
        $trackingLink,
        null,
        null,
        '  https://example.com/page  ',
        null,
        null,
        null,
        null,
        null,
    );

    expect($click->referer)->toBe('https://example.com/page');
});

it('trims and stores UTM values', function (): void {
    $trackingLink = TrackingLink::factory()->create();

    $click = $this->action->execute(
        $trackingLink,
        null,
        null,
        null,
        '  facebook  ',
        '  cpc  ',
        '  spring  ',
        '  shoes  ',
        '  banner  ',
    );

    expect($click->utm_source)->toBe('facebook');
    expect($click->utm_medium)->toBe('cpc');
    expect($click->utm_campaign)->toBe('spring');
    expect($click->utm_term)->toBe('shoes');
    expect($click->utm_content)->toBe('banner');
});

it('converts empty metadata to null', function (): void {
    $trackingLink = TrackingLink::factory()->create();

    $click = $this->action->execute(
        $trackingLink,
        null,
        '',
        '',
        '',
        '',
        '',
        '',
        '',
    );

    expect($click->user_agent)->toBeNull();
    expect($click->referer)->toBeNull();
    expect($click->utm_source)->toBeNull();
    expect($click->utm_medium)->toBeNull();
    expect($click->utm_campaign)->toBeNull();
    expect($click->utm_term)->toBeNull();
    expect($click->utm_content)->toBeNull();
});

it('converts whitespace-only metadata to null', function (): void {
    $trackingLink = TrackingLink::factory()->create();

    $click = $this->action->execute(
        $trackingLink,
        null,
        '   ',
        '   ',
        null,
        null,
        null,
        null,
        null,
    );

    expect($click->user_agent)->toBeNull();
    expect($click->referer)->toBeNull();
});

it('truncates oversized User-Agent to 512 characters', function (): void {
    $trackingLink = TrackingLink::factory()->create();
    $longAgent = str_repeat('A', 600);

    $click = $this->action->execute(
        $trackingLink,
        null,
        $longAgent,
        null,
        null,
        null,
        null,
        null,
        null,
    );

    expect($click->user_agent)->toHaveLength(512);
    expect($click->user_agent)->toBe(str_repeat('A', 512));
});

it('truncates oversized Referer to 2048 characters', function (): void {
    $trackingLink = TrackingLink::factory()->create();
    $longReferer = str_repeat('B', 2500);

    $click = $this->action->execute(
        $trackingLink,
        null,
        null,
        $longReferer,
        null,
        null,
        null,
        null,
        null,
    );

    expect($click->referer)->toHaveLength(2048);
});

it('truncates oversized UTM fields to 255 characters', function (): void {
    $trackingLink = TrackingLink::factory()->create();
    $longUtm = str_repeat('C', 300);

    $click = $this->action->execute(
        $trackingLink,
        null,
        null,
        null,
        $longUtm,
        null,
        null,
        null,
        null,
    );

    expect($click->utm_source)->toHaveLength(255);
});

it('does not append ellipsis to truncated values', function (): void {
    $trackingLink = TrackingLink::factory()->create();
    $longAgent = str_repeat('A', 600);

    $click = $this->action->execute(
        $trackingLink,
        null,
        $longAgent,
        null,
        null,
        null,
        null,
        null,
        null,
    );

    expect($click->user_agent)->not->toContain('...');
});

it('hashes IP using IpHasher', function (): void {
    $trackingLink = TrackingLink::factory()->create();

    $click = $this->action->execute(
        $trackingLink,
        '10.0.0.1',
        null,
        null,
        null,
        null,
        null,
        null,
        null,
    );

    $expectedHash = $this->ipHasher->hash('10.0.0.1');
    expect($click->ip_hash)->toBe($expectedHash);
});

it('sets ip_hash to null when IP is missing', function (): void {
    $trackingLink = TrackingLink::factory()->create();

    $click = $this->action->execute(
        $trackingLink,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
    );

    expect($click->ip_hash)->toBeNull();
});

it('sets ip_hash to null when IP is invalid', function (): void {
    $trackingLink = TrackingLink::factory()->create();

    $click = $this->action->execute(
        $trackingLink,
        'not-an-ip',
        null,
        null,
        null,
        null,
        null,
        null,
        null,
    );

    expect($click->ip_hash)->toBeNull();
});

it('never stores raw IP address', function (): void {
    $trackingLink = TrackingLink::factory()->create();

    $click = $this->action->execute(
        $trackingLink,
        '192.168.1.1',
        null,
        null,
        null,
        null,
        null,
        null,
        null,
    );

    $attributes = $click->getAttributes();
    foreach ($attributes as $value) {
        if (is_string($value)) {
            expect($value)->not->toContain('192.168.1.1');
        }
    }
});

it('creates the click through the tracking link relationship', function (): void {
    $trackingLink = TrackingLink::factory()->create();

    $click = $this->action->execute(
        $trackingLink,
        '10.0.0.1',
        null,
        null,
        null,
        null,
        null,
        null,
        null,
    );

    expect($trackingLink->clicks()->count())->toBe(1);
    expect($trackingLink->clicks()->first()->id)->toBe($click->id);
});
