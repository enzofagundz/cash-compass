<?php

namespace App\Enums;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Daily = 'daily';
    case Savings = 'savings';
    case Card = 'card';

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Entrada',
            self::Expense => 'Saída',
            self::Daily => 'Diário',
            self::Savings => 'Economia',
            self::Card => 'Cartão',
        };
    }

    public function columnLabel(): string
    {
        return match ($this) {
            self::Income => 'Entradas',
            self::Expense => 'Saídas',
            self::Daily => 'Diários',
            self::Savings => 'Economias',
            self::Card => 'Cartão',
        };
    }

    public function sign(): int
    {
        return $this === self::Income ? 1 : -1;
    }

    public function isIncome(): bool
    {
        return $this === self::Income;
    }

    public function badgeLetter(): ?string
    {
        return match ($this) {
            self::Daily => 'D',
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

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type): string => $type->value, self::cases());
    }

    /**
     * Types available to account plans: daily spending is manual only.
     *
     * @return array<int, self>
     */
    public static function planCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $type): bool => $type !== self::Daily,
        ));
    }
}
