---
name: code-review
description: Revê código numa sessão limpa, sem o contexto de quem o escreveu, contra as convenções do CLAUDE.md e a spec original. Usar quando pedem "revê este código", "code review", "faz a revisão antes do merge", "isto está bom?", quando é passado um diff, um PR ou um conjunto de ficheiros acabados de implementar. Insistir em correr esta revisão numa conversa NOVA, nunca na mesma sessão que produziu o código.
---

# code-review

Rever com olhos que não escreveram o código.

## Porquê sessão nova

Quem acaba de escrever um módulo tem na cabeça a justificação de cada decisão.
Isso é exactamente o que impede de ver o erro: o contexto preenche as lacunas
que o código deixou. Uma revisão na mesma sessão tende a confirmar o que foi
feito em vez de o testar.

Se esta skill for invocada na mesma conversa que produziu o código, dizer isso
e recomendar abrir uma sessão nova, passando apenas o diff, a spec e o
CLAUDE.md. Se o programador insistir em continuar, prosseguir — mas assinalar no
relatório que a revisão foi feita com contexto partilhado e vale menos.

## Entrada necessária

- o diff ou os ficheiros alterados;
- o `CLAUDE.md` do projecto;
- a spec e o ticket, se existirem.

Sem a spec, a revisão só consegue avaliar conformidade e correcção técnica, não
se o código faz o que era suposto. Dizê-lo explicitamente nesse caso.

## Como rever

Percorrer o checklist em `references/review-checklist.md`. Não é uma lista para
citar de volta — é para procurar. Ler cada ficheiro alterado com a pergunta:
"que problema é que isto vai causar daqui a seis meses, com um milhão de
registos na tabela e um programador novo na equipa?"

## Classificação dos achados

Cada achado leva uma etiqueta. Sem etiquetas, tudo parece igualmente urgente e
o programador ignora a lista inteira.

| Etiqueta | Significado |
|---|---|
| **Bloqueante** | Não pode entrar. Bug, falha de segurança, N+1, quebra de regra de ouro do CLAUDE.md. |
| **Importante** | Deve ser corrigido antes do merge, mas não é perigoso. |
| **Sugestão** | Melhoria opcional. O programador decide. |
| **Dúvida** | Não percebi a intenção. Pergunta, não crítica. |

## Formato do relatório

```
## Revisão — [módulo/ticket]

**Veredicto:** aprovado | aprovado com correcções | precisa de trabalho

### Bloqueantes
- `app/Http/Controllers/.../XController.php:42` — a listagem não limita colunas;
  com a tabela a crescer isto lê linhas inteiras. Ver secção 5.2 do CLAUDE.md.

### Importantes
- ...

### Sugestões
- ...

### Dúvidas
- ...

### O que está bem feito
- ...
```

A última secção não é cortesia vazia. Dizer o que está certo ensina o padrão
tão bem como apontar o que está errado, e faz com que a lista de bloqueantes
seja lida em vez de ser recebida como ataque.

## Postura

Ser directo sobre problemas e específico sobre a correcção. "Isto está mal" não
ajuda; "isto dispara uma query por cada linha da listagem, resolve-se com
`withCount`" ajuda.

Não aprovar código que quebra uma regra de ouro só porque funciona. O CLAUDE.md
existe precisamente porque código que funciona pode na mesma ser caro de manter.

Quando o código se desviar do CLAUDE.md por boa razão, dizer isso e sugerir que
a excepção fique registada — ou no ticket, ou no próprio CLAUDE.md.
