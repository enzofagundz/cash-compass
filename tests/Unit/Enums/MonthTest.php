<?php

use App\Enums\Month;

it('labels every month', function () {
    expect(Month::January->label())->toBe('Janeiro')
        ->and(Month::February->label())->toBe('Fevereiro')
        ->and(Month::December->label())->toBe('Dezembro');
});

it('builds the month options keyed by number', function () {
    $options = Month::options();

    expect($options)->toHaveCount(12)
        ->and($options[1])->toBe('Janeiro')
        ->and($options[12])->toBe('Dezembro');
});
