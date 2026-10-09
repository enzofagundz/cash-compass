# ADR 0002 — Mutações de lançamentos no MCP com idempotência e histórico mínimo

## Contexto

A ADR 0001 registrou o servidor MCP local somente leitura. A próxima entrega
(issue #4) precisa permitir que o Hermes crie, edite, confirme, pule e exclua
lançamentos individuais da conta fixa, sem duplicar criações repetidas por
falhas ou timeouts e sem abrir escrita genérica.

## Decisão

- Expor cinco ferramentas explícitas de lançamento: `create_transaction`
  (somente manual), `update_transaction`, `confirm_transaction`,
  `skip_transaction` e `delete_transaction`. Sem SQL, escrita genérica em
  modelos ou operações em massa. Nenhuma aceita `user_id` nem identidade.
- Reutilizar a conta fixa (`AccountTool` + `ConfiguredAccount`) e o
  serializador de domínio. Confirmar e pular só aceitam lançamentos
  `pending`; excluir só aceita lançamento manual e a ferramenta recebe
  `#[IsDestructive]`.
- Compartilhar as validações de vínculo com o painel por escopos de domínio:
  `Tag::attachableTo` (tags ativas da conta, preservando as já vinculadas em
  edições) e `AccountPlan::selectableFor` (planos ativos da conta). A
  positividade do valor continua no hook `saving` de `DailyTransaction`. O
  painel Filament passa a usar os mesmos escopos, sem regra paralela.
- Criações exigem `operation_key` única por conta. `mcp_operations` guarda a
  chave, a ferramenta, o hash canônico dos argumentos e o resultado
  serializado. Mesma chave e mesmos argumentos devolvem o resultado anterior;
  mesma chave e argumentos diferentes falham. O insert da chave acontece na
  mesma transação da criação, com `unique(user_id, operation_key)`, o que
  cobre concorrência e repetição após timeout sem duplicar.
- `mcp_mutation_audits` registra histórico mínimo: usuário, ferramenta,
  registro afetado (tipo e id), resultado e horário. Não guarda conversa,
  credenciais nem conteúdo financeiro completo; o resultado financeiro
  necessário à idempotência fica apenas em `mcp_operations`.
- A confirmação antes de exclusões e alterações de efeito amplo é uma
  proteção comportamental do agente, descrita nas instruções do servidor e na
  descrição da ferramenta. Não é barreira de autorização nem comprovação de
  aprovação humana pelo servidor, e não há protocolo próprio de elicitation.

## Consequências

- A idempotência é por conta e por chave; o hash usa a representação canônica
  dos argumentos (valor com duas casas, tags ordenadas), então a mesma
  intenção reenvia o mesmo resultado.
- O processo stdio é persistente: cada chamada resolve a conta, os
  calculadores e os escopos de novo, sem memoização obsoleta entre chamadas.
- O histórico mínimo é reutilizável pelas próximas ferramentas (tags, planos,
  saldo inicial e previsão de diário) por meio do mesmo `MutationLedger`.
- Criar um lançamento para um plano que já tem ocorrência na mesma data devolve
  erro de validação claro, sem falha bruta e sem confundir com a chave de
  operação.
- `confirm_transaction` e `skip_transaction` são transições de estado sem chave
  de operação: repetir após sucesso falha como no painel, então não são
  anunciadas como idempotentes.
- A mesma chave usada por uma ferramenta diferente é conflito, nunca replay
  cruzado do resultado de outra ferramenta.
- A fronteira de confiança continua sendo o processo local: a conta fixa
  limita as ferramentas, não os privilégios do processo hospedeiro.
