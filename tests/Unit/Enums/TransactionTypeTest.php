<?php

use App\Enums\TransactionType;

it('labels every transaction type', function () {
    expect(TransactionType::Income->label())->toBe('Entrada')
        ->and(TransactionType::Expense->label())->toBe('Saída')
        ->and(TransactionType::Savings->label())->toBe('Economia')
        ->and(TransactionType::Card->label())->toBe('Cartão');
});

it('exposes the plural column labels', function () {
    expect(TransactionType::Income->columnLabel())->toBe('Entradas')
        ->and(TransactionType::Expense->columnLabel())->toBe('Saídas')
        ->and(TransactionType::Savings->columnLabel())->toBe('Economias')
        ->and(TransactionType::Card->columnLabel())->toBe('Cartão');
});

it('signs only income as positive', function () {
    expect(TransactionType::Income->sign())->toBe(1)
        ->and(TransactionType::Expense->sign())->toBe(-1)
        ->and(TransactionType::Savings->sign())->toBe(-1)
        ->and(TransactionType::Card->sign())->toBe(-1)
        ->and(TransactionType::Income->isIncome())->toBeTrue()
        ->and(TransactionType::Card->isIncome())->toBeFalse();
});

it('removes the legacy daily type', function () {
    expect(TransactionType::values())->toBe(['income', 'expense', 'savings', 'card'])
        ->and(TransactionType::tryFrom('daily'))->toBeNull();
});
