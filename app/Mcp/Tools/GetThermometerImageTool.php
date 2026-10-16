<?php

namespace App\Mcp\Tools;

use App\Mcp\AccountTool;
use App\Mcp\Support\ConfiguredAccount;
use App\Services\ThermometerImageRenderer;
use App\Services\ThermometerImageUnavailable;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetThermometerImageTool extends AccountTool
{
    protected string $name = 'get_thermometer_image';

    protected string $title = 'Imagem do termômetro';

    protected string $description = 'Retorna a imagem do mês atual do termômetro (grade de dias com cores de saldo, check-ins e projeções) seguida de um resumo em texto. Use-a no relatório financeiro diário; em canais com anexo (por exemplo, Telegram), anexe a imagem repetindo no texto final a tag de mídia devolvida no resultado da ferramenta.';

    public function handle(
        Request $request,
        ConfiguredAccount $accounts,
        ThermometerImageRenderer $renderer,
    ): Response|ResponseFactory {
        $account = $this->account($request, $accounts);

        $summary = $renderer->summary($account);

        try {
            $png = $renderer->png($account);
        } catch (ThermometerImageUnavailable $exception) {
            return Response::text(
                $summary.' Não foi possível gerar a imagem do termômetro agora: '.$exception->getMessage()
            );
        }

        return Response::make([
            Response::image($png),
            Response::text($summary),
        ]);
    }
}
