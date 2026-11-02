# Relatório de slop — telas do painel

Auditoria e correção das telas do painel Filament `app`: Dashboard, Relatórios (duas abas), Termômetro, Previsão de diário, Saldo inicial, Lançamentos, Plano de contas, Tags, Usuários, login e imagem MCP do termômetro.

## Direção

- **Produto.** Controle financeiro diário com projeção de saldo, para uma pessoa só.
- **Caráter.** Preciso, sóbrio, levemente quente.
- **Mundo.** Caderno de contas e papel milimetrado: tinta sobre papel, âmbar de bússola como acento.
- **Momentos.** A grade do termômetro (única superfície com cor por tipo) e o saldo do dia.
- **Assinatura.** A célula invertida do dia de hoje na grade.

Regra aplicada: o cromo (navegação, tabelas, botões, cabeçalhos) fica neutro; a cor fica no conteúdo — tipos no termômetro e a barra que importa em cada gráfico.

## Sinais encontrados (e corrigidos)

### Bloqueadores

1. **Tema compilado desatualizado.** `public/build/assets/theme-*.css` não tinha `grid`, `gap-4`, `sm:grid-cols-3`, `flex-col`, usados em `resources/views/filament/pages/daily-forecast-page.blade.php`. A página renderizava em `display: block`, com os cards empilhados em uma coluna de 233px. Correção: `npm run build`. Build é gitignored, então o passo precisa repetir no deploy.
2. **Títulos de página em inglês.** `InitialBalanceSettings` mostrava "Initial Balance Settings"; `DailyTransactionResource` mostrava "Daily Transactions" e "Criar daily transaction"; `AccountPlanResource` mostrava "Account Plans" e "Criar account plan".

### Tipografia e cor

3. **Rótulo em caixa alta com tracking** em `.thermometer-forecast-title` (`text-transform: uppercase; letter-spacing: .04em`).
4. **Cabeçalhos de tabela em caixa alta** nos widgets `largest-transactions-table` e `tag-breakdown-table` (`uppercase tracking-wide`).
5. **Paleta de tipos em neon**: `#eb4998` (rosa), `#83cb1c` (lima), `#7b3dec` (violeta) ao lado de `#e53131`. Cinco hues saturados repetidos em 1.800+ células.
6. **Muro de verde no saldo**: todo dia confortável (acima de R$ 2.000) pintado de `#166534` sólido, dominando a borda direita da grade.
7. **Chips fantasmas**: célula vazia desenhava um círculo cinza cheio por tipo ausente, multiplicando pontos sem informação.

### Hierarquia e layout

8. **Três cards idênticos** em Previsão de diário, com o botão "Alterar divisor" espremido sob o número.
9. **Gráfico de bolhas** para gasto por horário (`HourlySpendChart`): raio codificava contagem, tipo errado para uma distribuição, eixo quase vazio.
10. **Rosca com três hues** para participação por tipo (`TypeBreakdownChart`).
11. **Barras coloridas por tag** em `TopTagsChart` — arco-íris dentro de uma série única, com o maior valor renderizado embaixo (`y.reverse`).
12. **Barras de 60px de altura** e gráficos com até 700px de altura (aspect-ratio 1.5 do frame de largura total).
13. **Dois blocos de texto explicativo** acima da grade do termômetro, competindo com o conteúdo.

### Ruído de tabela

14. **Sete elementos coloridos por linha** em Lançamentos: badge de tipo, badge de status, badge de origem e quatro ações em texto colorido (Editar/Confirmar/Pular/Excluir).
15. **Badge "Ativa" verde em toda linha** de uma página já filtrada por "Ativas".
16. **Ações em texto** em todas as tabelas, gerando colunas de palavras laranja/azul/vermelhas.
17. **Badge "Recorrente"** azul e badge de frequência âmbar em todas as linhas.

### Copy

18. **Botão "Login"** em inglês no formulário (resto em pt-BR).
19. **Descrições técnicas** nos widgets: "Valor decrescente; empate resolvido por data e identificador."
20. **KPI contraditório**: "Variação —" com a descrição "+R$ 7.830,30 em relação ao período anterior".
21. **"(arquivada)"** dentro do badge colorido da tag.
22. **Cabeçalho `dia` / `saldos` / `total`** em minúscula ao lado de `Entradas` / `Saídas`.

## O que mudou

| Arquivo | Mudança |
| --- | --- |
| `resources/css/filament/app/theme.css` | Paleta de tipos e de saldo rebaixada para um mesmo nível de croma; extensão do tema escuro; badge virou chip só preenchido quando há valor; raio 12px em cartões, 4px em controles; cabeçalho do total com 1px; gap de 8px entre chip e valor |
| `resources/views/filament/pages/thermometer/*` | `Dia`/`Saldos`/`Total`; uma linha de legenda em vez de dois parágrafos; alerta de saldo inicial em neutro |
| `app/Filament/Pages/InitialBalanceSettings.php` + blade | Título "Saldo inicial"; formulário em `max-w-xl` |
| `app/Filament/Resources/*` | `$modelLabel`/`$pluralModelLabel` em pt-BR; sem badge de tipo/frequência/origem; status colorido só na exceção; ações como ícones; números alinhados à direita |
| `resources/views/filament/pages/daily-forecast-page.blade.php` | Um bloco de resumo com três figuras + lista, no lugar de três cards iguais |
| `app/Filament/Widgets/HourlySpendChart.php` | Bolha → barras por hora, altura máxima 260px, eixo em "R$ 8 mil" |
| `app/Filament/Widgets/TypeBreakdownChart.php` | Rosca → barras horizontais, um único acento |
| `app/Filament/Widgets/TopTagsChart.php` | Maior tag no acento, resto em neutro; ranking de cima para baixo; `maxBarThickness` 20 |
| `app/Filament/Widgets/WeekdaySpendChart.php` | Dia de pico no acento, resto em neutro |
| `app/Filament/Widgets/MonthlyEvolutionChart.php` | Linha → barras (o período padrão tem um único mês), altura máxima 280px |
| `app/Filament/Widgets/ReportKpis.php` | "Nenhum gasto no período anterior para comparar" quando não há base |
| `resources/views/mcp/thermometer-image.blade.php` | Mesma paleta da grade do app |

## Verificação

- `vendor/bin/pint --dirty`, `vendor/bin/phpstan analyse` e `php artisan test` (476 testes) passando.
- Telas conferidas em `http://app-do-bruno.test` em 1440×900, claro, com dados de um usuário temporário (removido depois).

## Pendências conhecidas

- `SpendingReport::hourly()` ainda devolve `radius`, que existia só para o gráfico de bolhas. Remover exige ajustar `tests/Feature/Services/SpendingReportTest.php` ("keeps the bubble radius between six and twenty-four pixels").
- O botão do formulário de login ainda diz "Login"; trocar exige publicar `lang/pt_BR/filament-panels.php`.
- O campo de saldo inicial mostra o valor cru (`4200`), não formatado (`4.200,00`).
