# Cash Compass — Contexto do Projeto

> Guia operacional oficial para agentes de código e desenvolvedores. Este arquivo deve refletir o estado real do projeto e orientar decisões de implementação com segurança.

## Informações Básicas

```yaml
nome_aplicacao: "Cash Compass"
descricao: "Aplicação web de finanças pessoais: lançamentos diários, planos recorrentes, tags, saldo inicial e termômetro de saldos"
objetivo_do_arquivo: "ponto de entrada para agentes entenderem contexto técnico, de domínio e operacional"
versao_php_snapshot: "8.5.x"
versao_laravel_snapshot: "13.x"
versao_filament_snapshot: "5.x"
versao_livewire_snapshot: "4.x"
versao_tailwind_snapshot: "4.x"
versao_pest_snapshot: "5.x"
ambiente_local: "lerd (Podman) — site em app-do-bruno.test, PostgreSQL em lerd-postgres, banco app_do_bruno"
url_local: "app-do-bruno.test"
workers_locais: "queue e vite como serviços systemd do lerd"
js_runtime_local: "bun (js_runtime no .lerd.yaml)"
idioma_ui: "pt_BR"
timezone_padrao: "America/Sao_Paulo"
locale_padrao: "pt_BR"
```

Regra de validação: versões em snapshot devem ser conferidas antes de decisões críticas usando fontes reais do repositório (`composer.json`, `package.json`, `composer show --direct`, `php artisan about`).

Observação: `config('app.name')` retorna `Cash Compass`, alinhado ao nome oficial do produto e à UI.

---

## Contexto Estratégico do Produto

- O Cash Compass é uma ferramenta pessoal de controle financeiro diário, não multiusuário de sharing: cada conta (`users`) enxerga apenas os próprios dados.
- O modelo mental central é uma planilha financeira diária com projeção futura (ver `.references/saldos-reference.md` e `.references/termometro-guideline.md`, material de referência local não versionado).
- Integridade dos cálculos de saldo é requisito transversal: mudanças em lançamentos ou recorrência não podem quebrar a continuidade do saldo entre dias e meses.
- Cada entidade de domínio é isolada por usuário; decisões locais não podem atravessar essa fronteira.

---

## Manutenção Deste Arquivo

- Este arquivo é fonte viva de contexto operacional para agentes.
- Deve ser consultado antes de análise, implementação, revisão e após compactação de contexto.
- Deve ser reescrito sempre que mudanças alterarem arquitetura, fluxos, convenções, integrações, testes ou documentação relevante.
- Atualize este arquivo na mesma entrega em que a mudança alterar arquitetura, fluxo, convenção, recursos Filament, comandos agendados ou regras de negócio.
- Não manter documentação conflitante ou histórica aqui. Quando um padrão muda, substitua a regra antiga.
- Não há `docs/` nem MCP de regras de negócio neste projeto; este arquivo e o código real são as fontes canônicas.
- O `AGENTS.md` cobre guidelines de ferramentas (Boost, skills, comandos). Este arquivo cobre produto, domínio e arquitetura; evite duplicar conteúdo entre os dois.

---

## Arquitetura e Estrutura de Pastas

### Estrutura Principal

```yaml
app: "app/**"
routes: "routes/web.php, routes/console.php"
resources: "resources/**"
tests_feature: "tests/Feature/**"
tests_unit: "tests/Unit/**"
bootstrap: "bootstrap/app.php"
painel_filament: "app/Providers/Filament/AppPanelProvider.php"
migrations: "database/migrations/**"
seeders: "database/seeders/DatabaseSeeder.php"
referencias_locais: ".references/** (não versionado)"
```

### Camadas Observadas

```yaml
enums: "app/Enums — tipos, status, frequência, papéis, meses"
models: "app/Models — Eloquent com casts, escopo por usuário e PHPDoc de propriedades"
concerns: "app/Concerns/BelongsToUser — isolamento por usuário"
observers: "app/Observers/AccountPlanObserver — geração/cancelamento de ocorrências"
policies: "app/Policies/UserPolicy — autorização de administração de usuários"
services: "app/Services — BalanceCalculator, RecurrenceCalculator, RecurrenceGenerator"
console: "app/Console/Commands — comandos agendados"
filament_pages: "app/Filament/Pages — Termômetro e Saldo inicial"
filament_resources: "app/Filament/Resources — Lançamentos, Planos de contas, Usuários"
filament_concerns: "app/Filament/Concerns/VisibleToNonAdmins — acesso de páginas/recursos a não-admins"
views: "resources/views/filament — Blade do painel"
```

