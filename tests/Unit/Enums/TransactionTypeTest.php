<?php

use App\Enums\TransactionType;

it('labels every transaction type', function () {
    expect(TransactionType::Income->label())->toBe('Entrada')
        ->and(TransactionType::Expense->label())->toBe('Saída')
        ->and(TransactionType::Daily->label())->toBe('Diário')
        ->and(TransactionType::Savings->label())->toBe('Economia')
        ->and(TransactionType::Card->label())->toBe('Cartão');
});

it('exposes the plural column labels', function () {
    expect(TransactionType::Income->columnLabel())->toBe('Entradas')
        ->and(TransactionType::Expense->columnLabel())->toBe('Saídas')
        ->and(TransactionType::Daily->columnLabel())->toBe('Diários')
        ->and(TransactionType::Savings->columnLabel())->toBe('Economias')
        ->and(TransactionType::Card->columnLabel())->toBe('Cartão');
});

it('signs only income as positive', function () {
    expect(TransactionType::Income->sign())->toBe(1)
        ->and(TransactionType::Expense->sign())->toBe(-1)
        ->and(TransactionType::Daily->sign())->toBe(-1)
        ->and(TransactionType::Savings->sign())->toBe(-1)
        ->and(TransactionType::Card->sign())->toBe(-1)
        ->and(TransactionType::Income->isIncome())->toBeTrue()
        ->and(TransactionType::Card->isIncome())->toBeFalse();
});

it('lists every type in column order', function () {
    expect(TransactionType::values())->toBe(['income', 'expense', 'daily', 'savings', 'card']);
});

it('exposes the column badges', function () {
    expect(TransactionType::Income->badgeSymbol())->toBe('tmb-icon-arrow-in')
        ->and(TransactionType::Income->badgeLetter())->toBeNull()
        ->and(TransactionType::Expense->badgeSymbol())->toBe('tmb-icon-arrow-out')
        ->and(TransactionType::Expense->badgeLetter())->toBeNull()
        ->and(TransactionType::Daily->badgeSymbol())->toBeNull()
        ->and(TransactionType::Daily->badgeLetter())->toBe('D')
        ->and(TransactionType::Savings->badgeSymbol())->toBeNull()
        ->and(TransactionType::Savings->badgeLetter())->toBe('E')
        ->and(TransactionType::Card->badgeSymbol())->toBeNull()
        ->and(TransactionType::Card->badgeLetter())->toBe('C');
});

it('lists only the types available to account plans', function () {
    expect(TransactionType::planCases())->toBe([
        TransactionType::Income,
        TransactionType::Expense,
        TransactionType::Savings,
        TransactionType::Card,
    ]);
});
