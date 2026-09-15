<?php

use App\Models\User;
use Filament\Auth\Pages\EditProfile;
use Livewire\Livewire;

it('lets users view their profile', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/profile')->assertOk();
});

it('lets users update their own name and email', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.name', 'Novo Nome')
        ->set('data.email', 'novo@example.com')
        ->set('data.currentPassword', 'password')
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect($user->name)->toBe('Novo Nome');
});
