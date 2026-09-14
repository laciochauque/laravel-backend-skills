# Checklist de revisão — API Laravel 13

Percorrer por camada. Cada item remete para a secção do CLAUDE.md que o rege.

## Migrações (secção 4)

- [ ] Foi criada uma migração `add_*` / `change_*` para uma tabela que ainda
      **não** está em produção? → bloqueante: editar a migração de origem.
- [ ] PK é `ulid('id')->primary()`, nunca `id()` auto-increment? (3.1)
- [ ] Tem `timestamps()` e `softDeletes()` onde há dados de negócio?
- [ ] Toda a coluna usada em `where`, `orderBy`, filtro ou FK tem índice? (4.4)
- [ ] Índices compostos na ordem certa para as listagens previstas?
- [ ] Colunas de texto pesquisáveis com `fullText`, e com exactamente as colunas
      do `fullTextColumns()` do model?
- [ ] Tabelas não relacionadas metidas no mesmo ficheiro? (4.3)
- [ ] Pivot nomeada por ordem alfabética do par (`role_user`)?
- [ ] `down()` existe e desfaz mesmo o `up()`?

## Consultas (secção 5)

- [ ] Alguma listagem sem `select` limitado? → bloqueante.
- [ ] Alguma listagem sem paginação? → bloqueante.
- [ ] `cursorPaginate()` ou `simplePaginate()` num endpoint? → bloqueante:
      `paginate()`.
- [ ] `per_page` sem limite inferior (só `min(..., 100)`)? → bloqueante:
      `?per_page=-1` dá erro de SQL e resposta 500 no MySQL.
- [ ] Listagem paginada sem ordem determinística (`defaultSort`)? Com `OFFSET`,
      registos repetem-se ou saltam entre páginas.
- [ ] `LIKE '%…%'` ou `AllowedFilter::partial` numa coluna de texto?
      → bloqueante: scope `search` sobre índice FULLTEXT.
- [ ] Relação acedida dentro de um ciclo sem eager loading? → N+1, bloqueante.
- [ ] `->count()` sobre uma colecção carregada em vez de `withCount`?
- [ ] `count() > 0` em vez de `exists()`?
- [ ] `get()`/`all()` sobre conjunto potencialmente grande em job ou export?
      → usar `lazyById` / `chunkById`.
- [ ] Accessor que dispara query? (5.7)
- [ ] Relação num Resource sem `whenLoaded`? → bloqueante.
- [ ] Cada `AllowedFilter` / `allowedSorts` tem índice correspondente? (5.10)

## Controllers (secção 7)

- [ ] Contém lógica de negócio ou query de escrita? → bloqueante.
- [ ] Controller CRUD tem métodos além de `index/show/store/update/destroy`?
      → deve ser controller invocável separado.
- [ ] Escrita a chamar o Model directamente em vez de passar por uma Action?
- [ ] Um select de formulário alimentado pelo `index`? → bloqueante: endpoint
      `options`.
- [ ] Endpoint de opções sem `limit(100)`, sem `search`, ou a devolver mais do
      que `{id, label}`?
- [ ] Tipos de retorno explícitos em todos os métodos?
- [ ] PHPDoc presente para o Scramble? (18)

## Resources (secção 8)

- [ ] Existem os dois — detalhe e resumo? E o de opções, se a entidade aparece
      em selects?
- [ ] O `EntityOptionsResource` devolve exactamente `id` e `label`, sem dados
      sensíveis no `label`?
- [ ] O resumo devolve mais campos do que a listagem precisa?
- [ ] Algum campo dispara query na serialização?
- [ ] Datas em `toIso8601String()`?

## Requests, DTOs e Actions (10, 11, 12)

- [ ] `messages()` inline no FormRequest? → bloqueante: vai para `lang/pt_PT/`.
- [ ] `authorize()` implementado, não a devolver `true` cego?
- [ ] DTO é `final readonly class`?
- [ ] Action com mais de uma escrita fora de `DB::transaction`? → bloqueante.
- [ ] A Action faz mais do que uma operação? → devia ser duas Actions.
- [ ] Relação carregada no fim da Action para o Resource não disparar query?

## Rotas (secção 9)

