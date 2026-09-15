<?php

use App\Enums\UserRole;
use App\Models\User;
use Filament\Auth\Pages\Register;
use Livewire\Livewire;

it('renders the registration screen', function () {
    $this->get(route('filament.app.auth.register'))->assertOk();
});

it('registers new users as regular users', function () {
    Livewire::test(Register::class)
        ->set('data.name', 'John Doe')
        ->set('data.email', 'john@example.com')
        ->set('data.password', 'password')
        ->set('data.passwordConfirmation', 'password')
        ->call('register')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'john@example.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::User);
    $this->assertAuthenticatedAs($user);
});

it('rejects a duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    Livewire::test(Register::class)
        ->set('data.name', 'John Doe')
        ->set('data.email', 'taken@example.com')
        ->set('data.password', 'password')
        ->set('data.passwordConfirmation', 'password')
        ->call('register')
        ->assertHasFormErrors(['email']);
});
