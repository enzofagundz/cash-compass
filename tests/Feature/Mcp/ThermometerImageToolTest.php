<?php

use App\Mcp\Servers\CashCompassServer;
use App\Mcp\Tools\GetThermometerImageTool;
use App\Models\DailyTransaction;
use App\Models\User;
use App\Models\UserInitialBalance;
use Illuminate\Support\Facades\Http;

it('returns the thermometer image and a text summary for the fixed account', function () {
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

    $png = tinyPng();

    Http::fake(['api.cloudflare.com/*' => Http::response($png, 200)]);

    CashCompassServer::tool(GetThermometerImageTool::class, [])
        ->assertOk()
        ->assertSee(base64_encode($png))
        ->assertSee('Termômetro de outubro de 2026')
        ->assertSee('Saldo de hoje (16/10/2026): R$ 2.850,00')
        ->assertSee('Totais do mês');

    Http::assertSent(function ($request) {
        $html = $request['html'] ?? '';

        return str_contains($html, 'outubro de 2026')
            && str_contains($html, 'class="thermometer-row thermometer-row-today"')
            && str_contains($html, 'R$ 2.850,00');
    });
});

it('degrades to the text summary with a warning when the image cannot be generated', function () {
    $this->travelTo('2026-10-16');

    $account = User::factory()->create();
    useMcpAccount($account);

    config()->set('cash_compass.thermometer_image.cloudflare_account_id', 'acc-123');
    config()->set('cash_compass.thermometer_image.cloudflare_api_token', 'token-123');

    UserInitialBalance::factory()->for($account)->create([
        'amount' => 3000,
        'base_date' => '2026-10-01',
    ]);

    Http::fake(['api.cloudflare.com/*' => Http::response('upstream error', 500)]);

    CashCompassServer::tool(GetThermometerImageTool::class, [])
        ->assertOk()
        ->assertSee('Não foi possível gerar a imagem do termômetro')
        ->assertSee('Saldo de hoje (16/10/2026): R$ 3.000,00');
});

it('degrades with a clear warning when the cloudflare credentials are missing', function () {
    $this->travelTo('2026-10-16');

    $account = User::factory()->create();
    useMcpAccount($account);

    config()->set('cash_compass.thermometer_image.cloudflare_account_id', null);
    config()->set('cash_compass.thermometer_image.cloudflare_api_token', null);

    Http::fake();

    CashCompassServer::tool(GetThermometerImageTool::class, [])
        ->assertOk()
        ->assertSee('Não foi possível gerar a imagem do termômetro')
        ->assertSee('As credenciais da Cloudflare não estão configuradas.');

    Http::assertNothingSent();
});

it('degrades to the text summary when the cloudflare api is unreachable', function () {
    $this->travelTo('2026-10-16');

    $account = User::factory()->create();
    useMcpAccount($account);

    config()->set('cash_compass.thermometer_image.cloudflare_account_id', 'acc-123');
    config()->set('cash_compass.thermometer_image.cloudflare_api_token', 'token-123');

    UserInitialBalance::factory()->for($account)->create([
        'amount' => 3000,
        'base_date' => '2026-10-01',
    ]);

    Http::fake(['api.cloudflare.com/*' => Http::failedConnection('Connection refused')]);

    CashCompassServer::tool(GetThermometerImageTool::class, [])
        ->assertOk()
        ->assertSee('Não foi possível gerar a imagem do termômetro')
        ->assertSee('Falha de rede ao chamar a API da Cloudflare: Connection refused')
        ->assertSee('Saldo de hoje (16/10/2026): R$ 3.000,00');
});

it('degrades to the text summary when the cloudflare body is not a valid png', function () {
    $this->travelTo('2026-10-16');

    $account = User::factory()->create();
    useMcpAccount($account);

    config()->set('cash_compass.thermometer_image.cloudflare_account_id', 'acc-123');
    config()->set('cash_compass.thermometer_image.cloudflare_api_token', 'token-123');

    Http::fake(['api.cloudflare.com/*' => Http::response("\x89PNG\r\n\x1a\ntruncated", 200)]);

    CashCompassServer::tool(GetThermometerImageTool::class, [])
        ->assertOk()
        ->assertSee('Não foi possível gerar a imagem do termômetro')
        ->assertSee('não retornou uma imagem PNG válida (HTTP 200).');
});

it('never exposes another account data and never mutates anything', function () {
    $this->travelTo('2026-10-16');

    $account = User::factory()->create();
    $other = User::factory()->create();
    useMcpAccount($account);

    config()->set('cash_compass.thermometer_image.cloudflare_account_id', 'acc-123');
    config()->set('cash_compass.thermometer_image.cloudflare_api_token', 'token-123');

    UserInitialBalance::factory()->for($account)->create([
        'amount' => 3000,
        'base_date' => '2026-10-01',
    ]);

    UserInitialBalance::factory()->for($other)->create([
        'amount' => 999999,
        'base_date' => '2026-10-01',
    ]);

    DailyTransaction::factory()->for($other)->create([
        'date' => '2026-10-06',
        'amount' => 12345,
    ]);

    Http::fake(['api.cloudflare.com/*' => Http::response(tinyPng(), 200)]);

    $before = DailyTransaction::withoutGlobalScopes()->count();

    CashCompassServer::tool(GetThermometerImageTool::class, [])
        ->assertOk()
        ->assertSee('Saldo de hoje (16/10/2026): R$ 3.000,00')
        ->assertDontSee('R$ 999.999,00')
        ->assertDontSee('R$ 12.345,00');

    Http::assertSent(function ($request) {
        $html = $request['html'] ?? '';

        return str_contains($html, 'R$ 3.000,00')
            && ! str_contains($html, 'R$ 999.999,00')
            && ! str_contains($html, 'R$ 12.345,00');
    });

    expect(DailyTransaction::withoutGlobalScopes()->count())->toBe($before);
});

it('rejects identity arguments', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(GetThermometerImageTool::class, ['user_id' => 999])
        ->assertHasErrors(['identidade']);
});

it('renders fresh data on consecutive calls', function () {
    $this->travelTo('2026-10-16');

    $account = User::factory()->create();
    useMcpAccount($account);

    config()->set('cash_compass.thermometer_image.cloudflare_account_id', 'acc-123');
    config()->set('cash_compass.thermometer_image.cloudflare_api_token', 'token-123');

    UserInitialBalance::factory()->for($account)->create([
        'amount' => 3000,
        'base_date' => '2026-10-01',
    ]);

    Http::fake(['api.cloudflare.com/*' => Http::response("\x89PNG fake", 200)]);

    CashCompassServer::tool(GetThermometerImageTool::class, [])
        ->assertOk()
        ->assertSee('Saldo de hoje (16/10/2026): R$ 3.000,00');

    DailyTransaction::factory()->for($account)->create([
        'date' => '2026-10-10',
        'amount' => 100,
    ]);

    CashCompassServer::tool(GetThermometerImageTool::class, [])
        ->assertOk()
        ->assertSee('Saldo de hoje (16/10/2026): R$ 2.900,00');
});