- [ ] Rota acrescentada a `api.php` em vez do ficheiro do módulo?
- [ ] Ficheiro do módulo registado no carregador central?
- [ ] Middleware de autenticação e throttle presentes?
- [ ] Rotas fixas (`export`, `options`) declaradas antes do `apiResource`?
- [ ] Protecção por permissão coerente — ou no middleware, ou na Policy, e a
      mesma escolha em todas as rotas do módulo (ver nota de coerência abaixo).

> **Nota de coerência:** o projecto tem de escolher **um** sítio para a
> autorização. Se a Policy já cobre, o `permission:` no middleware é redundante;
> se o middleware cobre, a Policy não pode ser a única barreira nalgumas rotas e
> não noutras. Misturar os dois por rota produz buracos silenciosos.

## RBAC (secção 13)

- [ ] Nomes dos casos no `match()` coincidem exactamente com os casos
      declarados? (`self::ADMIN`, não `self::Admin`)
- [ ] O `match()` cobre **todos** os casos, incluindo `ALL` e `*_ALL`?
      → senão, `UnhandledMatchError` em produção.
- [ ] Permissões novas acrescentadas ao `PermissionEnum` e ao seeder?
- [ ] Nomenclatura `entidade.accao`, entidade em inglês e singular?

## Logs

- [ ] Variáveis interpoladas na mensagem em vez de irem no array de contexto?
- [ ] `catch` que engole a excepção sem `report($e)`? Ou que relança **e**
      regista (a mesma falha duas vezes)?
- [ ] `$e->getMessage()` no contexto em vez de `'exception' => $e`?
- [ ] Senhas, tokens, OTP, dados de cartão ou NUIT/BI completos no log?
      → bloqueante.
- [ ] Model inteiro passado como contexto?

## Língua (secção 22)

- [ ] Identificadores em inglês, textos visíveis em pt-MZ pré-AO90?
- [ ] Alguma string de utilizador escrita em código em vez de `lang/pt_PT/`?
- [ ] Atributos novos acrescentados a `lang/pt_PT/attributes.php`?
- [ ] Ortografia pré-AO90 respeitada (acção, correcto, optimizar, actualizar)?
      → verificável: `python3 scripts/check-ao90.py lang app`
- [ ] Ficheiros de língua em `lang/pt_PT/`, não `lang/pt/`?
- [ ] Datas manipuladas com consciência de `APP_TIMEZONE=Africa/Maputo`
      (timestamps gravados em hora local, não UTC)?

## Código em geral (secção 22)

- [ ] `declare(strict_types=1)` em todos os ficheiros novos?
- [ ] Classes `final` por defeito?
- [ ] `$fillable` explícito, nunca `$guarded = []`?
- [ ] `app()` / `resolve()` em vez de injecção de dependências?
- [ ] Tipos de retorno em todos os métodos (exigido pelo nível 8)?

## Testes (secção 20)

- [ ] Cobre store, index, show, update, destroy — e `options`, se existir?
- [ ] Cobre o caso sem autenticação (401)?
- [ ] Cobre o caso sem permissão (403)?
- [ ] Cobre pelo menos um caso de validação falhada (422)?
- [ ] Os testes assertam estrutura de resposta, não só o código HTTP?
- [ ] Testes com `?search=` a correr com `RefreshDatabase`? → resultados falsos:
      o FULLTEXT só vê dados confirmados. Vão para `tests/Search/`, com
      `DatabaseTruncation`.
- [ ] Testes a correr em SQLite em vez de MySQL?

## Segurança

- [ ] Dados sensíveis expostos nos Resources (hashes, tokens, e-mails privados)?
- [ ] Endpoint de escrita sem rate limiting?
- [ ] Endpoint que devolve registos de outros utilizadores sem verificação de posse?
- [ ] Ficheiros aceites sem restrição de MIME type? (15)
- [ ] Segredos reais commitados no `.env` ou no `.env.example`? (23)
- [ ] SMS enviado por `Http::` directo em vez de Notification com canal `sms`?
      → o catcher não o apanha e, em desenvolvimento, sai para um telemóvel real.
- [ ] `SMS_CATCHER_ENABLED=false` explícito no `.env.example`? Painel
      `/sms-catcher` activo num servidor acessível?
