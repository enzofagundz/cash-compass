<?php

use App\Models\User;

it('lets admins manage users', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    expect($admin->can('viewAny', User::class))->toBeTrue()
        ->and($admin->can('create', User::class))->toBeTrue()
        ->and($admin->can('update', $target))->toBeTrue()
        ->and($admin->can('delete', $target))->toBeTrue();
});

it('forbids admins from deleting their own account', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->can('delete', $admin))->toBeFalse();
});

it('forbids regular users from managing users', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();

    expect($user->can('viewAny', User::class))->toBeFalse()
        ->and($user->can('create', User::class))->toBeFalse()
        ->and($user->can('update', $target))->toBeFalse()
        ->and($user->can('delete', $target))->toBeFalse()
        ->and($user->can('deleteAny', User::class))->toBeFalse();
});
