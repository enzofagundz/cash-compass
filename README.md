# Cash Compass

Aplicação web de finanças pessoais: lançamentos diários, planos recorrentes, tags, saldo inicial e um termômetro de saldos com projeção de 12 meses. Construída como um monólito Laravel com painel Filament, onde cada conta enxerga apenas os próprios dados.

## Funcionalidades

- **Termômetro** — planilha financeira diária com entradas, saídas, diários, economias e cartão, saldo acumulado e projeção futura, em horizonte configurável (1 a 12 meses). Detalhe por célula com criação, edição, confirmação, pulo e exclusão de lançamentos.
- **Lançamentos** — CRUD com status `pendente`/`realizado`/`pulado`, tags, vínculo opcional a um plano e filtros por ano, mês, status e origem.
- **Planos de contas recorrentes** — frequências diária, semanal, quinzenal, mensal, anual e parcelada; as ocorrências são geradas automaticamente para o horizonte e mantidas em dia pelo comando `recurrence:tick`.
- **Saldo inicial** — valor e data-base que servem de ponto de partida para o cálculo do saldo.
- **Check-in diário** — marcação por dia (sem permitir dias futuros).
- **Usuários** — autenticação completa (login, registro, redefinição de senha, verificação de e-mail e perfil) e administração de contas com papéis `admin` e `user`.
- **Isolamento por usuário** — todas as entidades de domínio são filtradas pela conta autenticada.

## Stack

| Camada    | Tecnologia                                   |
|-----------|----------------------------------------------|
| Backend   | PHP 8.5, Laravel 13                          |
| Painel    | Filament 5, Livewire 4                       |
| Frontend  | Tailwind 4, Vite 8 (vite-plus)               |
| Banco     | PostgreSQL 16                                |
| Testes    | Pest 5, PHPStan/Larastan, Laravel Pint       |
| Ambiente  | lerd (Podman) para o desenvolvimento local   |

## Requisitos

- lerd com Podman (recomendado — fornece PHP, Node, PostgreSQL e nginx)
- Ou, sem lerd: PHP 8.5+, Composer 2, Node 22+, PostgreSQL 16

## Como rodar

### Com lerd (recomendado)

```bash
git clone git@github.com:enzofagundz/cash-compass.git
cd cash-compass

composer install
npm install

lerd link       # registra o site em cash-compass.test (uma vez)
lerd env        # cria/ajusta o .env, gera a APP_KEY e aponta para os serviços do lerd
lerd db:create  # cria o banco cash_compass e o de testes
php artisan migrate --seed
npm run build
lerd open       # abre http://cash-compass.test
```

No lerd, `php`, `composer`, `node` e `npm` são shims: os comandos rodam no container com o PHP e o Node do projeto. Os workers `queue` e `vite` sobem junto com o ambiente e podem ser controlados com `lerd queue:start|stop` e `lerd vite:start|stop`.

### Sem lerd

```bash
composer run setup   # instala deps, cria .env, gera key, migra e builda
```

Se preferir PostgreSQL, ajuste o `.env` (`DB_CONNECTION=pgsql`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) e rode `php artisan migrate --seed` novamente. Para o HMR, use `npm run dev`.

## Acesso

Após o seed, o `DatabaseSeeder` cria o administrador:

```text
E-mail: admin@example.com
Senha:  password
```

O registro está aberto em `/register` para criar contas de usuário comum. Contas inativas não entram no painel.

## Rotinas agendadas

| Comando            | Frequência | Função                                                                      |
|--------------------|------------|-----------------------------------------------------------------------------|
| `recurrence:tick`  | diária     | Gera as ocorrências do horizonte e confirma os lançamentos vencidos.        |
| `app:ping-scheduler` | diária   | Verifica se o agendador está ativo.                                          |

Em desenvolvimento o scheduler não é um worker do lerd; rode os comandos manualmente ou use `lerd schedule:start` para simular o cron.

## Testes e qualidade

```bash
php artisan test --compact                    # suíte Pest
php artisan test --compact --filter=BalanceCalculatorTest
vendor/bin/pint --dirty                       # formata arquivos alterados
composer test                                 # pint + phpstan + suíte completa
composer ci:check                             # mesmo fluxo executado no CI
```

Os testes usam SQLite em memória (`phpunit.xml`) e não dependem do banco do lerd. O CI roda em push para `main` e em pull requests (`.github/workflows/tests.yml`).

## Estrutura

```text
app/
├── Concerns/       # BelongsToUser: isolamento por usuário
├── Console/        # comandos agendados (recurrence:tick, app:ping-scheduler)
├── Enums/          # TransactionType, TransactionStatus, RecurrenceFrequency, UserRole, Month
├── Filament/       # painel: Pages (Termômetro, Saldo inicial) e Resources
├── Models/         # User, AccountPlan, DailyTransaction, Tag, DayCheckIn, UserInitialBalance
├── Observers/      # AccountPlanObserver: gera/cancela ocorrências
├── Policies/       # UserPolicy
└── Services/       # BalanceCalculator, RecurrenceCalculator, RecurrenceGenerator
resources/views/filament/   # views Blade do painel
routes/console.php          # agendamentos
tests/Feature, tests/Unit   # Pest
```

## Documentação

- [`PROJECT.md`](PROJECT.md) — fonte canônica de contexto: domínio, regras financeiras, arquitetura e workflow.
- [`AGENTS.md`](AGENTS.md) — orientação para agentes de código.
- `.references/` — material de referência local (não versionado).
