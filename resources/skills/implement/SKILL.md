---
name: implement
description: Executa um ticket de cada vez, do início ao fim, com testes e verificação das portas de qualidade antes de dar por concluído. Usar quando pedem "implementa o ticket X", "implement", "executa esta tarefa", "faz o NNN-XX", ou quando existe um ticket escrito e chegou a hora de o construir. Também usar quando alguém pede para implementar algo que já tem spec e tickets — pegar no próximo ticket por ordem de dependência em vez de fazer tudo de uma vez.
---

# implement

Fechar **um** ticket. Não dois. Não "já agora arranjo também aquilo".

## Ciclo

### 1. Ler o ticket e as suas dependências

Ler o ticket. Ler a secção da spec que ele referencia. Confirmar que os tickets
de que depende estão marcados como feitos. Se não estiverem, dizer qual falta e
parar — não implementar por cima de uma dependência inexistente.

### 2. Ler as convenções que se aplicam

O `CLAUDE.md` do projecto rege tudo. Além disso, consultar a skill específica
do tipo de artefacto em causa antes de escrever:

| O ticket toca em... | Consultar |
|---|---|
| migrações, colunas, índices | `laravel-migrations` |
| controller, DTO, Action, Resource, rotas, selects | `laravel-module-scaffold` |
| listagens, filtros, pesquisa por texto, N+1 | `laravel-query-optimization` |
| permissões, roles, policies | `laravel-rbac` |
| mensagens, validações, labels | `laravel-pt-mz-strings` |
| jobs e filas | `laravel-queues` |
| logs, try/catch, integrações externas | `laravel-logging` |
| anexos e media | `laravel-media` |
| testes | `laravel-pest-tests` |

Estas skills descrevem os padrões; este ciclo descreve o processo. São
complementares — ler a que se aplica, não adivinhar o padrão.

### 3. Olhar para o código existente antes de escrever

Encontrar um módulo já implementado no projecto e ler como está feito. O
CLAUDE.md diz o padrão; o código diz como a equipa o aplica na prática. Quando
os dois divergirem, seguir o CLAUDE.md e assinalar a divergência ao programador.

### 4. Implementar

Seguir a ordem dos passos do ticket. Escrever o teste junto com o
comportamento, não no fim.

Se a meio se descobrir que o ticket está errado ou incompleto — falta uma
coluna, a regra de negócio não fecha, a spec contradiz-se — **parar e dizer**.
Não resolver em silêncio alargando o âmbito. Um ticket que cresceu em segredo é
a origem de metade das revisões de código difíceis.

### 5. Portas de qualidade

Nenhum ticket fica concluído sem isto:

```bash
php artisan test                  # verde
./vendor/bin/phpstan analyse      # sem erros nível 8
```

E a verificação que não é automática — reler o diff à procura de:

- listagem sem `select` limitado ou sem `paginate()`, ou `per_page` sem limite
  inferior;
- `LIKE '%…%'` em coluna de texto, ou select alimentado pelo `index`;
- relação acedida num Resource sem `whenLoaded`;
- mensagem de texto escrita inline em vez de `lang/pt_PT/`;
- linha de log com variáveis na mensagem, ou excepção engolida sem `report()`;
- migração `add_*` criada para uma tabela que ainda não está em produção;
- escrita múltipla fora de `DB::transaction`;
- `$guarded = []` em vez de `$fillable` explícito;
- falta de `declare(strict_types=1)` ou de tipo de retorno.

### 6. Fechar

Marcar o ticket como feito. Resumir em três a cinco linhas: o que ficou feito,
que ficheiros mudaram, e o que ficou por decidir (se algo ficou).

Perguntar se se avança para o ticket seguinte. Não avançar por iniciativa
própria — o programador pode querer rever primeiro.

## O que nunca fazer aqui

**Não rever o próprio trabalho como se fosse revisão de código.** Quem acabou
de escrever o código tem o contexto todo na cabeça e por isso não vê o que
falta. A revisão faz-se noutra sessão, com a skill `code-review`.

**Não fazer commit de trabalho que não passa as portas de qualidade**, nem com
a intenção de "arranjar no ticket seguinte".
