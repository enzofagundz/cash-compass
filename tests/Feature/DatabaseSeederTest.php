<?php

use App\Enums\UserRole;
use App\Models\User;

it('creates an admin user', function () {
    $this->seed();

    $admin = User::where('email', 'admin@example.com')->firstOrFail();

    expect($admin->role)->toBe(UserRole::Admin->value);
});
