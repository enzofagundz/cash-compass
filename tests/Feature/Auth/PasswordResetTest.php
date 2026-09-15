<?php

use App\Models\User;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

it('renders the password reset request screen', function () {
    $this->get(route('filament.app.auth.password-reset.request'))->assertOk();
});

it('sends a password reset link', function () {
    $user = User::factory()->create();

    Notification::fake();

    Livewire::test(RequestPasswordReset::class)
        ->set('data.email', $user->email)
        ->call('request')
        ->assertHasNoFormErrors();

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('resets the password with a valid token', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => $token])
        ->set('password', 'new-password')
        ->set('passwordConfirmation', 'new-password')
        ->call('resetPassword')
        ->assertHasNoFormErrors();

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});
