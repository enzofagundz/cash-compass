<?php

use App\Models\User;

it('redirects guests from the panel root to the login page', function () {
    $this->get('/')->assertRedirect('/login');
});

it('lets verified users reach the panel root', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/')->assertOk();
});

it('redirects unverified users to the email verification prompt', function () {
    $this->actingAs(User::factory()->unverified()->create());

    $this->get('/')->assertRedirect(route('filament.app.auth.email-verification.prompt'));
});

it('forbids deactivated users from the panel', function () {
    $this->actingAs(User::factory()->inactive()->create());

    $this->get('/')->assertForbidden();
});

it('redirects the legacy dashboard url to the panel root', function () {
    $this->get('/dashboard')->assertRedirect('/');
});

it('redirects the legacy settings url to the profile page', function () {
    $this->get('/settings')->assertRedirect('/profile');
});

it('redirects legacy admin urls to the panel root', function () {
    $this->get('/admin')->assertRedirect('/');
    $this->get('/admin/users')->assertRedirect('/users');
});
