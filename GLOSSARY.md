# Glossário do Cash Compass

Termos de domínio usados no produto, na documentação e no código. Quando um
termo mudar de significado, atualize este arquivo e o ADR correspondente.

## Gasto

Lançamento com status realizado e tipo Saída (`expense`), Diário (`daily`) ou
Cartão (`card`), com data dentro do período analisado. Entradas (`income`),
Economia (`savings`), lançamentos pendentes e pulados ficam fora da definição.
Os totais de gasto não fecham com o saldo do Termômetro por desenho, porque
Economia é excluída da análise.

## Período

Intervalo inclusivo nas duas pontas usado por um relatório. O padrão é o mês
atual, e os presets disponíveis são mês atual, mês passado, últimos três meses,
ano atual e intervalo personalizado. O mesmo período vale para todas as abas e
indicadores da página de relatórios.

## Dia ativo

Dia do período com pelo menos um lançamento que entra na definição de gasto. A
média por dia ativo divide o total gasto pela quantidade de dias ativos; sem
dias ativos, o indicador fica vazio.

## Relatório de tags

Leitura agregada do gasto por tag no período. Cada lançamento soma seu valor
integral em cada tag vinculada, então a soma das tags pode passar do total
gasto e a participação de cada tag pode somar mais de 100%. Tags arquivadas com
gasto no período entram com indicação textual; tags sem gasto ficam fora. O
gasto sem tag aparece em uma linha "Sem tag" ao final da tabela.
