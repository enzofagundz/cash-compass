<?php

use App\Models\User;
use Laravel\Fortify\Features;

it('renders the login screen', function () {
    $this->get(route('login'))->assertOk();
});

it('authenticates users using the login screen', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

it('does not authenticate users with an invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrorsIn('email');

    $this->assertGuest();
});

it('does not authenticate deactivated users', function () {
    $user = User::factory()->create();
    $user->is_active = false;
    $user->save();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrorsIn('email');

    $this->assertGuest();
});

it('redirects users with two factor enabled to the two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
});

it('logs users out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});
