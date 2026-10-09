# Issue tracker: GitHub

Issues e specs deste projeto vivem em `enzofagundz/cash-compass`.
Use o CLI `gh` dentro do repositório.

## Operações

- Criar: `gh issue create --title "..." --body-file <arquivo>`
- Ler: `gh issue view <numero> --comments`
- Listar: `gh issue list --state open`
- Comentar: `gh issue comment <numero> --body-file <arquivo>`
- Aplicar labels: `gh issue edit <numero> --add-label "..."`
- Remover labels: `gh issue edit <numero> --remove-label "..."`
- Fechar: `gh issue close <numero> --comment "..."`

Para corpos multilinha enviados pelo shell, use heredoc com delimitador
entre aspas simples (`<<'EOF'`) para impedir expansão de comandos.

“Publicar no issue tracker” significa criar uma GitHub Issue.
“Buscar o ticket relevante” significa ler a issue e seus comentários.

## Pull requests como superfície de triagem

PRs as a request surface: no.

## Dependências entre tickets

Use dependências nativas do GitHub. Se indisponíveis, registre
`Blocked by: #<numero>` no corpo do ticket.

## Vínculo com a sessão

Ao trabalhar em uma issue ou PR, vincule sua URL à sessão com
`openchamber`, action `session.link`, assim que identificar o item.
Faça o mesmo para itens abertos ou resolvidos durante o trabalho.
Menções incidentais não exigem vínculo.
