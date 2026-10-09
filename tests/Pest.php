<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class)->in('Feature');

function useMcpAccount(User $user): void
{
    config()->set('cash_compass.mcp.account_email', $user->email);
}
