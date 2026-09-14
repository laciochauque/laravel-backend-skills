---
name: laravel-query-optimization
description: Padrões obrigatórios de leitura neste projecto — eager loading explícito, select limitado, withCount, paginação completa com paginate(), pesquisa de texto por FULLTEXT (nunca LIKE '%...%'), whenLoaded, lazyById, Spatie Query Builder v7 — assumindo sempre tabelas com milhões de registos. Usar SEMPRE que escrever ou rever um método index, uma listagem, um filtro ou pesquisa por nome/texto, um autocomplete, um Resource, um relatório, um export, ou qualquer código Eloquent que leia dados. Usar também quando alguém reporta lentidão, suspeita de N+1, ou pergunta como optimizar uma consulta.
---

# Optimização de consultas

Premissa deste projecto: **qualquer tabela pode ter milhões de registos**.
Código que faz N+1, carrega colunas a mais ou devolve colecções inteiras é
tratado como bug, não como estilo.

## Eager loading explícito

```php
// ❌ N+1: uma query por cada utilizador
$users = User::all();
foreach ($users as $user) { $user->roles; }

// ✅
$users = User::with('roles')->get();

// ✅ melhor ainda — só as colunas que o Resource usa
$users = User::with(['roles:id,name'])->get();
```

Ao usar `with('relacao:col1,col2')`, incluir sempre a chave estrangeira que liga
a relação, senão o Eloquent não consegue fazer o match e a relação vem vazia.

## `select` limitado

```php
User::query()
    ->select(['id', 'name', 'email', 'active', 'created_at'])
    ->paginate(15);
```

A regra prática: a lista de colunas do `select` deve coincidir com os campos do
`EntitySummaryResource`. Se divergirem, uma das duas está errada.

## `withCount` em vez de carregar a relação

```php
// ❌ carrega todos os documentos só para os contar
$user->documents->count();

// ✅
User::withCount('documents')->find($id);   // → $user->documents_count
```

## Paginação completa — `paginate()`

Endpoints de listagem **nunca** devolvem colecções não paginadas, e paginam
sempre com `paginate()`: o cliente recebe `total`, `last_page` e os links
numerados de 1 a n.

```php
$perPage = max(1, min($request->integer('per_page', 15), 100));

return UserSummaryResource::collection(
    User::query()
        ->select(['id', 'name', 'email', 'active', 'created_at'])
        ->orderByDesc('created_at')
        ->orderByDesc('id')
        ->paginate($perPage)
        ->withQueryString()
);
```

- **`cursorPaginate()` e `simplePaginate()` não se usam em endpoints.** Uma
  forma de paginar só, um contrato só para o frontend. Formato em
  `laravel-module-scaffold/references/responses.md`.
- **`per_page` com limite inferior e superior.** `min(..., 100)` sozinho não
  chega: com `?per_page=-1`, o Query Builder descarta o `limit` negativo e o
  MySQL recebe um `OFFSET` sem `LIMIT` — erro de sintaxe e resposta 500. Noutras
  bases de dados seria a tabela inteira numa página.
- **Ordem determinística.** Com `OFFSET`, uma listagem sem ordem completa pode
  repetir ou saltar registos entre páginas. Ordenar sempre, e desempatar pela
  chave (`id`).
- **`withQueryString()`** mantém filtros e ordenação nos links de página.

O preço do `paginate()` é o `COUNT(*)` e o `OFFSET`, e ambos se pagam com
índices:

- O `COUNT(*)` corre com os mesmos `where` da listagem: os índices que servem a
  listagem servem a contagem. Um filtro sem índice paga-se duas vezes por
  pedido.
- Um `OFFSET` alto (página 5000) lê e descarta todas as linhas anteriores. Numa
  listagem de ecrã isto raramente acontece; para percorrer tudo — exports,
  jobs — usa-se `lazyById`/`chunkById`, nunca páginas.

## Pesquisa por texto — nunca `LIKE '%...%'`

```php
// ❌ varrimento completo da tabela, e só encontra a frase exacta
User::where('name', 'like', "%{$search}%");
AllowedFilter::partial('name');     // o mesmo, gerado pelo Spatie: LOWER(name) LIKE '%…%'

// ✅ índice FULLTEXT, qualquer termo, mais relevantes primeiro
User::query()->search('Rosário Mateus');
AllowedFilter::custom('search', new FullTextSearchFilter());
```

