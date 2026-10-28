<?php

use App\Models\User;
use Filament\Facades\Filament;

it('redirects guests from the panel root to the login page', function () {
    $this->get('/')->assertRedirect('/login');
});

it('redirects regular users from the panel root to the dashboard', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/')->assertRedirect('/dashboard');
});

it('redirects admins from the panel root to user management', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/')->assertRedirect('/users');
});

it('redirects unverified users to the email verification prompt', function () {
    $this->actingAs(User::factory()->unverified()->create());

    $this->get('/')->assertRedirect(route('filament.app.auth.email-verification.prompt'));
});

it('forbids deactivated users from the panel', function () {
    $this->actingAs(User::factory()->inactive()->create());

    $this->get('/')->assertForbidden();
});

it('lists the dashboard first for regular users', function () {
    $this->actingAs(User::factory()->create());

    $urls = collect(Filament::getNavigation())
        ->flatMap(fn ($group) => $group->getItems())
        ->map(fn ($item) => $item->getUrl())
        ->values();

    expect($urls->first())->toBe(url('/dashboard'))
        ->and($urls)->not->toContain(url('/'));
});

it('uses user management as the admin entry without thermometer navigation', function () {
    $this->actingAs(User::factory()->admin()->create());

    $urls = collect(Filament::getNavigation())
        ->flatMap(fn ($group) => $group->getItems())
        ->map(fn ($item) => $item->getUrl())
        ->values();

    expect($urls->first())->toBe(url('/users'))
        ->and($urls)->not->toContain(url('/thermometer'));
});

it('serves the dashboard at the legacy dashboard url for authenticated users', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/dashboard')->assertOk();
});

it('redirects the legacy settings url to the profile page', function () {
    $this->get('/settings')->assertRedirect('/profile');
});

it('redirects legacy admin urls to the panel root', function () {
    $this->get('/admin')->assertRedirect('/');
    $this->get('/admin/users')->assertRedirect('/users');
});
