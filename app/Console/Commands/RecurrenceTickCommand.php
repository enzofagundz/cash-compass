<?php

namespace App\Console\Commands;

use App\Enums\TransactionStatus;
use App\Models\AccountPlan;
use App\Models\DailyTransaction;
use App\Services\RecurrenceGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('recurrence:tick')]
#[Description('Completes the recurrence horizon and confirms transactions that are due.')]
class RecurrenceTickCommand extends Command
{
    public function handle(RecurrenceGenerator $generator): int
    {
        $today = CarbonImmutable::now()->startOfDay();

        AccountPlan::withoutGlobalScopes()
            ->where('is_active', true)
            ->each(fn (AccountPlan $plan) => $generator->generate($plan, $today));

        $confirmed = DailyTransaction::withoutGlobalScopes()
            ->where('status', TransactionStatus::Pending)
            ->where('date', '<=', $today->toDateString())
            ->update(['status' => TransactionStatus::Realized]);

        $this->info("Recurrence tick complete: {$confirmed} transaction(s) confirmed.");

        return self::SUCCESS;
    }
}
