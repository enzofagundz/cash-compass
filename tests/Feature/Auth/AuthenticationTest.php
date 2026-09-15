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
