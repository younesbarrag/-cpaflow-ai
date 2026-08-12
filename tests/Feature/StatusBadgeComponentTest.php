<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;

uses(RefreshDatabase::class);

describe('StatusBadge component', function () {
    test('renders status text for completed', function () {
        $html = Blade::render('<x-status-badge :status="\'completed\'" />');

        expect($html)->toContain('Completed');
        expect($html)->toContain('bg-emerald-100');
        expect($html)->toContain('text-emerald-700');
    });

    test('renders status text for processing', function () {
        $html = Blade::render('<x-status-badge :status="\'processing\'" />');

        expect($html)->toContain('Processing');
        expect($html)->toContain('bg-brand-100');
        expect($html)->toContain('text-brand-700');
    });

    test('renders status text for failed', function () {
        $html = Blade::render('<x-status-badge :status="\'failed\'" />');

        expect($html)->toContain('Failed');
        expect($html)->toContain('bg-red-100');
        expect($html)->toContain('text-red-700');
    });

    test('preserves x-show attribute on rendered element', function () {
        $html = Blade::render('<x-status-badge :status="\'completed\'" x-show="gen.status === \'completed\'" />');

        expect($html)->toContain('x-show="gen.status === \'completed\'"');
    });

    test('preserves multiple arbitrary attributes', function () {
        $html = Blade::render('<x-status-badge :status="\'processing\'" x-show="gen.status === \'pending\'" x-cloak id="badge-1" />');

        expect($html)->toContain('x-show="gen.status === \'pending\'"');
        expect($html)->toContain('x-cloak');
        expect($html)->toContain('id="badge-1"');
    });

    test('preserves data attributes', function () {
        $html = Blade::render('<x-status-badge :status="\'active\'" data-testid="status-badge" />');

        expect($html)->toContain('data-testid="status-badge"');
    });

    test('preserves aria attributes', function () {
        $html = Blade::render('<x-status-badge :status="\'active\'" aria-label="Status" />');

        expect($html)->toContain('aria-label="Status"');
    });

    test('extra classes are merged not replaced', function () {
        $html = Blade::render('<x-status-badge :status="\'completed\'" class="my-extra" />');

        expect($html)->toContain('my-extra');
        expect($html)->toContain('inline-flex');
    });

    test('badge without extra attributes still renders correctly', function () {
        $html = Blade::render('<x-status-badge :status="\'draft\'" />');

        expect($html)->toContain('Draft');
        expect($html)->toContain('bg-gray-100');
        expect($html)->toContain('text-gray-700');
    });
});
