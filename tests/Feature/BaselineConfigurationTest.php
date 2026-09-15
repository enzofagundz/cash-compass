<?php

it('uses the sao paulo timezone and brazilian locale', function () {
    expect(config('app.timezone'))->toBe('America/Sao_Paulo')
        ->and(config('app.locale'))->toBe('pt_BR')
        ->and(config('app.faker_locale'))->toBe('pt_BR');
});
