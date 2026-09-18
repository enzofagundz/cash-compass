<?php

use App\Enums\ThermometerColumn;

it('labels every thermometer column', function () {
    expect(ThermometerColumn::Income->label())->toBe('Entrada')
        ->and(ThermometerColumn::Expense->label())->toBe('Saída')
        ->and(ThermometerColumn::Forecast->label())->toBe('Previsão diária')
        ->and(ThermometerColumn::Savings->label())->toBe('Economia')
        ->and(ThermometerColumn::Card->label())->toBe('Cartão');
});

it('exposes the plural column labels', function () {
    expect(ThermometerColumn::Income->columnLabel())->toBe('Entradas')
        ->and(ThermometerColumn::Expense->columnLabel())->toBe('Saídas')
        ->and(ThermometerColumn::Forecast->columnLabel())->toBe('Previsão diária')
        ->and(ThermometerColumn::Savings->columnLabel())->toBe('Economias')
        ->and(ThermometerColumn::Card->columnLabel())->toBe('Cartão');
});

it('flags only the forecast column as read only', function () {
    expect(ThermometerColumn::Forecast->isForecast())->toBeTrue()
        ->and(ThermometerColumn::Income->isForecast())->toBeFalse()
        ->and(ThermometerColumn::Expense->isForecast())->toBeFalse()
        ->and(ThermometerColumn::Savings->isForecast())->toBeFalse()
        ->and(ThermometerColumn::Card->isForecast())->toBeFalse();
});

it('keeps transaction types and the forecast column equally valued', function () {
    expect(ThermometerColumn::Income->value)->toBe('income')
        ->and(ThermometerColumn::Expense->value)->toBe('expense')
        ->and(ThermometerColumn::Forecast->value)->toBe('forecast')
        ->and(ThermometerColumn::Savings->value)->toBe('savings')
        ->and(ThermometerColumn::Card->value)->toBe('card');
});

it('maps the badge letters and symbols of every column', function () {
    expect(ThermometerColumn::Income->badgeSymbol())->toBe('tmb-icon-arrow-in')
        ->and(ThermometerColumn::Income->badgeLetter())->toBeNull()
        ->and(ThermometerColumn::Expense->badgeSymbol())->toBe('tmb-icon-arrow-out')
        ->and(ThermometerColumn::Expense->badgeLetter())->toBeNull()
        ->and(ThermometerColumn::Forecast->badgeSymbol())->toBeNull()
        ->and(ThermometerColumn::Forecast->badgeLetter())->toBe('P')
        ->and(ThermometerColumn::Savings->badgeSymbol())->toBeNull()
        ->and(ThermometerColumn::Savings->badgeLetter())->toBe('E')
        ->and(ThermometerColumn::Card->badgeSymbol())->toBeNull()
        ->and(ThermometerColumn::Card->badgeLetter())->toBe('C');
});
