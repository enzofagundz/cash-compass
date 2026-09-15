<?php

namespace App\Observers;

use App\Models\AccountPlan;
use App\Services\RecurrenceGenerator;
use Carbon\CarbonImmutable;

class AccountPlanObserver
{
    /**
     * Scheduling fields that invalidate already generated pending transactions.
     *
     * @var array<int, string>
     */
    private const SCHEDULING_FIELDS = [
        'type',
        'description',
        'expected_amount',
        'frequency',
        'interval',
        'day_of_month',
        'day_of_week',
        'starts_at',
        'ends_at',
        'occurrences',
        'is_active',
    ];

    public function __construct(
        private readonly RecurrenceGenerator $generator,
    ) {}

    public function created(AccountPlan $plan): void
    {
        $this->generator->generate($plan);
    }

    public function updated(AccountPlan $plan): void
    {
        if (! $plan->wasChanged(self::SCHEDULING_FIELDS)) {
            return;
        }

        $plan->cancelPendingRecurringTransactions(CarbonImmutable::now());

        $this->generator->generate($plan);
    }

    public function deleting(AccountPlan $plan): bool
    {
        if ($plan->hasRealizedTransactionsUpToToday()) {
            return false;
        }

        $plan->cancelPendingRecurringTransactions();

        return true;
    }
}
