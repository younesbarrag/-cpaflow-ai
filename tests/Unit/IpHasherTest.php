<?php

use App\Services\TrackingLink\IpHasher;

beforeEach(function (): void {
    $this->hasher = new IpHasher('test-secret-key-for-unit-tests');
});

it('hashes a valid IPv4 address', function (): void {
    $hash = $this->hasher->hash('192.168.1.1');

    expect($hash)
        ->toBeString()
        ->toHaveLength(64);
});

it('hashes a valid IPv6 address', function (): void {
    $hash = $this->hasher->hash('2001:0db8:0000:0000:0000:0000:0000:0001');

    expect($hash)
        ->toBeString()
        ->toHaveLength(64);
});

it('produces the same hash for the same canonical IP', function (): void {
    $hash1 = $this->hasher->hash('10.0.0.1');
    $hash2 = $this->hasher->hash('10.0.0.1');

    expect($hash1)->toBe($hash2);
});

it('produces the same hash for equivalent IPv6 forms', function (): void {
    $hash1 = $this->hasher->hash('2001:db8::1');
    $hash2 = $this->hasher->hash('2001:0db8:0000:0000:0000:0000:0000:0001');

    expect($hash1)->toBe($hash2);
});

it('produces different hashes for different IPs', function (): void {
    $hash1 = $this->hasher->hash('10.0.0.1');
    $hash2 = $this->hasher->hash('10.0.0.2');

    expect($hash1)->not->toBe($hash2);
});

it('returns null for null input', function (): void {
    expect($this->hasher->hash(null))->toBeNull();
});

it('returns null for empty string', function (): void {
    expect($this->hasher->hash(''))->toBeNull();
});

it('returns null for invalid IP', function (): void {
    expect($this->hasher->hash('not-an-ip'))->toBeNull();
});

it('returns null for whitespace-only input', function (): void {
    expect($this->hasher->hash('   '))->toBeNull();
});

it('strips IPv6 zone identifier before hashing', function (): void {
    $hash1 = $this->hasher->hash('fe80::1%eth0');
    $hash2 = $this->hasher->hash('fe80::1%1');
    $hash3 = $this->hasher->hash('fe80::1');

    expect($hash1)->toBe($hash2);
    expect($hash2)->toBe($hash3);
});

it('trims whitespace from IP input', function (): void {
    $hash1 = $this->hasher->hash('  192.168.1.1  ');
    $hash2 = $this->hasher->hash('192.168.1.1');

    expect($hash1)->toBe($hash2);
});

it('never returns the raw IP', function (): void {
    $hash = $this->hasher->hash('192.168.1.1');

    expect($hash)->not->toContain('192.168.1.1');
});
