<?php

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('lets admins list users in the panel', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin/users')->assertOk();
});

it('forbids regular users from listing users in the panel', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/users')->assertForbidden();
});

it('lets admins create a user with the admin role in the panel', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageUsers::class)
        ->callAction('create', data: [
            'name' => 'New Admin',
            'email' => 'new-admin@example.com',
            'role' => UserRole::Admin->value,
            'password' => 'password',
        ])
        ->assertHasNoActionErrors();

    expect(User::where('email', 'new-admin@example.com')->firstOrFail()->role)->toBe(UserRole::Admin);
});

it('lets admins deactivate a user in the panel', function () {
    $this->actingAs(User::factory()->admin()->create());

    $user = User::factory()->create();

    Livewire::test(ManageUsers::class)
        ->callTableAction('toggle_active', $user)
        ->assertHasNoActionErrors();

    expect($user->refresh()->is_active)->toBeFalse();
});

it('lets admins send a password reset link in the panel', function () {
    $this->actingAs(User::factory()->admin()->create());

    $user = User::factory()->create();

    Notification::fake();

    Livewire::test(ManageUsers::class)
        ->callTableAction('send_reset_link', $user)
        ->assertHasNoActionErrors();

    Notification::assertSentTo($user, ResetPassword::class);
});