"Rosário Mateus" encontra "Carlos do Rosário Mateus" (primeiro — tem os dois
termos), "Carlos Agostinho do Rosário" e "Mateus Cuna Júnior". Acentos e
maiúsculas não contam, e cada termo funciona como prefixo, por isso serve para
pesquisa enquanto se escreve.

A implementação completa — migração, trait `HasFullTextSearch`, filtro Spatie,
testes e limites conhecidos — está em **`references/text-search.md`**. Ler antes
de implementar.

Para códigos e referências (`FAC-2026-0001`), pesquisa por prefixo:
`AllowedFilter::beginsWith('reference')` gera `reference LIKE 'FAC-2026%'`, que
usa um índice normal.

## `whenLoaded` nos Resources

```php
'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
```

Um Resource **nunca** dispara queries. Tudo o que aparece em `whenLoaded` tem de
ter sido carregado no controller via `with`/`load`. O `whenLoaded` não é uma
optimização — é a rede de segurança que faz a relação em falta desaparecer da
resposta em vez de silenciosamente disparar mil queries.

## `exists()` em vez de `count() > 0`

```php
// ❌
if (User::where('email', $email)->count() > 0) { ... }

// ✅
if (User::where('email', $email)->exists()) { ... }
```

## Grandes volumes: nunca `get()`

Em jobs, relatórios e exports:

```php
// ✅ streaming, memória constante
User::query()->lazyById(1000)->each(function (User $user): void {
    // processa um a um
});

// ✅ por lotes
User::query()->chunkById(500, function ($users): void {
    // processa o lote
});
```

Usar `chunkById` e não `chunk` quando o ciclo altera os registos — o `chunk`
usa `OFFSET` e salta linhas quando o conjunto muda debaixo dos pés.

## Accessors não fazem queries

Um accessor que consulta a base de dados dispara uma query por linha da
listagem, e o autor da listagem não tem como saber. Se o accessor precisa de
dados relacionados, esses dados vêm por eager loading e lêem-se de `whenLoaded`.

## Cache para leituras estáveis

Listas de referência — permissões, configurações, enums persistidos:

```php
$permissions = Cache::remember('permissions:all', now()->addHour(),
    fn () => Permission::pluck('name')->all());
```

Ao cachear, decidir logo como se invalida. Cache sem invalidação definida é uma
dívida com data de vencimento desconhecida.

## Query Builder do Spatie (v7)

Usar em todos os `index` com filtros ou ordenação, sempre com `select` limitado:

```php
QueryBuilder::for(User::query()->select(['id', 'name', 'email', 'active', 'created_at']))
    ->allowedFilters(
        AllowedFilter::custom('search', new FullTextSearchFilter()),
        AllowedFilter::exact('active'),
        AllowedFilter::exact('role', 'roles.name'),
    )
    ->allowedSorts('name', 'created_at')
    ->defaultSort('-created_at', '-id')
    ->allowedIncludes('roles')
    ->paginate(max(1, min($request->integer('per_page', 15), 100)))
    ->withQueryString();
```

**O `select` vai na query base, dentro do `for()`.** Chamado depois do `for()`,
funciona em runtime, mas devolve o Builder do Eloquent e o Larastan nível 8
deixa de ver o `allowedFilters()` que vem a seguir (`method.notFound`).

**Na v7, `allowedFilters`, `allowedSorts`, `allowedIncludes`, `allowedFields` e
`defaultSort` são variádicos.** Passar um array — a forma da v6 — dá
`TypeError`. Listas dinâmicas vão com `...$lista`.

**Cada `AllowedFilter` e cada `allowedSorts` exige índice correspondente na
migração de origem** — e o `search` exige índice FULLTEXT. Um filtro sem índice
é uma varredura de tabela completa exposta publicamente na API — qualquer
cliente a pode disparar.

## Como verificar

```bash
php artisan telescope:install    # exige laravel/telescope nas dependências de dev
```

O Telescope mostra o número de queries por pedido. Regra de aceitação: uma
listagem de 15 registos com duas relações deve fazer três queries, não
dezassete. Se o número de queries cresce com o número de linhas, há N+1.