### Estado Arquitetural

- Monólito Laravel com painel Filament v5 único (`app`) servido na raiz `/`, dono da autenticação (login, registro, reset, verificação de e-mail e perfil).
- Sem API, sem JWT, sem módulos externos; `routes/web.php` apenas redireciona aliases legados (`/dashboard`, `/settings`, `/admin/*`) para as rotas do painel.
- Lógica financeira concentrada em services testáveis; páginas Filament orquestram UI, estado Livewire e persistência.
- Recursos e páginas de não-admin usam `VisibleToNonAdmins`; administração de usuários usa `UserPolicy` + `UserResource`.
- Composição de formulários é compartilhada: `DailyTransactionResource::formComponents()` e `AccountPlanResource::formComponents()` são reutilizados pelas actions do Termômetro.

---

## Modelo de Domínio

### Entidades

```yaml
User:
  tabela: "users"
  campos_proprios: "role (UserRole), is_active"
  relacoes: "hasOne initialBalance"
  regras: "isAdmin() distingue admin; canAccessPanel exige is_active"

UserInitialBalance:
  tabela: "user_initial_balances"
  campos: "amount decimal(10,2) default 0, base_date date nullable"
  relacao: "um por usuário"

AccountPlan:
  tabela: "account_plans"
  campos: "type, description, expected_amount, frequency, interval, day_of_month, day_of_week, starts_at, ends_at, occurrences, is_active"
  relacoes: "hasMany dailyTransactions; belongsToMany tags"
  observer: "AccountPlanObserver"

DailyTransaction:
  tabela: "daily_transactions"
  campos: "date, type, amount, description, account_plan_id nullable, is_recurring, status"
  relacoes: "belongsTo accountPlan; belongsToMany tags"
  unicidade: "unique(user_id, account_plan_id, date)"
  indice: "index(user_id, date, status)"

Tag:
  tabela: "tags"
  campos: "name; unique(user_id, name)"
  relacoes: "belongsToMany dailyTransactions, accountPlans"

DayCheckIn:
  tabela: "day_check_ins"
  campos: "date; unique(user_id, date)"
```

### Enums

```yaml
TransactionType: "income (+1), expense, daily, savings, card (todos -1)"
TransactionStatus: "pending, realized (default), skipped"
RecurrenceFrequency: "daily, weekly, biweekly, monthly, yearly, installment"
UserRole: "user, admin"
Month: "meses de 1 a 12 com labels pt-BR"
```

### Isolamento por Usuário (BelongsToUser)

- Todo model de domínio usa o trait `BelongsToUser`.
- O trait adiciona global scope `user` que filtra por `auth()->id()` quando há usuário autenticado, e preenche `user_id` no `creating`.
- Consultas fora de contexto autenticado (comandos agendados, testes com `forUser`) usam `withoutGlobalScope('user')` ou o scope `forUser`.
- Regra: nunca aceitar `user_id` vindo do navegador; dados de outro usuário não podem aparecer em nenhuma tela.
- Regra: ao consultar em job/comando, remover explicitamente o global scope e escopar por usuário.

---

## Regras Financeiras (Contrato Canônico)

### Cálculo de Saldo

```text
saldo_dia = saldo_dia_anterior + entradas − saídas − diários − economias − cartão
saldo_realizado = saldo inicial + receitas realizadas − despesas realizadas desde base_date, limitado a hoje
saldo_projetado = saldo realizado de hoje + pendências futuras até a data alvo
```

### Semântica de Status e Datas

