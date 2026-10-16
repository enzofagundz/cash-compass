<?php

use App\Models\DailyTransaction;
use App\Models\DayCheckIn;
use App\Models\User;
use App\Models\UserInitialBalance;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

it('writes the current month thermometer image to the given output path', function () {
    $this->travelTo('2026-10-16');

    $account = User::factory()->create();
    useMcpAccount($account);

    config()->set('cash_compass.thermometer_image.cloudflare_account_id', 'acc-123');
    config()->set('cash_compass.thermometer_image.cloudflare_api_token', 'token-123');

    UserInitialBalance::factory()->for($account)->create([
        'amount' => 3000,
        'base_date' => '2026-10-01',
    ]);

    DailyTransaction::factory()->for($account)->create([
        'date' => '2026-10-05',
        'amount' => 150,
    ]);

    DayCheckIn::toggleFor($account, '2026-10-05');

    $png = "\x89PNG\r\n\x1a\nfake-png-bytes";

    Http::fake([
        'api.cloudflare.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
    ]);

    $output = sys_get_temp_dir().'/thermometer-command-'.uniqid().'.png';

    $this->artisan('thermometer:image', ['--output' => $output])->assertExitCode(0);

    expect(file_get_contents($output))->toBe($png);

    @unlink($output);

    Http::assertSent(function ($request) {
        $html = $request['html'] ?? '';

        return str_contains($request->url(), '/accounts/acc-123/browser-run/screenshot')
            && $request->hasHeader('Authorization', 'Bearer token-123')
            && ($request['viewport']['width'] ?? null) === 1200
            && ($request['viewport']['height'] ?? null) === 1000
            && str_contains($html, 'outubro de 2026')
            && str_contains($html, 'class="thermometer-row thermometer-row-today"')
            && str_contains($html, 'thermometer-day-check')
            && str_contains($html, 'data-balance-color="dark-green"')
            && str_contains($html, 'R$ 2.850,00')
            && str_contains($html, 'Saldo inicial de R$ 3.000,00');
    });
});

it('writes to the default storage path when no output is given', function () {
    $this->travelTo('2026-10-16');

    useMcpAccount(User::factory()->create());
    config()->set('cash_compass.thermometer_image.cloudflare_account_id', 'acc-123');
    config()->set('cash_compass.thermometer_image.cloudflare_api_token', 'token-123');

    Http::fake(['api.cloudflare.com/*' => Http::response("\x89PNG fake", 200)]);

    $this->artisan('thermometer:image')->assertExitCode(0);

    $path = storage_path('app/thermometer-image-2026-10.png');

    expect(File::exists($path))->toBeTrue();
    expect(File::get($path))->toBe("\x89PNG fake");

    File::delete($path);
});

it('fails with a clear message when the cloudflare credentials are missing', function () {
    $this->travelTo('2026-10-16');

    useMcpAccount(User::factory()->create());
    config()->set('cash_compass.thermometer_image.cloudflare_account_id', null);
    config()->set('cash_compass.thermometer_image.cloudflare_api_token', null);

    Http::fake();

    $this->artisan('thermometer:image')
        ->expectsOutputToContain('As credenciais da Cloudflare não estão configuradas.')
        ->assertExitCode(1);

    Http::assertNothingSent();
});

it('fails when cloudflare does not return a png', function () {
    $this->travelTo('2026-10-16');

    useMcpAccount(User::factory()->create());
    config()->set('cash_compass.thermometer_image.cloudflare_account_id', 'acc-123');
    config()->set('cash_compass.thermometer_image.cloudflare_api_token', 'token-123');

    Http::fake(['api.cloudflare.com/*' => Http::response('upstream error', 500)]);

    $output = sys_get_temp_dir().'/thermometer-command-'.uniqid().'.png';

    $this->artisan('thermometer:image', ['--output' => $output])
        ->expectsOutputToContain('A API da Cloudflare não retornou um PNG (HTTP 500).')
        ->assertExitCode(1);

    expect(File::exists($output))->toBeFalse();
});

it('fails with a clear message when the cloudflare api is unreachable', function () {
    $this->travelTo('2026-10-16');

    useMcpAccount(User::factory()->create());
    config()->set('cash_compass.thermometer_image.cloudflare_account_id', 'acc-123');
    config()->set('cash_compass.thermometer_image.cloudflare_api_token', 'token-123');

    Http::fake(['api.cloudflare.com/*' => Http::failedConnection('Connection refused')]);

    $output = sys_get_temp_dir().'/thermometer-command-'.uniqid().'.png';

    $this->artisan('thermometer:image', ['--output' => $output])
        ->expectsOutputToContain('Falha de rede ao chamar a API da Cloudflare: Connection refused')
        ->assertExitCode(1);

    expect(File::exists($output))->toBeFalse();
});

it('surfaces the cloudflare error detail when the api rejects the request', function () {
    $this->travelTo('2026-10-16');

    useMcpAccount(User::factory()->create());
    config()->set('cash_compass.thermometer_image.cloudflare_account_id', 'acc-123');
    config()->set('cash_compass.thermometer_image.cloudflare_api_token', 'token-123');

    Http::fake(['api.cloudflare.com/*' => Http::response(json_encode([
        'success' => false,
        'errors' => [
            ['code' => 7001, 'message' => 'Invalid input: expected number, received undefined'],
        ],
    ]), 400)]);

    $this->artisan('thermometer:image', ['--output' => sys_get_temp_dir().'/thermometer-'.uniqid().'.png'])
        ->expectsOutputToContain('Invalid input: expected number, received undefined')
        ->assertExitCode(1);
});
