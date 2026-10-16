<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Filament\Pages\Thermometer\ThermometerGrid;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Renders the current month thermometer grid as a PNG.
 *
 * The HTML is self-contained (inline CSS, no external assets) because the
 * Cloudflare screenshot API only receives the markup itself, and the data
 * comes from the canonical grid presenter used by the panel.
 */
class ThermometerImageRenderer
{
    private const ENDPOINT = 'https://api.cloudflare.com/client/v4/accounts/%s/browser-run/screenshot';

    private const VIEWPORT_WIDTH = 1200;

    private const VIEWPORT_HEIGHT = 1000;

    public function __construct(
        private readonly ThermometerGrid $grid,
        private readonly BalanceCalculator $balanceCalculator,
    ) {}

    public function png(User $user): string
    {
        $accountId = config('cash_compass.thermometer_image.cloudflare_account_id');
        $token = config('cash_compass.thermometer_image.cloudflare_api_token');

        if (! is_string($accountId) || trim($accountId) === '' || ! is_string($token) || trim($token) === '') {
            throw new ThermometerImageUnavailable('As credenciais da Cloudflare não estão configuradas.');
        }

        try {
            $response = Http::withToken($token)
                ->timeout(60)
                ->post(sprintf(self::ENDPOINT, $accountId), [
                    'html' => $this->html($user),
                    'viewport' => [
                        'width' => self::VIEWPORT_WIDTH,
                        'height' => self::VIEWPORT_HEIGHT,
                        'deviceScaleFactor' => 2,
                    ],
                    'screenshotOptions' => [
                        'type' => 'png',
                        'fullPage' => true,
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw new ThermometerImageUnavailable(
                'Falha de rede ao chamar a API da Cloudflare: '.$exception->getMessage()
            );
        }

        $body = $response->body();

        if ($response->failed() || ! str_starts_with($body, "\x89PNG")) {
            throw new ThermometerImageUnavailable($this->failureMessage($response->status(), $body));
        }

        return $body;
    }

    public function html(User $user): string
    {
        return view('mcp.thermometer-image', [
            'month' => $this->month($user),
            'columns' => TransactionType::cases(),
            'initialBalance' => $this->initialBalanceSummary($user),
            'money' => fn (string $value): string => $this->money($value),
        ])->render();
    }

    /**
     * Short pt-BR summary of the current month, used beside the image and as
     * the fallback when the image cannot be generated.
     */
    public function summary(User $user): string
    {
        $month = $this->month($user);

        $lines = ['Termômetro de '.$month['label'].'.'];

        foreach ($month['days'] as $day) {
            if ($day['is_today'] === true) {
                $lines[] = 'Saldo de hoje ('.$day['short_date'].'): '.$this->money($day['balance']).'.';

                break;
            }
        }

        $totals = collect(TransactionType::cases())
            ->map(fn (TransactionType $type): string => mb_strtolower($type->label(), 'UTF-8').' '.$this->money($month['totals'][$type->value]))
            ->implode(', ');

        $lines[] = 'Totais do mês: '.$totals.'.';

        return implode(' ', $lines);
    }

    private function money(string $value): string
    {
        return 'R$ '.number_format((float) $value, 2, ',', '.');
    }

    /**
     * Current month grid row, enriched the same way the panel grid is.
     *
     * @return array<string, mixed>
     */
    private function month(User $user): array
    {
        $now = CarbonImmutable::now();

        return $this->grid->months($user, (int) $now->year, (int) $now->month, 1)[0];
    }

    /**
     * @return array{amount: string, starts_at: string}|null
     */
    private function initialBalanceSummary(User $user): ?array
    {
        $balance = $this->balanceCalculator->initialBalance($user);
        $startsAt = $this->balanceCalculator->startsAt($user);

        if ($balance === null || $startsAt === null) {
            return null;
        }

        return [
            'amount' => (string) $balance->amount,
            'starts_at' => $startsAt->format('d/m/Y'),
        ];
    }

    /**
     * Failure message including the Cloudflare error detail, when present.
     */
    private function failureMessage(int $status, string $body): string
    {
        $message = 'A API da Cloudflare não retornou um PNG (HTTP '.$status.')';

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($body, true);
        $detail = is_array($decoded) ? data_get($decoded, 'errors.0.message') : null;

        return is_string($detail) ? $message.': '.$detail.'.' : $message.'.';
    }
}
