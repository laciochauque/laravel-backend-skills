---
name: laravel-module-scaffold
description: Constrói um módulo completo de API nesta arquitectura Laravel 13 (Actions + Services + DTOs) pela ordem correcta — migração, model, enums, permissões, traduções, requests, DTOs, actions, resources (detalhe, resumo e opções), controller fino com paginate e pesquisa FULLTEXT, endpoint de opções para selects, rotas modulares e testes. Usar SEMPRE que pedirem um recurso, módulo, entidade ou CRUD novo ("cria o módulo de pagamentos", "preciso de endpoints para documentos"), ao acrescentar um endpoint ou controller invocável a um módulo existente, e quando um formulário precisa de um select, dropdown ou autocomplete de uma entidade. É a skill guarda-chuva da arquitectura — consultar antes de criar qualquer controller, DTO, Action ou Resource.
---

# Módulo de API — construção

A ordem abaixo é a ordem das dependências reais. Seguir por outra ordem obriga
a voltar atrás.

## Ordem de construção

1. **Migração** — tabela, ULID PK, `timestamps`, `softDeletes`, índices para
   todos os filtros/ordenações/FKs, `fullText` nas colunas de texto
   pesquisáveis. Ver skill `laravel-migrations`.
2. **Model** — `HasUlids`, `SoftDeletes`, `LogsActivity`, `$fillable` explícito,
   `HasFullTextSearch` se houver pesquisa por texto, media collections se
   aplicável.
3. **Enums** — estado da entidade + entradas no `PermissionEnum`. Ver `laravel-rbac`.
4. **Policy** — permissões `entidade.accao`.
5. **Traduções** — `lang/pt_PT/attributes.php`. Ver `laravel-pt-mz-strings`.
6. **Requests** — `StoreEntityRequest`, `UpdateEntityRequest`, sem `messages()`.
7. **DTOs** — `CreateEntityDTO`, `UpdateEntityDTO`, `final readonly`.
8. **Actions** — uma por operação, `DB::transaction` se houver várias escritas.
9. **Resources** — `EntityResource` (detalhe) + `EntitySummaryResource` (lista)
   + `EntityOptionsResource` (`{id, label}`) se a entidade aparecer em selects.
10. **Controller CRUD** — fino, `index` com `select`, `with`, `search` e `paginate`.
11. **Controllers invocáveis** — um por acção adicional, incluindo o
    `EntityOptionsController` se a entidade aparecer em selects.
12. **Rotas** — ficheiro próprio, registado no carregador central.
13. **Testes Feature** — ver `laravel-pest-tests`.
14. **Verificação** — N+1, PHPStan nível 8, cobertura.

## Estrutura de directórios

```
app/
├── Actions/User/{Create,Update,Delete}UserAction.php
├── DTOs/User/{Create,Update}UserDTO.php
├── Services/UserService.php
├── Http/
│   ├── Controllers/Api/V1/User/UserController.php          ← CRUD
│   ├── Controllers/Api/V1/User/UserExportController.php    ← invocável
│   ├── Controllers/Api/V1/User/UserOptionsController.php   ← opções de select
│   ├── Filters/FullTextSearchFilter.php                    ← partilhado
│   ├── Requests/SearchOptionsRequest.php                   ← partilhado
│   ├── Requests/User/{Store,Update}UserRequest.php
│   └── Resources/User/{UserResource,UserSummaryResource,UserOptionsResource}.php
├── Models/
│   ├── Concerns/HasFullTextSearch.php                      ← partilhado
│   └── User.php
├── Enums/  Traits/  Observers/  Policies/  Exceptions/
```

## Actions vs Services

Uma **Action** faz uma operação: criar, actualizar, exportar. Um **Service**
agrega lógica transversal de uma entidade, reutilizada por várias Actions.

Se uma Action está a fazer duas coisas, são duas Actions. Se duas Actions
partilham quinze linhas iguais, isso é um Service.

O controller nunca chama o Model directamente para escrita — passa sempre por
uma Action.

## Opções para selects

Um campo de selecção (select, dropdown, autocomplete) **nunca** se alimenta do
`index`. Tem endpoint próprio: `GET /api/v1/{entities}/options`.