- `realized` conta na data correspondente; `pending` entra apenas na projeção, a partir de hoje; `skipped` não conta em lugar algum, mas permanece registrado para não ser recriado.
- `UserInitialBalance.base_date`, quando definida, exclui do cálculo lançamentos anteriores a ela (o saldo inicial representa o ponto de partida).
- `BalanceCalculator` é a fonte única de verdade para saldos e grades; views não recalculam.
- `monthGrid()` e `horizonGrid()` retornam arrays formatados com strings decimais (2 casas); a view apenas formata/exibe.
- Moeda: valores trafegam como `decimal:2`/string; formatação de exibição usa `R$` com vírgula decimal.
- Observação registrada no guideline do Termômetro: robustez futura recomendada é calcular em centavos inteiros ou decimal, evitando aritmética acumulativa com `float`.

### Recorrência

- `RecurrenceCalculator` calcula datas de ocorrência por frequência, intervalo, `day_of_month`/`day_of_week` e limite (`ends_at` ou `occurrences`).
- Horizonte padrão de geração: `DEFAULT_HORIZON_MONTHS = 12`.
- Dias inexistentes no mês caem no último dia do mês (ex.: dia 31 vira 28/29 em fevereiro) via `addMonthsNoOverflow` + `min(dia, daysInMonth)`.
- `RecurrenceGenerator` cria apenas ocorrências ausentes; ocorrências existentes mantêm seus valores, exceto tags, que seguem o plano como fonte de verdade para pendentes recorrentes.
- `AccountPlanObserver`:
  - `created`: gera ocorrências.
  - `updated`: se campo de agendamento mudou, cancela pendências recorrentes geradas e regera.
  - `deleting`: bloqueia exclusão se houver lançamento realizado até hoje; caso contrário, cancela pendências e permite excluir.
- Lançamentos recorrentes não podem ser excluídos pela action de exclusão do Termômetro (somente manuais); recorrências são tratadas com "Pular ocorrência" (`skipped`) ou desativando o plano.

### Check-in

- `DayCheckIn::toggleFor()` alterna o estado do dia; datas futuras são bloqueadas na página do Termômetro com notificação de erro.
- Check-in é independente dos lançamentos.

---

## Acesso e Autorização

```yaml
admin:
  definicao: "role = admin"
  acesso: "UserResource (listar, criar, editar, desativar e redefinir senha de usuários)"
  exclusao: "não pode excluir a si mesmo (UserPolicy::delete)"

usuario_nao_admin:
  definicao: "role = user"
  acesso: "Termômetro, Saldo inicial, Lançamentos, Planos de contas"
  bloqueios: "UserResource não registra navegação e UserPolicy nega ações administrativas"

painel:
  entrada: "canAccessPanel exige is_active; contas inativas não entram"
```

- A autorização de usuários é feita por policy; a de páginas/recursos de domínio por `VisibleToNonAdmins` + global scope do trait.

---

## Painel Filament (UI e Recursos)

```yaml
AppPanelProvider:
  id: "app"
  path: "/"
  autenticacao: "login, registration, passwordReset, emailVerification, profile"
  cor_primaria: "Amber"
  tema: "resources/css/filament/app/theme.css"
  descoberta: "app/Filament/Resources, Pages, Widgets"
  home: "GET / → RedirectToHomeController (route panel home)"

recursos:
  DailyTransactionResource:
    label: "Lançamentos"
    pagina: "ManageDailyTransactions (única, sem create/edit separados)"
    filtros: "status, origem (recorrente/manual), ano, mês"
    acoes_linha: "Editar, Confirmar (pendente), Pular (pendente), Excluir"
  AccountPlanResource:
    label: "Plano de contas"
    pagina: "ManageAccountPlans"
    filtros: "tipo, frequência, ativo"
    acoes_linha: "Editar (regera ocorrências), Excluir (bloqueado com realizados)"
  UserResource:
    label: "Usuários"
    pagina: "ManageUsers"
    visibilidade: "somente admin"
    acoes_linha: "Editar, Desativar/Reativar, Redefinir senha"

paginas:
  ThermometerPage:
    slug: "thermometer"
    titulo: "Termômetro"
    funcao: "grade mensal diária em horizonte configurável (1–12 meses, padrão 12)"
    estado_url: "year, month, months"
    acoes: "Novo lançamento; por célula: adicionar, editar, confirmar, pular, excluir; selecionar período; configurar horizonte; ir para hoje; navegação mês/ano"
    views: "resources/views/filament/pages/thermometer/*"
  InitialBalanceSettings:
    slug: "initial-balance"
    titulo: "Saldo inicial"
    campos: "amount (obrigatório), base_date"
    view: "resources/views/filament/pages/initial-balance-settings.blade.php"
```

