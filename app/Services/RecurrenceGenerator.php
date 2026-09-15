<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Models\AccountPlan;
use Carbon\CarbonImmutable;

class RecurrenceGenerator
{
    public function __construct(
        private readonly RecurrenceCalculator $calculator,
    ) {}

    /**
     * Create the missing pending transactions for the plan's horizon.
     *
     * Existing transactions are left untouched so that per-occurrence edits
     * survive a later run of the daily tick.
     *
     * @return int Number of transactions created.
     */
    public function generate(AccountPlan $plan, ?CarbonImmutable $asOf = null): int
    {
        if (! $plan->is_active) {
            return 0;
        }

        $asOf = ($asOf ?? CarbonImmutable::now())->startOfDay();
        $horizon = $asOf->addMonths(RecurrenceCalculator::DEFAULT_HORIZON_MONTHS);
        $limit = $plan->ends_at === null
            ? $horizon
            : $horizon->min(CarbonImmutable::instance($plan->ends_at));

        $dates = $this->calculator->occurrences(
            frequency: $plan->frequency,
            startsAt: CarbonImmutable::instance($plan->starts_at)->startOfDay(),
            until: $limit,
            interval: $plan->interval,
            dayOfMonth: $plan->day_of_month,
            dayOfWeek: $plan->day_of_week,
            occurrences: $plan->occurrences,
        );

        $created = 0;

        foreach ($dates as $date) {
            if ($date->lessThan($asOf)) {
                continue;
            }

            $transaction = $plan->dailyTransactions()->firstOrNew(['date' => $date->toDateString()]);

            if ($transaction->exists) {
                continue;
            }

            $transaction->user_id = $plan->user_id;
            $transaction->type = $plan->type;
            $transaction->amount = $plan->expected_amount;
            $transaction->description = $plan->description;
            $transaction->is_recurring = true;
            $transaction->status = TransactionStatus::Pending;
            $transaction->save();

            $created++;
        }

        return $created;
    }
}
