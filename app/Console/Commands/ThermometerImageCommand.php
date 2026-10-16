<?php

namespace App\Console\Commands;

use App\Mcp\Support\ConfiguredAccount;
use App\Services\ThermometerImageRenderer;
use App\Services\ThermometerImageUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

#[Signature('thermometer:image {--output= : Caminho absoluto do arquivo PNG}')]
#[Description('Gera o PNG do mês atual do termômetro usando a API Cloudflare Browser Rendering.')]
class ThermometerImageCommand extends Command
{
    public function handle(ThermometerImageRenderer $renderer, ConfiguredAccount $accounts): int
    {
        try {
            $account = $accounts->resolve();
            $png = $renderer->png($account);
        } catch (ThermometerImageUnavailable|ValidationException $exception) {
            $this->components->error($this->failureMessage($exception));

            return self::FAILURE;
        }

        $path = (string) ($this->option('output') ?: storage_path(
            'app/thermometer-image-'.CarbonImmutable::now()->format('Y-m').'.png'
        ));

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $png);

        $this->components->info("Imagem do termômetro salva em {$path}.");

        return self::SUCCESS;
    }

    private function failureMessage(\Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            return (string) (collect($exception->errors())->flatten()->first() ?? $exception->getMessage());
        }

        return $exception->getMessage();
    }
}
