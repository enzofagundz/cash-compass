<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_admin_user(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $this->assertSame(UserRole::Admin->value, $admin->role);
    }
}
