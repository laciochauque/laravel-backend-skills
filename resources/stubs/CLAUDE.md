# CLAUDE.md — API Laravel 13

> Este ficheiro contém apenas as regras **sempre activas**. Os padrões
> detalhados por área vivem nas skills em `.claude/skills/`, que o Claude Code
> carrega conforme a tarefa.
>
> **Língua dos textos visíveis:** Português, ortografia pré-AO90, registo de
> Moçambique.
> **Língua do código:** Inglês.

---

## Regras de Ouro

Estas nunca se quebram:

1. **Migrações em desenvolvimento:** nunca criar migração `add_*` / `change_*`.
   Edita-se a migração original da tabela. Migrações aditivas só **depois de a
   tabela estar em produção**. → skill `laravel-migrations`
2. **Uma migração por tabela** (ou por área de domínio coesa).
3. **Controladores finos:** sem lógica de negócio, sem queries soltas. Recebem
   o request, invocam a Action, devolvem o Resource. → `laravel-module-scaffold`
4. **Consultas optimizadas:** eager loading explícito, `select` limitado,
   `withCount`, paginação completa com `paginate()`. Proibido N+1, `SELECT *`
   implícito em listagens e `LIKE '%…%'` em colunas de texto — a pesquisa usa
   FULLTEXT. → `laravel-query-optimization`
5. **Rotas por módulo:** um ficheiro por área (`routes/api/v1/*.php`), nunca um
   `api.php` monolítico.
6. **Mensagens e validações** sempre em `lang/pt_PT/` — nunca inline.
   → `laravel-pt-mz-strings`
7. **Selects alimentam-se de `/{entities}/options`** — `{id, label}`, com
   pesquisa, no máximo 100. Nunca do `index`. → `laravel-module-scaffold`

---

## Stack

| Atributo | Valor |
|---|---|
| Framework | Laravel 13 (lançado a 17/03/2026) |
| PHP | 8.3+ (mínimo exigido pelo Laravel 13) |
| Tipo | API RESTful (JSON) |
| Base de dados | MySQL 8+ |
| Autenticação | Laravel Sanctum (Bearer token) |
| Arquitectura | Actions + Services + DTOs |
| Chaves primárias | ULID |
| Paginação | `paginate()` — total e links numerados |
| Pesquisa de texto | FULLTEXT do MySQL (scope `search`) |
| Filas | Database driver |
| Logs | canal `daily`, 30 dias, contexto por pedido |
| Testes | Pest 3, em MySQL |
| Análise estática | Larastan nível 8 |
| SMS em desenvolvimento | `michal78/laravel-sms-catcher` |
| Locale | `pt_PT` |
| Fuso horário | `Africa/Maputo` |

> **Laravel 13:** não há breaking changes face ao 12; a única alteração de
> infra-estrutura é o salto para PHP 8.3. A nova sintaxe opcional de atributos
> PHP existe, mas este projecto **mantém a configuração por propriedades** para
> consistência com o código já existente.

---

## Regras de Código

| Regra | Detalhe |
|---|---|
| `declare(strict_types=1)` | Obrigatório em todos os ficheiros PHP |
| `final class` | Por defeito em todas as classes |
| `readonly` | Obrigatório em DTOs (`final readonly class`) |
| Tipos de retorno | Sempre explícitos — exigido pelo Larastan nível 8 |
| `$fillable` | Sempre explícito — nunca `$guarded = []` |
| `softDeletes()` | Em todas as tabelas com dados de negócio |
| Injecção de dependências | Constructor ou method injection — nunca `app()`/`resolve()` |
| Transacções | `DB::transaction` em operações com múltiplas escritas |
| Consultas | Eager loading, `select` limitado, `paginate()` com `per_page` entre 1 e 100 — sempre |
| Pesquisa de texto | Scope `search` sobre índice FULLTEXT — nunca `LIKE '%…%'` |
| Logs | Mensagem fixa, dados no array de contexto, `'exception' => $e`, nunca dados sensíveis → `laravel-logging` |

---

## Língua e Ortografia

| Contexto | Língua |
|---|---|
| Classes, métodos, variáveis, rotas | Inglês |
| Nomes de tabelas e colunas | Inglês, `snake_case` |
| Jobs, Events, Listeners | Inglês |
| Comentários PHPDoc e inline | pt pré-AO90, frases curtas |
| Strings JSON (`message`, `errors`) | pt, via `lang/pt_PT/` |
| Mensagens de log | pt pré-AO90, fixas |
| Labels de Enums (`label()`) | pt |
| Nomes de testes (`it('...')`) | pt |

Ortografia pré-AO90: acção, actualizar, activo, correcto, optimizar,
objectivo, directório, excepção, selecção, colecção, arquitectura, projecto,
electrónico, facto, adopção.

Verificável: `php artisan backend:check-ao90`

---

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
Rotas (módulo)        → routes/api/v1/users.php
Migração (tabela)     → ..._create_users_table.php
```

---

## Variáveis de Ambiente

```env
APP_NAME="Nome da API"
APP_ENV=production
APP_DEBUG=false
APP_URL="https://api.exemplo.co.mz"

APP_LOCALE=pt_PT
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=pt_PT
APP_TIMEZONE=Africa/Maputo

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

QUEUE_CONNECTION=database
SANCTUM_STATEFUL_DOMAINS=

LOG_CHANNEL=daily
LOG_DAILY_DAYS=30
LOG_LEVEL=error

SMS_CATCHER_ENABLED=false
```

> **Segurança:** nunca versionar senhas reais. O `.env.example` vai com valores
> vazios.
>
> **Fuso horário:** `APP_TIMEZONE` altera o fuso em que os timestamps são
> **gravados**, não só apresentados. Ver a skill `laravel-pt-mz-strings` antes
> de o mudar num projecto com dados.
>
> **SMS:** `SMS_CATCHER_ENABLED=true` só no `.env` local. Com o catcher activo,
> os SMS não são enviados. Ver a skill `laravel-bootstrap`.

---

## Portas de Qualidade

Nenhum commit passa sem estes três:

```bash
php artisan test
./vendor/bin/phpstan analyse     # sem erros nível 8
php artisan backend:check-ao90
```

---

## Skills disponíveis

| Skill | Quando dispara |
|---|---|
| `grill-me` | funcionalidade nova ainda por definir |
| `to-spec` | escrever a especificação |
| `to-tickets` | partir a spec em tarefas |
| `implement` | executar um ticket |
| `code-review` | rever código (em **sessão nova**) |
| `laravel-migrations` | tabelas, colunas, índices |
| `laravel-module-scaffold` | módulo ou endpoint novo, selects |
| `laravel-query-optimization` | listagens, filtros, pesquisa, N+1 |
| `laravel-rbac` | permissões, roles, policies |
| `laravel-pest-tests` | testes |
| `laravel-pt-mz-strings` | mensagens, traduções, ortografia |
| `laravel-queues` | jobs e filas |
| `laravel-logging` | logs, try/catch, integrações externas |
| `laravel-media` | anexos e ficheiros |
| `laravel-static-analysis` | erros de PHPStan |
| `laravel-bootstrap` | arranque de projecto novo |

`php artisan backend:skills` lista o estado de cada uma.
