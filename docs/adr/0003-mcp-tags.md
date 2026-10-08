# ADR 0003 — Tags e vínculos de lançamentos no MCP

## Contexto

A ADR 0002 entregou as mutações de lançamentos individuais com idempotência e
histórico mínimo. A entrega da issue #5 precisa permitir que o Hermes gerencie
tags individuais e seus vínculos com lançamentos da conta fixa, mantendo a
normalização, a paleta de cores, o arquivamento e os bloqueios de exclusão já
existentes no Cash Compass.

## Decisão

- Expor cinco ferramentas explícitas de tag: `create_tag` (com
  `operation_key` idempotente e `#[IsIdempotent]`), `update_tag`,
  `archive_tag`, `reactivate_tag` e `delete_tag` (`#[IsDestructive]`).
  Nenhuma aceita `user_id` nem identidade; todas resolvem a conta fixa por
  `AccountTool` + `ConfiguredAccount`.
- Reutilizar o domínio existente em vez de regra paralela: `Tag::normalizeName`
  e o hook `saving` normalizam o nome; a unicidade é a constraint
  `(user_id, normalized_name)`, que inclui tags arquivadas; as cores vêm do
  enum `TagColor`; `Tag::archive`/`Tag::reactivate` mudam o estado; e
  `Tag::hasUsage` bloqueia a exclusão quando há vínculos com lançamentos ou
  planos.
- Reutilizar o `MutationLedger` da issue #4: `create_tag` usa `run()` com hash
  canônico dos argumentos (nome normalizado e cor), insert da chave na mesma
  transação da criação; as demais alterações usam `record()` para o histórico
  mínimo. Nenhum mecanismo de idempotência paralelo.
- Vínculos de lançamentos continuam a cargo das ferramentas de lançamento da
  issue #4, que já usam `Tag::attachableTo` e `ResolvesTransactionRelations`:
  novos vínculos só aceitam tags ativas da conta, e vínculos existentes com
  tags arquivadas são preservados em edições. A issue #5 verifica esse contrato
  e não duplica a regra.
- A confirmação antes de excluir uma tag é uma proteção comportamental do
  agente, descrita nas instruções do servidor e na descrição da ferramenta.
  Não é barreira de autorização nem comprovação de aprovação humana pelo
  servidor, e não há protocolo próprio de elicitation.

## Consequências

- A paleta de cores não altera cálculos financeiros; tags são apenas
  classificação. Arquivar mantém o histórico de vínculos intacto.
- Excluir uma tag sem vínculos é irreversível e exige confirmação na conversa;
  tags vinculadas devem ser arquivadas em vez de excluídas.
- O histórico mínimo (`mcp_mutation_audits`) segue sem nome, cor ou conteúdo
  financeiro: guarda usuário, ferramenta, registro afetado, resultado e
  horário. O resultado necessário à idempotência fica apenas em
  `mcp_operations`.
- O padrão de tags é reutilizável pelas próximas ferramentas (planos de contas,
  saldo inicial, previsão de diário) pelo mesmo `MutationLedger`.
