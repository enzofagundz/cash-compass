<?php

use App\Models\User;
use Filament\Auth\Pages\Login;
use Livewire\Livewire;

it('renders the login screen', function () {
    $this->get(route('filament.app.auth.login'))->assertOk();
});

it('authenticates users through the panel login', function () {
    $user = User::factory()->create();

    Livewire::test(Login::class)
        ->set('data.email', $user->email)
        ->set('data.password', 'password')
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user);
});

it('redirects regular users to the thermometer home after login', function () {
    $user = User::factory()->create();

    Livewire::test(Login::class)
        ->set('data.email', $user->email)
        ->set('data.password', 'password')
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($user);

    $this->get('/')->assertRedirect('/thermometer');
});

it('redirects admins to user management after login', function () {
    $user = User::factory()->admin()->create();

    Livewire::test(Login::class)
        ->set('data.email', $user->email)
        ->set('data.password', 'password')
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($user);

    $this->get('/')->assertRedirect('/users');
});

it('returns to the requested thermometer link with filters after login', function () {
    $this->travelTo('2026-09-15');

    $this->get('/thermometer?year=2027&month=3&months=3')->assertRedirect('/login');

    $user = User::factory()->create();

    Livewire::test(Login::class)
        ->set('data.email', $user->email)
        ->set('data.password', 'password')
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirectContains('/thermometer')
        ->assertRedirectContains('year=2027')
        ->assertRedirectContains('month=3')
        ->assertRedirectContains('months=3');

    $this->assertAuthenticatedAs($user);

    $this->get('/thermometer?year=2027&month=3&months=3')
        ->assertOk()
        ->assertSee('mar/2027 – mai/2027');
});

it('still forbids the requested page without permission after login', function () {
    $this->get('/users')->assertRedirect('/login');

    $user = User::factory()->create();

    Livewire::test(Login::class)
        ->set('data.email', $user->email)
        ->set('data.password', 'password')
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect('/users');

    $this->assertAuthenticatedAs($user);

    $this->get('/users')->assertForbidden();
});

it('does not authenticate users with an invalid password', function () {
    $user = User::factory()->create();

    Livewire::test(Login::class)
        ->set('data.email', $user->email)
        ->set('data.password', 'wrong-password')
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
});

it('does not authenticate deactivated users', function () {
    $user = User::factory()->inactive()->create();

    Livewire::test(Login::class)
        ->set('data.email', $user->email)
        ->set('data.password', 'password')
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
});

it('logs authenticated users out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('filament.app.auth.logout'))
        ->assertRedirect(route('filament.app.auth.login'));

    $this->assertGuest();
});
