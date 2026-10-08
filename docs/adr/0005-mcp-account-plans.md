# ADR 0005 — Planos de contas e recorrências no MCP

## Contexto

A ADR 0003 entregou as tags no MCP. A entrega da issue #6 precisa permitir que o
Hermes crie, edite, ative/desative e exclua planos de contas recorrentes da
conta fixa, gerando e regenerando ocorrências pelos mesmos contratos
financeiros do painel, sem criar regra de recorrência paralela. O número 0004
fica reservado para a entrega paralela de configurações (saldo inicial,
previsão de diário e check-ins).

## Decisão

- Expor cinco ferramentas explícitas de plano: `create_account_plan` (com
  `operation_key` idempotente e `#[IsIdempotent]`), `update_account_plan`,
  `activate_account_plan`, `deactivate_account_plan` e `delete_account_plan`
  (`#[IsDestructive]`). Nenhuma aceita `user_id` nem identidade; todas resolvem
  a conta fixa por `AccountTool` + `ConfiguredAccount`.
- Reutilizar o domínio existente em vez de regra paralela. Criação e edição
  usam o model `AccountPlan` (nunca update/delete em massa), preservando os
  eventos do `AccountPlanObserver`: `created` gera ocorrências; `updated`, ao
  mudar um campo de agendamento, cancela as pendências geradas e regera;
  `deleting` bloqueia a exclusão com lançamento realizado até hoje e cancela as
  pendências quando permite. Ativar/desativar usam o mesmo observer, sem nova
  semântica de recorrência.
- O tipo Diário continua exclusivo de lançamentos manuais: os planos aceitam
  apenas `TransactionType::planCases()`. Frequência, intervalo, datas e limites
  (`occurrences`, `ends_at`) validam contra os mesmos contratos do painel, e
  valores monetários usam representação decimal exata com duas casas.
- A criação gera as ocorrências antes das tags existirem (observer); a
  ferramenta sincroniza as tags e chama o `RecurrenceGenerator` de novo, como o
  painel, para que as ocorrências pendentes reflitam as tags do plano. A edição
  faz o mesmo, mantendo o plano como fonte de verdade das tags das ocorrências
  pendentes recorrentes; lançamentos realizados não são alterados.
- Excluir um plano com lançamento realizado até hoje retorna erro de validação
  em vez de sucesso silencioso; se o observer recusar a exclusão, a transação
  reverte e a ferramenta também retorna erro.
- Reutilizar o `MutationLedger` da issue #4: `create_account_plan` usa `run()`
  com hash canônico dos argumentos; as demais alterações usam `record()` para o
  histórico mínimo. Nenhum mecanismo de idempotência paralelo. Tags novas
  vinculam apenas tags ativas da conta; tags arquivadas já vinculadas são
  preservadas (via `Tag::attachableTo`).
- A confirmação antes de exclusões e de alterações de recorrência que
  cancelam/regeneram ocorrências é uma proteção comportamental do agente,
  descrita nas instruções do servidor e nas descrições das ferramentas. Não é
  barreira de autorização nem comprovação de aprovação humana pelo servidor, e
  não há protocolo próprio de elicitation.

## Consequências

- O comportamento de recorrência permanece centralizado no
  `RecurrenceCalculator`/`RecurrenceGenerator`/`AccountPlanObserver`; o MCP é
  apenas outra entrada para as mesmas regras.
- O histórico mínimo (`mcp_mutation_audits`) segue sem descrição, valor ou
  conteúdo financeiro completo: guarda usuário, ferramenta, registro afetado,
  resultado e horário.
- Cada chamada resolve a conta e o gerador de novo, sem memoização obsoleta
  entre chamadas do processo stdio.
