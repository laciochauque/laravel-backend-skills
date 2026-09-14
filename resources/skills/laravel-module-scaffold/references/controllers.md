# Controllers

## Regras

1. Um controller de entidade tem **apenas** `index`, `show`, `store`, `update`,
   `destroy`.
2. Caminho: `app/Http/Controllers/Api/V1/{Entity}/{Entity}Controller.php`
3. Qualquer acção adicional é um **controller invocável** separado:
   `Api/V1/{Entity}/{Entity}{Action}Controller.php` — incluindo as opções de
   select, `{Entity}OptionsController`.
4. Zero lógica de negócio. Zero queries de escrita.

A razão da regra 3: um controller com `index`, `store`, `export`, `activate`,
`archive` e `duplicate` deixa de ter um nome honesto. Um ficheiro por acção é
mais fácil de encontrar, de proteger com permissão própria e de testar.

## CRUD

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Actions\User\CreateUserAction;
use App\Actions\User\DeleteUserAction;
use App\Actions\User\UpdateUserAction;
use App\DTOs\User\CreateUserDTO;
use App\DTOs\User\UpdateUserDTO;
use App\Http\Controllers\Controller;
use App\Http\Filters\FullTextSearchFilter;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\User\UserResource;
use App\Http\Resources\User\UserSummaryResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class UserController extends Controller
{
    /**
     * Lista utilizadores.
     *
     * @return AnonymousResourceCollection<int, UserSummaryResource>
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = QueryBuilder::for(User::query()->select(['id', 'name', 'email', 'active', 'created_at']))
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

        return UserSummaryResource::collection($users);
    }

    /** Detalhe completo de um utilizador. */
    public function show(User $user): UserResource
    {
        return UserResource::make($user->load(['roles:id,name', 'media']));
    }

    /** Cria um utilizador. */
    public function store(StoreUserRequest $request, CreateUserAction $action): JsonResponse
    {
        $user = $action->execute(CreateUserDTO::fromRequest($request));

        return UserResource::make($user)->response()->setStatusCode(201);
    }

    /** Actualiza um utilizador. */
    public function update(
        UpdateUserRequest $request,
        User $user,
        UpdateUserAction $action,
    ): UserResource {
        return UserResource::make(
            $action->execute($user, UpdateUserDTO::fromRequest($request)),
        );
    }

    /** Remove um utilizador. */
    public function destroy(User $user, DeleteUserAction $action): JsonResponse
    {
        $action->execute($user);

        return response()->json(['message' => __('messages.deleted')]);
    }
}
```

Notas sobre este exemplo:

- **`paginate()`, nunca `cursorPaginate()`.** O cliente recebe `total` e os
  links numerados — formato em `responses.md`.
- **`max(1, min(..., 100))` no `per_page`.** O `min` impede que um cliente peça
  cem mil registos; o `max` impede o `-1`: o Query Builder descarta um `limit`
  negativo e o MySQL recebe um `OFFSET` sem `LIMIT` — erro de sintaxe, resposta
  500.
- **`defaultSort` obrigatório.** Com `OFFSET`, uma listagem sem ordem
  determinística repete ou salta registos entre páginas. O `-id` desempata
  registos criados no mesmo segundo.
- **O `select` vai na query base, dentro do `for()`.** Chamado depois
  (`QueryBuilder::for(User::class)->select(...)`), funciona em runtime, mas
  devolve o Builder do Eloquent e o Larastan deixa de ver o `allowedFilters()`
  — erro no nível 8.
- **`filter[search]`** é a pesquisa FULLTEXT (`laravel-query-optimization`,
  `references/text-search.md`). Nunca `AllowedFilter::partial`, que gera
  `LOWER(coluna) LIKE '%…%'`.
- **Spatie Query Builder v7: argumentos separados, não arrays.**
  `allowedFilters`, `allowedSorts`, `allowedIncludes` e `defaultSort` são
  variádicos; um array — a forma da v6 — dá `TypeError`. Listas dinâmicas vão
  com `...$lista`.
- **`withQueryString()`** mantém filtros e ordenação nos links de página.
- O `load()` no `show` carrega exactamente o que o `UserResource` expõe via
  `whenLoaded` — se o Resource ganhar um campo novo, esta linha tem de
  acompanhar.
- O PHPDoc no `index` é o que o Scramble usa para gerar a documentação OpenAPI.

## Invocável

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Actions\User\ExportUsersAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Exporta utilizadores para ficheiro. */
final class UserExportController extends Controller
{
    public function __invoke(Request $request, ExportUsersAction $action): JsonResponse
    {
        $url = $action->execute($request->user());

        return response()->json(['data' => ['url' => $url]]);
    }
}
```

## Opções para selects

Um Request partilhado por todos os endpoints de opções:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Pedido partilhado pelos endpoints de opções de selecção. */
final class SearchOptionsRequest extends FormRequest
{
    // A autorização das opções vive no middleware da rota (routes.md).
    // Devolver true aqui é essa decisão, não esquecimento.
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function term(): ?string
    {
        $search = $this->validated('search');

        return is_string($search) ? $search : null;
    }
}
```

E um controller invocável por entidade:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchOptionsRequest;
use App\Http\Resources\User\UserOptionsResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Utilizadores para campos de selecção: {id, label}, no máximo 100. */
final class UserOptionsController extends Controller
{
    /** @return AnonymousResourceCollection<int, UserOptionsResource> */
    public function __invoke(SearchOptionsRequest $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->select(['id', 'name'])
            ->where('active', true)
            ->search($request->term())
            ->orderBy('name')
            ->limit(100)
            ->get();

        return UserOptionsResource::collection($users);
    }
}
```

- **Uma query, sem paginação e sem `COUNT(*)`.** O `limit(100)` é o tecto fixo.
- **`select` só com as colunas do `label`.**
- **Só o que se pode escolher:** `where('active', true)`. Os eliminados por soft
  delete já ficam de fora pelo scope global.
- **Sem pesquisa**, os primeiros 100 por ordem alfabética. **Com pesquisa**, os
  mais relevantes primeiro, depois por ordem alfabética.
- **Frontend:** pedir com debounce (cerca de 300 ms). Cada tecla é um pedido, e
  o `throttle:api` conta-os.
- **O valor já escolhido** num formulário de edição não vem daqui: vem do
  Resource de detalhe de quem referencia a entidade — ver `resources.md`.
