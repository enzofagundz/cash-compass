<?php

use App\Models\User;

it('renders the confirm password screen', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('password.confirm'))->assertOk();
});