### Lacunas e Decisões Conhecidas do Termômetro

- Implementado: grade Blade própria (não tabela Filament), navegação temporal, limite de horizonte 1–12, detalhe em slide-over com ações por lançamento, check-in com bloqueio de futuro, cores de saldo por faixa.
- Não implementado da referência: "previsão de diário" editável (`.references/saldos-reference.md`, seção 8.4) e filtros `<select>` por coluna; tratar como pendência de produto, não como bug.
- O guideline completo de UI/UX/acessibilidade está em `.references/termometro-guideline.md`; usar como referência de design.

---

## Rotas, Frontend e Assets

```yaml
rotas_web: "routes/web.php — apenas redirects 301 de aliases legados"
rotas_filament: "geradas pelo painel app na raiz; /thermometer e /initial-balance visíveis aos não-admins autenticados"
api: "inexistente"
frontend:
  bundler: "Vite 8 + vite-plus (scripts: vp dev / vp build)"
  dev: "worker vite do lerd (js_runtime: bun)"
  css: "Tailwind 4"
  livewire: "4.x com atributos #[Computed], #[Url]; Alpine disponível para interações locais"
  tema_filament: "resources/css/filament/app/theme.css"
assets_extras: "livewire/blaze para otimização de componentes Blade"
```

Regras:

- Se mudanças de frontend não refletirem na UI, verifique o worker do Vite (`lerd worker list`) e rode o build (`npm run build`, ou `bun run build` com o runtime pinado) para os assets de produção.
- Reutilizar componentes e views existentes antes de criar novos.
- Não introduzir novo framework/biblioteca de frontend sem decisão explícita do mantenedor.

---

## Rotinas Agendadas

```yaml
fonte: "routes/console.php"
rotinas:
  - comando: "app:ping-scheduler"
    frequencia: "daily, onOneServer, withoutOverlapping"
    funcao: "prova que o pipeline do scheduler está ligado"
  - comando: "recurrence:tick"
    frequencia: "daily, onOneServer, withoutOverlapping"
    funcao: "gera o horizonte de recorrências e confirma lançamentos vencidos (pending com date <= hoje vira realized)"
regras:
  - "recurrence:tick roda sem escopo autenticado; usa withoutGlobalScopes e itera por usuário via gerador"
  - "sem filas dedicadas: queue e cache usam driver database"
  - "em desenvolvimento o scheduler não é worker do lerd; rode os comandos manualmente ou use lerd schedule:start"
```

---

## Testes e Qualidade

```yaml
framework_testes: "Pest 5"
estruturas:
  - "tests/Feature (Auth, Filament, Models, Policies, Services, baseline)"
  - "tests/Unit (Enums, Services)"
formatacao: "Laravel Pint (pint.json)"
analise_estatica: "PHPStan + Larastan (phpstan.neon)"
banco_de_testes: "conforme phpunit.xml"
```

Regras obrigatórias:

- Toda mudança de comportamento deve ter validação programática apropriada; executar o menor conjunto de testes capaz de cobrir o risco.
- Testes usam factories (`database/factories/`) e o trait de isolamento; em teste, considere o global scope e use `forUser`/`withoutGlobalScope` quando necessário.
- Fluxo completo de verificação: `composer test` (config:clear + pint --test + phpstan + php artisan test).
- Teste focado: `php artisan test --compact --filter=TestName` ou `vendor/bin/pest <path>`.

---

## Ambiente Local (lerd)

```yaml
site: "app-do-bruno.test (nginx + PHP-FPM 8.5 no container lerd-php85-fpm)"
node: "22"
js_runtime: "bun (js_runtime no .lerd.yaml)"
banco: "PostgreSQL 16 (container lerd-postgres)"
workers: "queue e vite (systemd: lerd-queue-app-do-bruno, lerd-vite-app-do-bruno)"
shims: "php, composer, node, npm e bun do host executam no ambiente do lerd"
```

