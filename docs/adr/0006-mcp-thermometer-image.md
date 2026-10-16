# ADR 0006 — Imagem diária do termômetro no MCP

## Contexto

O relatório financeiro diário do Hermes (cron às 7h do perfil `julinho`, entrega
no Telegram) era só texto. O usuário quer enxergar de relance o mês atual do
termômetro — a mesma grade do painel, com cores de saldo, check-ins e projeções —
ao lado do relatório. A grade vive dentro de um componente Livewire/Filament que
exige sessão autenticada, e o projeto não tem navegador headless, hospedagem de
imagem nem rota pública; dados financeiros não podem ganhar URL pública.

## Decisão

- Expor uma tool MCP somente leitura `get_thermometer_image`, sem parâmetros
  (sempre o mês atual, fuso `America/Sao_Paulo`), resolvendo a conta fixa pelo
  mesmo `AccountTool` + `ConfiguredAccount` das demais ferramentas. A resposta é
  um content block de imagem (`image/png` em base64) seguido de um resumo curto
  em texto (mês, saldo de hoje e totais do mês), para o relatório continuar útil
  em canais sem renderização de imagem.
- Renderizar a grade em uma view Blade autossuficiente (CSS inline, sem
  Livewire/Filament/Tailwind, fundo branco), alimentada pelo presenter canônico
  do painel (`ThermometerGrid` sobre `BalanceCalculator::horizonGrid()`) — a view
  não recalcula nada e a página do Termômetro permanece intocada.
- Rasterizar pela REST API da Cloudflare (Browser Rendering/Browser Run,
  `POST /accounts/{id}/browser-run/screenshot`) enviando o HTML inline: sem
  navegador local, sem Worker, sem hospedagem e sem URL pública. O payload exige
  `viewport.width` e `viewport.height` (largura 1200, escala 2, `fullPage`); a
  resposta é PNG binário. A imagem trafega apenas embutida na resposta MCP.
- Configurar por `cash_compass.thermometer_image` com `CLOUDFLARE_ACCOUNT_ID` e
  `CLOUDFLARE_API_TOKEN` (token manual com permissão de Browser Rendering; o
  plano gratuito cobre uma chamada diária). Sem SDK PHP oficial: cliente HTTP do
  framework, nenhuma dependência nova.
- Sem cache e sem pré-render agendado: cada chamada gera a imagem fresca, para
  pedidos ad-hoc no chat refletirem os dados atuais.
- Degradação segura: credenciais ausentes ou falha da API (rede, token, limite,
  resposta inesperada — com o detalhe do erro da Cloudflare quando houver) fazem
  a tool devolver o resumo em texto com aviso explícito, nunca um erro. O
  comando local `thermometer:image --output` gera o mesmo PNG para aprovação
  visual e diagnóstico.
- Lado Hermes (fora do repositório): cwd do servidor `cash_compass` corrigido
  para o checkout principal do projeto na configuração raiz e na do perfil
  `julinho`; a conta fixa passa a vir do `.env` do app; o prompt do job diário
  chama a tool e repete `MEDIA:<caminho>` na resposta final, porque o
  auto-anexo do gateway só cobre a allowlist de mídia (`text_to_speech`,
  `image_generate`) — tools MCP exigem a repetição da tag. Tool nova em servidor
  stdio já conectado só aparece após reiniciar o gateway do perfil.

## Consequências

- O HTML renderizado (com valores financeiros) sai do host apenas para a
  rasterização na Cloudflare, dentro da conta do próprio usuário; a imagem nunca
  ganha URL pública nem armazenamento externo — o Hermes cacheia o PNG local no
  diretório de mídia do perfil para anexar no Telegram.
- O relatório diário ganhou um ponto de falha externo, tratado com degradação
  explícita: sem Cloudflare ou sem credenciais, o texto continua chegando.
- Nenhuma mudança nas consultas, no orçamento de render da página do Termômetro
  ou nas regras financeiras; a tool reutiliza os mesmos calculadores.
- A validação real fixou o caminho `browser-run/screenshot` (nome legado
  `browser-rendering`) e o contrato do payload; chamadas fora do free tier
  (rajadas ad-hoc) consomem a cota de navegador da conta.
