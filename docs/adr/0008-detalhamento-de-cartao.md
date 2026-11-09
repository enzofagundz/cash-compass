# ADR 0008 — Detalhamento de cartão com itens de compra

## Status

Aceita em 2026-11-09.

## Contexto

Lançamentos do tipo Cartão carregam um único valor. Ele compõe a coluna Cartão,
entra no saldo e conta como gasto nos relatórios, mas não diz o que compõe a
compra. Sem esse detalhamento, o relatório de tags trata a fatura inteira como
uma coisa só e o usuário perde a classificação por tipo de compra dentro de um
cartão que mistura mercados, farmácias e assinaturas.

O risco da feature é virar uma segunda fonte de verdade financeira: se o item
passasse a somar no saldo, cada tela precisaria escolher entre pai e itens, e a
continuidade do saldo quebraria na primeira divergência.

## Decisão

- **O pai continua sendo a única fonte de verdade.** Itens de cartão
  (`card_items`) detalham o valor do lançamento-pai e nada mais: saldo,
  previsão, colunas do dia, total gasto, quebra por tipo, evolução mensal, dias
  ativos e maiores lançamentos continuam lendo o valor do pai. Nenhuma fórmula
  financeira muda.
- **Item pertence a um lançamento do tipo Cartão.** `CardItem` tem descrição
  obrigatória (até 255), valor maior que zero (`decimal(10,2)`), zero ou mais
  tags e nenhum campo próprio de data, status ou tipo. O isolamento por usuário
  é o mesmo `BelongsToUser` do resto do domínio, e o item herda o dono do pai:
  não existe caminho para vincular item a lançamento de outro usuário.
- **Fechamento exato, em centavos.** Quando existem itens, a soma tem que ser
  igual ao valor do pai, comparada em centavos. A validação é do conjunto, no
  salvamento, e detalhamento parcial não é persistido. Zero itens é permitido e
  mantém o comportamento atual do cartão.
- **Mudar valor ou tipo exige o conjunto.** Trocar o tipo para fora de Cartão
  com itens existentes é bloqueado; remover todos os itens no mesmo salvamento
  libera a troca. Mudar o valor do pai exige ajustar os itens no mesmo
  salvamento. A regra vive em `CardItemSet` (validação e persistência) e é
  reforçada no hook `saving` de `DailyTransaction`, para que nenhum caminho de
  escrita — formulário, action do Termômetro ou MCP — escape dela.
- **Cascata na exclusão.** Excluir o lançamento-pai exclui os itens
  (`cascadeOnDelete` no banco e `hasMany` no model).
- **Intervalo de detalhamento permitido.** Ocorrências pendentes de cartão
  geradas por plano só podem ser detalhadas depois da proteção de planos
  (ADR desta mesma entrega, seção de planos): qualquer mutação do plano que
  apagaria uma ocorrência pendente detalhada é bloqueada. Ocorrências
  realizadas ou puladas podem ser detalhadas livremente.
- **Tags do item alimentam o relatório, o valor não.** Item com tags próprias é
  atribuído a elas; item sem tag própria herda as tags do pai; sem nenhuma das
  duas, vai para "Sem tag". O valor do pai não é atribuído separadamente. Item
  com várias tags soma integral em cada tag, preservando a regra já documentada
  de que a soma das tags pode passar do total. Contagem e média do relatório de
  tags passam a contar itens (o rótulo "Lançamentos" dessas colunas vira
  "Registros") e a data usada é a do pai. As demais leituras de relatório
  continuam no pai.
- **Tag vinculada a item conta como uso.** Arquivar uma tag continua permitido;
  excluir fica bloqueado enquanto houver vínculo com item, do mesmo jeito que
  já acontece com lançamentos e planos.
- **UI no formulário existente.** O repetidor de itens entra em
  `DailyTransactionResource::formComponents()`, condicional ao tipo Cartão, e
  por isso vale para criar e editar na tela de Lançamentos e nas actions do
  Termômetro. Não existe modal separado só de itens: o lápis do painel do dia
  abre o mesmo formulário. O formulário mostra a soma dos itens e a diferença
  para o valor do pai, e o salvamento fica bloqueado enquanto não fechar. A
  tabela de Lançamentos mostra a contagem de itens e o painel do dia mostra
  "N itens · R$ X" com a lista colapsável (descrição, valor e tags).
- **MCP sem tools de item na v1.** `get_transaction`, `list_transactions` e
  `get_horizon` continuam devolvendo o lançamento-pai (mais a contagem de itens);
  `update_transaction` recusa mudança de valor ou de tipo em cartão detalhado e
  segue editando descrição, data, status, plano e tags; `delete_transaction`
  exclui o pai e leva os itens junto; as tools de tag ganham a contagem de itens
  e a exclusão considera item como vínculo; as tools de plano propagam o
  bloqueio de ocorrência pendente detalhada.
- **Sem backfill e sem página própria.** Cartões existentes continuam sem
  detalhamento até o usuário editar; não há resource, página ou item de menu
  para `CardItem`.

## Consequências

- O total de gasto do relatório continua fechando com o pai, mas a soma das
  linhas de tag pode passar do total — comportamento já documentado e agora
  também válido para cartões detalhados.
- Como o item nunca entra em saldo nem em previsão, uma divergência entre pai e
  itens só pode existir transitoriamente dentro de uma requisição; o banco
  recusa o estado fechado errado.
- O formulário de lançamento fica mais alto quando o tipo é Cartão. O repetidor
  é opcional, então o fluxo de cartão sem detalhamento não muda.
- A ordem dos itens é a de inserção; não há reordenação manual.
- O histórico do MCP não guarda itens: `mcp_operations` e `mcp_mutation_audits`
  continuam com chave, ferramenta, hash dos argumentos, registro afetado e
  horário.
