<?php

namespace App\Services;

use App\Models\DailyForecast;
use App\Models\User;

class DailyForecastCalculator
{
    public const DEFAULT_DIVISOR_DAYS = 30;

    public const MIN_DIVISOR_DAYS = 1;

    public const MAX_DIVISOR_DAYS = 31;

    public function divisorDays(User $user): int
    {
        $divisor = (int) $user->forecast_divisor_days;

        return $divisor >= self::MIN_DIVISOR_DAYS && $divisor <= self::MAX_DIVISOR_DAYS
            ? $divisor
            : self::DEFAULT_DIVISOR_DAYS;
    }

    public function monthlyTotal(User $user): string
    {
        return $this->format((float) DailyForecast::query()->forUser($user)->sum('amount'));
    }

    public function dailyAmount(User $user): string
    {
        return $this->dailyAmountFromTotal(
            (float) $this->monthlyTotal($user),
            $this->divisorDays($user),
        );
    }

    public function dailyAmountFromTotal(float $monthlyTotal, int $divisorDays): string
    {
        return $this->format(round($monthlyTotal / $divisorDays, 2));
    }

    private function format(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
