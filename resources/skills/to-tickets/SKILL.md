---
name: to-tickets
description: Parte uma especificação aprovada em tickets pequenos, ordenados por dependência e implementáveis um de cada vez. Usar quando pedem "divide em tarefas", "cria os tickets", "to-tickets", "parte isto em bocados", ou quando existe uma spec pronta e é preciso começar a implementar. Também usar quando um pedido de implementação é grande demais para uma sessão só.
---

# to-tickets

Partir a spec em unidades de trabalho que um programador (ou o `implement`)
consegue fechar sem abrir o âmbito.

## Regra de tamanho

Um ticket é pequeno o suficiente se:

- toca em três a seis ficheiros;
- tem um critério de aceitação verificável por teste;
- pode ser revisto sem ler a spec inteira;
- não deixa o `main` partido se for o único a entrar.

Se um ticket precisa de mais de um parágrafo para explicar o que faz, é dois
tickets.

## Ordem: seguir a checklist do CLAUDE.md

A secção 24 do CLAUDE.md já define a ordem correcta de construção. Os tickets
herdam essa ordem, porque é a ordem das dependências reais:

1. Migração (tabela + índices)
2. Model + Enums
3. Permissões (`PermissionEnum`) + Policy + seeder
4. Traduções (`lang/pt_PT/attributes.php`)
5. Requests + DTOs
6. Actions
7. Resources (detalhe + resumo)
8. Controller CRUD + rotas
9. Controllers invocáveis (acções adicionais)
10. Testes Feature
11. Verificação final (N+1, PHPStan, coverage)

Isto não obriga a um ticket por linha. Passos pequenos e acoplados agrupam-se
(ex.: Requests + DTOs num só). Passos grandes dividem-se (ex.: um ticket por
controller invocável).

**Os testes não ficam todos para o fim.** Cada ticket que produz comportamento
observável inclui os seus próprios testes no critério de aceitação. O ticket 10
cobre apenas o que sobrou: autorização cruzada, casos limite e integração.

## Formato

Usar `assets/ticket-template.md`. Guardar em `docs/tickets/NNN-XX-titulo.md`,
onde `NNN` é o número da spec e `XX` a ordem dentro dela.

Produzir também um índice em `docs/tickets/NNN-INDEX.md` com a tabela de todos
os tickets, dependências e estado.

## Dependências explícitas

Cada ticket declara de que tickets depende. Sem isto, o `implement` pode pegar
no ticket 7 antes do 2 e encontrar um model que ainda não existe.

Marcar também os que podem correr em paralelo — em equipa isso importa.

## O que não fazer

**Não copiar a spec para dentro dos tickets.** O ticket aponta para a secção da
spec. Duplicar significa que uma alteração à spec deixa dez tickets
desactualizados em silêncio.

**Não criar tickets de "refactorizar depois".** Ou o trabalho é necessário
agora e entra num ticket real, ou não é e não existe.

**Não deixar um ticket sem critério de aceitação.** Um ticket sem critério é
uma conversa, não uma tarefa.
