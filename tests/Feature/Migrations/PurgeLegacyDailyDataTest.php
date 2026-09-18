<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('purges legacy daily transactions, plans and pivots permanently', function () {
    $user = User::factory()->create();

    $tagId = DB::table('tags')->insertGetId([
        'user_id' => $user->id,
        'name' => 'Assinaturas',
        'color' => 'neutral',
        'is_active' => true,
        'normalized_name' => 'assinaturas',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $planId = DB::table('account_plans')->insertGetId([
        'user_id' => $user->id,
        'type' => 'daily',
        'description' => 'Diário',
        'expected_amount' => 50,
        'frequency' => 'daily',
        'interval' => 1,
        'starts_at' => '2026-09-01',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $dailyId = DB::table('daily_transactions')->insertGetId([
        'user_id' => $user->id,
        'date' => '2026-09-10',
        'type' => 'daily',
        'amount' => 50,
        'account_plan_id' => $planId,
        'is_recurring' => true,
        'status' => 'realized',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('daily_transaction_tag')->insert([
        'daily_transaction_id' => $dailyId,
        'tag_id' => $tagId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('account_plan_tag')->insert([
        'account_plan_id' => $planId,
        'tag_id' => $tagId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $keptPlanId = DB::table('account_plans')->insertGetId([
        'user_id' => $user->id,
        'type' => 'income',
        'description' => 'Salário',
        'expected_amount' => 5000,
        'frequency' => 'monthly',
        'interval' => 1,
        'day_of_month' => 5,
        'starts_at' => '2026-09-01',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $keptId = DB::table('daily_transactions')->insertGetId([
        'user_id' => $user->id,
        'date' => '2026-09-10',
        'type' => 'expense',
        'amount' => 20,
        'is_recurring' => false,
        'status' => 'realized',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $path = collect(glob(database_path('migrations/*_purge_legacy_daily_data.php')))->sole();

    $migration = require $path;
    $migration->up();

    expect(DB::table('daily_transactions')->where('type', 'daily')->count())->toBe(0)
        ->and(DB::table('account_plans')->where('type', 'daily')->count())->toBe(0)
        ->and(DB::table('daily_transaction_tag')->where('daily_transaction_id', $dailyId)->count())->toBe(0)
        ->and(DB::table('account_plan_tag')->where('account_plan_id', $planId)->count())->toBe(0)
        ->and(DB::table('daily_transactions')->where('id', $keptId)->exists())->toBeTrue()
        ->and(DB::table('account_plans')->where('id', $keptPlanId)->exists())->toBeTrue()
        ->and(DB::table('tags')->where('id', $tagId)->exists())->toBeTrue();
});
