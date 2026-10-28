# ADR 0007 — Relatórios de gastos e análise por tags

## Status

Aceita em 2026-10-28.

## Contexto

O usuário registra lançamentos diários e classifica parte deles com tags, mas o
painel só oferece a planilha do Termômetro e a lista de lançamentos. Não existe
leitura agregada de horário, dia da semana, tipo, evolução mensal ou destino do
dinheiro por tag. Sem uma definição canônica de gasto, cada tela poderia somar
tipos e status diferentes e produzir números que não fecham entre si.

## Decisão

- **Gasto** é um lançamento com status realizado e tipo Saída (`expense`),
  Diário (`daily`) ou Cartão (`card`), com data dentro do período. Entradas,
  Economia (`savings`), pendentes e pulados ficam fora. A exclusão de Economia
  faz os totais do relatório divergirem do saldo do Termômetro por desenho.
- O **período** é inclusivo nas duas pontas, com presets mês atual, mês passado,
  últimos três meses, ano atual e intervalo personalizado. O padrão é o mês
  atual. A comparação usa o intervalo imediatamente anterior de mesma duração,
  não necessariamente o mês calendário anterior.
- O **horário** do bubble chart vem de `created_at` do lançamento manual
  (`is_recurring = false`), no fuso do app. O período filtra pela data do
  lançamento, não pela data de criação. Ocorrências geradas por plano ficam
  fora do gráfico de horários. A dimensão é rotulada como "horário do
  lançamento", porque mede quando o registro foi criado, não quando o gasto
  aconteceu.
- A **agregação por tag** soma o valor integral de cada lançamento em cada tag
  vinculada. A participação usa como denominador o total gasto do período,
  incluindo o que não tem tag; por isso a soma das participações pode passar de
  100% e a tela avisa isso. Tags arquivadas com gasto entram; tags sem gasto
  ficam fora; o gasto sem tag aparece como "Sem tag".
- Um único service concentra a definição de gasto, os recortes de período, os
  indicadores, as séries dos gráficos e as linhas da tabela de tags. Os widgets
  apenas consomem estruturas prontas e não recalculam regra financeira.
- O Dashboard mostra sempre o mês atual, independente do filtro da página de
  relatórios. A página de relatórios é restrita a usuários não-admin; o
  administrador continua apenas com a gestão de usuários.

## Consequências

- Os totais de gasto podem divergir do saldo do Termômetro sempre que houver
  Economia, receitas, pendentes ou pulados; não "corrigir" o relatório para
  igualar o saldo.
- A hora do lançamento é uma aproximação: um gasto registrado no dia seguinte
  aparece no horário do registro. A limitação fica explícita no rótulo e neste
  ADR.
- A soma das tags pode passar de 100%; testes fixam esse comportamento em vez
  de normalizar as participações.
- Com o preset padrão de um mês, a evolução mensal tem um único ponto; os
  presets de três meses e ano existem para quando a tendência importa.
- O relatório é somente leitura: nenhuma query de escrita, job ou rotina
  agendada é necessária.
