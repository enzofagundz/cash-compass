<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_users_in_panel(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin/users')->assertOk();
    }

    public function test_regular_users_are_forbidden_from_listing_users_in_panel(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/users')->assertForbidden();
    }

    public function test_admin_can_create_user_with_admin_role_in_panel(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageUsers::class)
            ->callAction('create', data: [
                'name' => 'New Admin',
                'email' => 'new-admin@example.com',
                'role' => UserRole::Admin->value,
                'password' => 'password',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(UserRole::Admin->value, User::where('email', 'new-admin@example.com')->firstOrFail()->role);
    }

    public function test_admin_can_deactivate_user_in_panel(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $user = User::factory()->create();

        Livewire::test(ManageUsers::class)
            ->callTableAction('toggle_active', $user)
            ->assertHasNoActionErrors();

        $this->assertFalse($user->refresh()->is_active);
    }

    public function test_admin_can_send_password_reset_link_in_panel(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $user = User::factory()->create();

        Notification::fake();

        Livewire::test(ManageUsers::class)
            ->callTableAction('send_reset_link', $user)
            ->assertHasNoActionErrors();

        Notification::assertSentTo($user, ResetPassword::class);
    }
}
