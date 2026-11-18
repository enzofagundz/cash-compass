<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class)->in('Feature');

/*
 * Livewire and Filament pages leave reference cycles behind. Collecting them
 * after every test keeps the peak memory of the whole suite bounded instead of
 * growing with the number of rendered pages.
 */
pest()->afterEach(function (): void {
    gc_collect_cycles();
});

function useMcpAccount(User $user): void
{
    config()->set('cash_compass.mcp.account_email', $user->email);
}

/**
 * Smallest decodable PNG (1x1), used wherever a real image body is required.
 */
function tinyPng(): string
{
    return (string) base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        true,
    );
}
