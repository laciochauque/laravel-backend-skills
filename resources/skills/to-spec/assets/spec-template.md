# Spec NNN — [Nome do Módulo]

| Campo | Valor |
|---|---|
| Estado | rascunho \| aprovada \| implementada |
| Autor | |
| Data | |
| Entidade principal | `entity` (inglês, singular) |

## 1. Objectivo

Dois a quatro parágrafos. Que problema resolve, para quem, e qual o critério de
sucesso observável.

## 2. Fora de âmbito

Lista explícita do que esta spec **não** cobre. Obrigatória.

- ...

## 3. Modelo de dados

### Tabela `entities`

| Coluna | Tipo | Nulo | Defeito | Notas |
|---|---|---|---|---|
| `id` | ulid | não | — | PK |
| ... | | | | |
| `created_at` / `updated_at` | timestamp | — | — | |
| `deleted_at` | timestamp | sim | null | soft delete |

### Índices

| Colunas | Tipo | Justificação (filtro/ordenação/FK/pesquisa que o exige) |
|---|---|---|
| `status`, `created_at` | composto | listagem por defeito |
| `name` | FULLTEXT | pesquisa `filter[search]` e opções |

### Relações

- `entity` pertence a `user` (`user_id`, FK, cascadeOnDelete)
- ...

## 4. Estados

Se a entidade tiver estados, declarar o enum e as transições permitidas.

| De | Para | Quem | Condição |
|---|---|---|---|
| `DRAFT` | `SUBMITTED` | autor | todos os campos obrigatórios preenchidos |

Transições não listadas são proibidas e devolvem 422.

## 5. Permissões

| Permissão | Cobre | Perfis com acesso |
|---|---|---|
| `entity.create` | `store` | MANAGER, ADMIN |
| `entity.read` | `index`, `show` | todos os autenticados |

## 6. Endpoints

| Método | Rota | Controller | Permissão | Resource |
|---|---|---|---|---|
| GET | `/api/v1/entities` | `EntityController@index` | `entity.read` | `EntitySummaryResource` |
| GET | `/api/v1/entities/options` | `EntityOptionsController` | autenticado | `EntityOptionsResource` |
| POST | `/api/v1/entities` | `EntityController@store` | `entity.create` | `EntityResource` |

### Filtros e ordenações da listagem

- Pesquisa: `search` (FULLTEXT sobre `name`)
- Filtros: `status` (exacto)
- Ordenações: `created_at`, `name`; por defeito `-created_at`
- Includes: `user`
- Cada um destes tem índice correspondente na secção 3.

### Opções para selects

- `label`: `name` (ou a composição, ex.: `code — name`)
- Só entram registos que se podem escolher (ex.: `active`)
- Consumido por: formulários de ...
- Protecção: só autenticação, ou as permissões de quem consome, se os nomes
  forem sensíveis

## 7. Validação

| Campo | Regras | Mensagem |
|---|---|---|
| `name` | required, string, max:255 | via `lang/pt_PT/validation.php` |

Atributos a acrescentar em `lang/pt_PT/attributes.php`: ...

## 8. Efeitos colaterais

- Jobs disparados: ...
- Notificações: ...
- Auditoria (`LogsActivity`): campos registados
- Logs: eventos registados e nível
- Media collections: ...

## 9. Casos limite e falhas

| Situação | Comportamento esperado | Código HTTP |
|---|---|---|
| Registo já eliminado (soft delete) | 404 | 404 |
| Transição de estado proibida | mensagem de negócio | 422 |

## 10. Presunções

Tudo o que foi decidido por defeito e não confirmado pelo programador.

- **[PRESUMIDO]** ...

## 11. Critérios de aceitação

Lista verificável. Cada linha deve poder virar um teste Pest.

- [ ] `GET /api/v1/entities` devolve lista paginada com `EntitySummaryResource`,
      com `meta.total` e `meta.links`
- [ ] `GET /api/v1/entities?filter[search]=Rosário Mateus` devolve os registos
      com qualquer dos dois termos, primeiro os que têm ambos
- [ ] `GET /api/v1/entities/options?search=...` devolve no máximo 100 `{id, label}`
- [ ] Utilizador sem `entity.read` recebe 403
- [ ] ...