- `php`, `composer`, `node`, `npm` e `bun` no PATH são shims do lerd: os comandos rodam com o PHP 8.5 e o Node do projeto dentro do container, não na máquina nua.
- O site é servido por nginx + PHP-FPM. Não usar `php artisan serve` nem `composer run dev` (conflitam com o vhost e com o worker do Vite).
- Workers sobem e reiniciam junto com o lerd; controle com `lerd queue:start`/`lerd queue:stop`, `lerd vite:start`/`lerd vite:stop` e `lerd worker list`.
- `lerd run` lista os comandos do framework (migrate, optimize:clear, key:generate, storage:link); `lerd shell` abre shell no container.
- O agendador não é worker declarado no `.lerd.yaml`: rode `php artisan recurrence:tick` manualmente ou use `lerd schedule:start` para simular o cron em desenvolvimento.
- Diagnóstico: `lerd status` (saúde), `lerd logs` (FPM/nginx/workers), `lerd site:doctor` (checagens do app) e `lerd profile` (SPX).

---

## Comandos Úteis

```bash
# Lerd (ambiente local)
lerd start / lerd stop     # sobe/derruba DNS, nginx, FPM, serviços e workers
lerd open                  # abre app-do-bruno.test no navegador
lerd status                # saúde de serviços e workers
lerd shell                 # shell no container PHP
lerd run                   # lista comandos do framework (migrate, optimize:clear…)

# Banco (PostgreSQL via lerd)
php artisan migrate
php artisan db:seed

# Workers
lerd worker list
lerd queue:start / lerd queue:stop   # fila (driver database)
lerd vite:start / lerd vite:stop     # dev server do Vite (HMR)

# Agendador
php artisan recurrence:tick
php artisan app:ping-scheduler

# Testes e qualidade (shims do lerd executam no container)
php artisan test --compact --filter=TestName   # alias: lerd test --compact --filter=TestName
vendor/bin/pint --dirty
composer test
```

---

## Convenções Operacionais para Agentes (Workflow Obrigatório)

Antes de propor ou implementar qualquer alteração, seguir a ordem:

1. Ler este `PROJECT.md` e o `AGENTS.md` (e `.ai/rules/index.md` se existir).
2. Buscar regras internas, skills e padrões já definidos no repositório.
3. Consultar a documentação versionada do ecossistema Laravel/Filament aplicável ao tema.
4. Explorar o código real afetado (models, services, páginas, observers, testes).
5. Só então propor/implementar a alteração com impacto mínimo e validação objetiva.

Regras adicionais:

- Código-fonte e identificadores em inglês; UI e mensagens em pt_BR.
- Rodar comandos PHP/Composer/testes pelos shims do lerd (já no PATH); não subir servidor próprio (`artisan serve`/`composer run dev`).
- Seguir o padrão dominante da área impactada; migração arquitetural só com decisão manual explícita do mantenedor.
- Aplicar `laravel-best-practices`, `livewire-development` e `filament-specialist` (skills) conforme a área tocada.
- Não alterar o global scope `BelongsToUser` nem aceitar `user_id` do navegador.
- Preservar a continuidade do saldo e a semântica dos status (`pending`/`realized`/`skipped`).
- Ao alterar o Termômetro ou o `BalanceCalculator`, atualizar/executar os testes correspondentes em `tests/Feature/Filament/ThermometerPageTest.php` e `tests/Feature/Services/BalanceCalculatorTest.php`.
- Nunca comitar `.references/` (não versionado) nem credenciais reais em código ou documentação.

---

## Encoding e Acentuação

- Todos os arquivos devem permanecer em UTF-8.
- Preservar acentuação correta em pt-BR para UI, mensagens e documentação.
- Evitar qualquer alteração que introduza mojibake.

---

## Checklist de Aceite do `PROJECT.md`

- Documento cobre as seções obrigatórias sem lacunas críticas.
- Regras financeiras (saldo, status, base_date, recorrência) estão explícitas e consistentes com o código.
- Isolamento por usuário e autorização estão documentados e acionáveis.
- Inventário do painel (recursos, páginas, rotinas agendadas) está presente.
- Workflow obrigatório para agentes está acionável.
- Política de idioma/encoding está explícita (`pt_BR` + UTF-8).
- Lacunas conhecidas (previsão de diário, `config('app.name')`) estão registradas sem ambiguidade.
