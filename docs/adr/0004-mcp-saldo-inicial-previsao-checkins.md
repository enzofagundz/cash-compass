# ADR 0004 — Saldo inicial, previsão de diário e check-ins no MCP

## Contexto

A ADR 0001 registrou o servidor MCP local somente leitura e a ADR 0002 as
mutações de lançamentos individuais. A issue #7 estende o servidor para
configurar o saldo inicial, os itens e o divisor da previsão de diário e os
check-ins individuais, tornando os efeitos observáveis pelas consultas
existentes, sem abrir escrita genérica nem alterar as regras financeiras.

## Decisão

- Expor ferramentas explícitas: `update_initial_balance`,
  `create_daily_forecast`, `update_daily_forecast`, `delete_daily_forecast`,
  `set_forecast_divisor` e `set_day_check_in`. Sem SQL nem escrita genérica em
  modelos; nenhuma aceita `user_id` nem identidade.
- Criações e o saldo inicial (registro único, inclusive a primeira
  configuração) usam `MutationLedger::run` com `operation_key`; edições,
  exclusões, divisor e check-in registram histórico mínimo com
  `MutationLedger::record`, sem mecanismo paralelo.
- `update_initial_balance` e `delete_daily_forecast` recebem `#[IsDestructive]`.
  A confirmação antes de exclusões e de alterações de efeito amplo é uma
  proteção comportamental do agente, não uma comprovação de aprovação humana
  pelo servidor.
- `base_date` vazia mantém a semântica do painel: o cálculo começa na data de
  criação do registro. O divisor aceita apenas 1 a 31, reutilizando
  `DailyForecastCalculator`.
- `set_day_check_in` define um estado explícito (`checked_in`) em vez de
  alternar, para que uma repetição com resultado incerto não inverta o dia;
  datas futuras são rejeitadas. O modelo `DayCheckIn` ganha `setFor`, com
  `toggleFor` delegando a ele.
- Cada chamada resolve a conta e os calculadores de novo; após uma mutação o
  efeito é lido de uma instância fresca, sem memoização obsoleta no processo
  stdio persistente.

## Consequências

- O Hermes administra saldo inicial, previsão de diário e check-ins pela mesma
  conta fixa, com idempotência nas criações e histórico mínimo reutilizado.
- O saldo e a previsão retornados coincidem com os calculadores canônicos do
  painel.
- As regras financeiras existentes permanecem intactas: nenhuma regra nova de
  saldo, recorrência ou previsão é introduzida.
