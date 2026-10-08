<?php

namespace App\Mcp\Concerns;

use App\Models\AccountPlan;
use App\Models\DailyForecast;
use App\Models\DailyTransaction;
use App\Models\DayCheckIn;
use App\Models\Tag;
use App\Models\UserInitialBalance;
use Carbon\CarbonImmutable;

trait SerializesDomainRecords
{
    /**
     * @return array<string, mixed>
     */
    protected function transactionRecord(DailyTransaction $transaction): array
    {
        return [
            'id' => (int) $transaction->getKey(),
            'date' => $transaction->date->toDateString(),
            'type' => $transaction->type->value,
            'type_label' => $transaction->type->label(),
            'amount' => $transaction->amount,
            'description' => $transaction->description,
            'account_plan_id' => $transaction->account_plan_id,
            'is_recurring' => (bool) $transaction->is_recurring,
            'status' => $transaction->status->value,
            'status_label' => $transaction->status->label(),
            'tags' => $transaction->tags
                ->map(fn (Tag $tag): array => $this->tagRecord($tag))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function accountPlanRecord(AccountPlan $plan): array
    {
        return [
            'id' => (int) $plan->getKey(),
            'type' => $plan->type->value,
            'type_label' => $plan->type->label(),
            'description' => $plan->description,
            'expected_amount' => $plan->expected_amount,
            'frequency' => $plan->frequency->value,
            'frequency_label' => $plan->frequency->label(),
            'interval' => (int) $plan->interval,
            'day_of_month' => $plan->day_of_month,
            'day_of_week' => $plan->day_of_week,
            'starts_at' => $this->dateString($plan->starts_at),
            'ends_at' => $this->dateString($plan->ends_at),
            'occurrences' => $plan->occurrences,
            'is_active' => (bool) $plan->is_active,
            'tags' => $plan->tags
                ->map(fn (Tag $tag): array => $this->tagRecord($tag))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function tagRecord(Tag $tag): array
    {
        $record = [
            'id' => (int) $tag->getKey(),
            'name' => $tag->name,
            'color' => $tag->color->value,
            'color_label' => $tag->color->label(),
            'is_active' => (bool) $tag->is_active,
        ];

        if (array_key_exists('daily_transactions_count', $tag->getAttributes())) {
            $record['daily_transactions_count'] = (int) $tag->daily_transactions_count;
        }

        if (array_key_exists('account_plans_count', $tag->getAttributes())) {
            $record['account_plans_count'] = (int) $tag->account_plans_count;
        }

        return $record;
    }

    /**
     * @return array<string, mixed>
     */
    protected function forecastRecord(DailyForecast $forecast): array
    {
        return [
            'id' => (int) $forecast->getKey(),
            'description' => $forecast->description,
            'amount' => $forecast->amount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function checkInRecord(DayCheckIn $checkIn): array
    {
        return [
            'id' => (int) $checkIn->getKey(),
            'date' => $checkIn->date->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function initialBalanceRecord(UserInitialBalance $balance): array
    {
        return [
            'id' => (int) $balance->getKey(),
            'amount' => $balance->amount,
            'base_date' => $this->dateString($balance->base_date),
            'starts_at' => $this->dateString($balance->base_date) ?? $balance->created_at?->toDateString(),
        ];
    }

    private function dateString(mixed $date): ?string
    {
        return $date instanceof CarbonImmutable || $date instanceof \DateTimeInterface
            ? $date->format('Y-m-d')
            : null;
    }
}
