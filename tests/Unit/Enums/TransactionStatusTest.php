<?php

use App\Enums\TransactionStatus;

it('labels every transaction status', function () {
    expect(TransactionStatus::Pending->label())->toBe('Pendente')
        ->and(TransactionStatus::Realized->label())->toBe('Realizado')
        ->and(TransactionStatus::Skipped->label())->toBe('Pulado');
});
