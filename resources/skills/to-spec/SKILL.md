---
name: to-spec
description: Transforma decisões já tomadas (normalmente vindas de uma sessão grill-me) numa especificação escrita e verificável para um módulo ou funcionalidade de API Laravel. Usar quando pedem "escreve a spec", "cria a especificação", "to-spec", "documenta isto antes de implementar", ou quando uma funcionalidade já foi discutida em profundidade e falta o documento. Não usar para explorar ideias ainda vagas — nesse caso usar grill-me primeiro.
---

# to-spec

Escrever a especificação a partir de decisões **já tomadas**. Uma spec não é
sítio para descobrir requisitos; é o registo do que ficou decidido.

## Antes de escrever

Verificar que as decisões existem mesmo. Se a conversa não cobriu estados,
permissões ou casos limite, parar e dizer:

> "Faltam decisões sobre X e Y. Passamos pela `grill-me` primeiro, ou prefere
> que eu proponha valores por defeito e marque-os como presumidos?"

Nunca inventar regras de negócio em silêncio. Tudo o que for presumido fica
marcado como **[PRESUMIDO]** na spec, para o revisor ver de imediato.

## Formato

Usar `assets/spec-template.md` como estrutura. Guardar em
`docs/specs/NNN-nome-do-modulo.md`, com numeração sequencial.

## Princípios

**Cada requisito é verificável.** "O sistema deve ser rápido" não é requisito.
"A listagem devolve no máximo 50 registos por página" é.

**A spec alinha-se ao CLAUDE.md, não o contrário.** As decisões arquitecturais
já estão tomadas — ULID, soft deletes, Actions, paginação completa com
`paginate()`, pesquisa de texto por FULLTEXT, selects alimentados por
`options`, pt-MZ nas strings. A spec não as repete nem as discute; assume-as e
só regista os desvios (que devem ser raros e justificados).

**Dados antes de comportamento.** Tabelas, colunas, tipos e índices primeiro.
Depois estados. Depois endpoints. Depois efeitos colaterais. Esta ordem é a
mesma da checklist da secção 24 do CLAUDE.md, e faz com que a `to-tickets`
consiga fatiar sem reordenar.

**Índices são requisito, não detalhe.** Cada filtro e ordenação listado na spec
obriga a um índice declarado na secção de dados, e cada pesquisa por texto
obriga a um índice FULLTEXT. Se a spec pede filtro por `status` e não declara
índice em `status`, a spec está incompleta.

**Selects são requisito.** Se a entidade aparece em campos de selecção de
outros módulos, a spec declara o endpoint `options`, o que compõe o `label` e
quem o pode consumir.

**Português de Moçambique (pré-AO90) no texto, inglês nos identificadores.**
Nomes de tabelas, colunas, classes e endpoints em inglês dentro do texto em
português.

## Secção obrigatória: Fora de âmbito

Toda a spec declara explicitamente o que **não** faz. É a secção que mais
discussão evita mais tarde. Se ninguém souber o que pôr aqui, é sinal de que o
âmbito ainda não está fechado.

## A seguir

Depois de escrita, mostrar a spec e perguntar se está correcta. Só depois de
aprovada sugerir a `to-tickets`.
