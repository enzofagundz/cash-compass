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
     * Fill the plan's pending transactions for the twelve months after a reference date.
     */
    public function generate(AccountPlan $plan, ?CarbonImmutable $asOf = null): int
    {
        if (! $plan->is_active) {
            return 0;
        }

        $asOf = ($asOf ?? CarbonImmutable::now())->startOfDay();
        $horizon = $asOf->addMonths(12);
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

        foreach ($dates as $date) {
            if ($date->lessThan($asOf)) {
                continue;
            }

            $plan->dailyTransactions()->updateOrCreate(
                ['date' => $date->toDateString()],
                [
                    'user_id' => $plan->user_id,
                    'type' => $plan->type,
                    'amount' => $plan->expected_amount,
                    'description' => $plan->description,
                    'is_recurring' => true,
                    'status' => TransactionStatus::Pending,
                ],
            );
        }

        return count($dates);
    }
}