| | `index` | `options` |
|---|---|---|
| Para quê | ecrã de listagem | campo de selecção num formulário |
| Resposta | `EntitySummaryResource`, paginada | `EntityOptionsResource`: só `{id, label}` |
| Quantidade | `per_page` (1 a 100) + `COUNT(*)` | no máximo 100, sem `COUNT(*)` |
| Pesquisa | `?filter[search]=` | `?search=` |
| Relações, includes | sim | não |

Ler as opções do `index` traria só a primeira página (o select mostraria 15
registos de milhares), pagaria o `COUNT(*)` e as colunas do resumo a cada tecla,
e prenderia os formulários ao formato da listagem — mudar a listagem partiria
os selects.

Controller, Resource e rota em `references/controllers.md`,
`references/resources.md` e `references/routes.md`.

## Referências detalhadas

Ler o ficheiro correspondente ao passo em que se está, não todos de uma vez:

| Ficheiro | Quando ler |
|---|---|
| `references/controllers.md` | passos 10 e 11 — CRUD, invocáveis e opções |
| `references/requests-dtos-actions.md` | passos 6, 7 e 8 |
| `references/resources.md` | passo 9 |
| `references/routes.md` | passo 12 |
| `references/responses.md` | formato das respostas, paginação e handler de excepções |

A pesquisa por texto (migração, trait, filtro, testes) está na skill
`laravel-query-optimization`, em `references/text-search.md`.

## Regras que atravessam tudo

- `declare(strict_types=1)` em todos os ficheiros.
- `final class` por defeito.
- Tipos de retorno explícitos — exigido pelo Larastan nível 8.
- Injecção por construtor ou por método, nunca `app()` / `resolve()`.
- `$fillable` explícito, nunca `$guarded = []`.
- Nenhuma string visível ao utilizador escrita no código.
- Nenhuma listagem sem `select` limitado e `paginate()`. A única excepção são
  as opções de select: limite fixo de 100.
- Nenhum `LIKE '%...%'` em colunas de texto — pesquisa pelo scope `search`.
- Nenhum select alimentado pelo `index`.

## Nomenclatura

```
Controller CRUD       → Api/V1/User/UserController.php
Controller invocável  → Api/V1/User/UserExportController.php
Controller opções     → Api/V1/User/UserOptionsController.php
Action                → CreateUserAction, ExportUsersAction
DTO                   → CreateUserDTO, UpdateUserDTO
Service               → UserService
Request               → StoreUserRequest, UpdateUserRequest
Resource (detalhe)    → UserResource
Resource (resumo)     → UserSummaryResource
Resource (opções)     → UserOptionsResource
Job                   → SendEmailNotificationJob
Enum                  → RoleEnum, PermissionEnum, StatusEnum
Rotas                 → routes/api/v1/users.php
Migração              → ..._create_users_table.php
```

## Checklist final do módulo

- [ ] Migração com índices para todos os filtros e ordenações declarados
- [ ] `fullText` nas colunas pesquisáveis, igual ao `fullTextColumns()` do model
- [ ] Model com `HasUlids`, `SoftDeletes`, `$fillable`
- [ ] Enum de estado + entradas no `PermissionEnum` com `label()` completo
- [ ] Policy criada e registada
- [ ] Atributos em `lang/pt_PT/attributes.php`
- [ ] Requests sem `messages()`
- [ ] DTOs `final readonly`
- [ ] Actions com transacção onde há múltiplas escritas
- [ ] Resources de detalhe e resumo, com `whenLoaded` em todas as relações
- [ ] `EntityOptionsResource` + `EntityOptionsController` se a entidade aparece
      em selects
- [ ] Controller fino, `index` com `select` + `search` + `paginate`, `per_page`
      entre 1 e 100
- [ ] Ficheiro de rotas próprio, registado no carregador; `options` antes do
      `apiResource`
- [ ] Testes: caminho feliz, 401, 403, 422 para cada endpoint; testes de
      pesquisa em `tests/Search/`
- [ ] PHPDoc nos controllers para o Scramble
- [ ] `php artisan test` verde
- [ ] `./vendor/bin/phpstan analyse` sem erros nível 8
