<?php

namespace App\Enums;

enum ThermometerColumn: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Forecast = 'forecast';
    case Savings = 'savings';
    case Card = 'card';

    public function label(): string
    {
        return $this === self::Forecast
            ? 'Previsão diária'
            : TransactionType::from($this->value)->label();
    }

    public function columnLabel(): string
    {
        return $this === self::Forecast
            ? 'Previsão diária'
            : TransactionType::from($this->value)->columnLabel();
    }

    public function badgeLetter(): ?string
    {
        return match ($this) {
            self::Forecast => 'P',
            self::Savings => 'E',
            self::Card => 'C',
            default => null,
        };
    }

    public function badgeSymbol(): ?string
    {
        return match ($this) {
            self::Income => 'tmb-icon-arrow-in',
            self::Expense => 'tmb-icon-arrow-out',
            default => null,
        };
    }

    public function isForecast(): bool
    {
        return $this === self::Forecast;
    }
}
