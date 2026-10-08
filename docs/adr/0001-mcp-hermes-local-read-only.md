# ADR 0001 — Servidor MCP local somente leitura para o Hermes

## Contexto

O Cash Compass expõe as operações financeiras apenas pelo painel Filament. O
usuário quer operar as finanças por conversa com o Hermes Agent, sem expor a
aplicação na rede. O pacote oficial `laravel/mcp` já estava instalado de forma
transitiva como dependência de desenvolvimento, sem servidor de domínio.

## Decisão

- Promover `laravel/mcp` a dependência direta de execução e registrar um
  servidor local por stdio, iniciado pelo Hermes com
  `php artisan mcp:start cash-compass`. Sem endpoint HTTP e sem servidor web
  adicional.
- Vincular cada chamada a uma conta financeira fixa definida por configuração
  local (`cash_compass.mcp.account_email`). A conta precisa existir, estar ativa
  e não ser admin; caso contrário a chamada falha de forma fechada. As
  ferramentas não aceitam identidade (`user_id`) nem seleção de conta.
- Expor apenas ferramentas explícitas e finitas de consulta. Não expor SQL,
  execução arbitrária, escrita genérica em modelos ou as ferramentas do Boost.
- Reutilizar os calculadores canônicos (`BalanceCalculator`,
  `DailyForecastCalculator`) e o escopo `forUser` do trait de isolamento, sem
  depender do global scope autenticado, que fica inativo sem sessão.
- Manter uma base reutilizável (`AccountTool`, `PaginatesResults`, serializador
  de domínio) para futuras ferramentas de mutação, sem criá-las nesta entrega.

## Consequências

- O transporte stdio não expõe a rede, mas o processo local continua com acesso
  privilegiado ao projeto e ao banco. A conta fixa limita as ferramentas, não os
  privilégios do processo hospedeiro.
- A identidade é validada a cada chamada; o processo persistente não mantém
  identidade nem cálculos obsoletos entre chamadas.
- Consultas de coleções usam filtros, paginação e limites; o horizonte de
  projeção respeita o teto de 12 meses do produto.
- O Hermes instalado é configurado de forma aditiva em
  `~/.hermes/config.yaml`, preservando os demais servidores e credenciais.
