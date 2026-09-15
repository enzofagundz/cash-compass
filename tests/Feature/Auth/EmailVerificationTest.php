<?php

use App\Models\User;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Auth\Pages\EmailVerification\EmailVerificationPrompt;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

it('redirects verified users away from the verification prompt', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('filament.app.auth.email-verification.prompt'))->assertRedirect('/');
});

it('shows the verification prompt to unverified users', function () {
    $this->actingAs(User::factory()->unverified()->create());

    $this->get(route('filament.app.auth.email-verification.prompt'))->assertOk();
});

it('resends the verification notification', function () {
    $user = User::factory()->unverified()->create();
    $this->actingAs($user);

    Notification::fake();

    Livewire::test(EmailVerificationPrompt::class)
        ->callAction('resendNotification');

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('verifies the email from a signed link', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('filament.app.auth.email-verification.verify', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    $this->actingAs($user)->get($url);

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
});
