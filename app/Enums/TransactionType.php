<?php

namespace App\Enums;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Savings = 'savings';
    case Card = 'card';

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Entrada',
            self::Expense => 'Saída',
            self::Savings => 'Economia',
            self::Card => 'Cartão',
        };
    }

    public function columnLabel(): string
    {
        return match ($this) {
            self::Income => 'Entradas',
            self::Expense => 'Saídas',
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

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type): string => $type->value, self::cases());
    }
}
