# Resources

Cada entidade tem **dois**, e um terceiro quando aparece em selects.

| Ficheiro | Usado em | Campos |
|---|---|---|
| `EntityResource` | `show`, `store`, `update` | todos, relações, media |
| `EntitySummaryResource` | `index` | só o essencial da listagem |
| `EntityOptionsResource` | `options`; relações noutros Resources | `id` e `label` |

A separação existe porque a listagem e o detalhe têm custos diferentes. Um
Resource único obriga a escolher entre carregar tudo em todas as linhas da
listagem, ou devolver menos do que o detalhe precisa.

## Resumo

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\User;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Versão resumida — usada em listagens.
 *
 * @mixin User
 */
final class UserSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'active'     => $this->active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

Os campos deste Resource têm de coincidir com o `select` do `index`. Se o
Resource pedir um campo que o `select` não trouxe, o valor vem a `null` em
silêncio — ou, pior, o Eloquent recarrega o model inteiro.

## Detalhe

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\User;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Versão completa — usada no detalhe.
 *
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'email'       => $this->email,
            'active'      => $this->active,
            'roles'       => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'permissions' => $this->whenLoaded(
                'permissions',
                fn () => $this->getAllPermissions()->pluck('name'),
            ),
            'avatar'      => $this->whenLoaded('media', fn () => $this->getFirstMediaUrl('avatar')),
            'created_at'  => $this->created_at?->toIso8601String(),
            'updated_at'  => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

## Opções

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\User;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Entidade reduzida a {id, label} — para campos de selecção.
 *
 * @mixin User
 */
final class UserOptionsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'    => $this->id,
            'label' => $this->name,
        ];
    }
}
```

- **Sempre estas duas chaves, com estes nomes**, em todas as entidades: o
  componente de select do frontend é um só.
- O `label` pode compor colunas (`"{$this->code} — {$this->name}"`), desde que
  o `select` do controller as traga todas.
- Nada sensível no `label`: por defeito, as opções chegam a qualquer utilizador
  autenticado (ver `routes.md`).

### Valor já escolhido num formulário de edição

Ao editar um documento, o select do responsável tem de mostrar o nome de quem
já está escolhido — que pode não estar entre as 100 opções devolvidas. Por isso
o Resource de detalhe de quem **referencia** a entidade devolve a relação no
mesmo formato `{id, label}`:

```php
// DocumentResource
'owner' => UserOptionsResource::make($this->whenLoaded('owner')),
```

```php
// DocumentController@show
return DocumentResource::make($document->load(['owner:id,name']));
```

O frontend usa esse objecto como opção inicial do select, sem pedido extra. Uma
relação vazia (`owner_id` a `null`) sai como `"owner": null`.

## Regras

- **O Resource nunca dispara queries.** Toda a relação vai dentro de
  `whenLoaded`, e o carregamento faz-se no controller com `with`/`load`.
- **`@mixin` do model em todos os Resources.** É o que diz ao Larastan que
  `$this->name` é a coluna do model; sem ele, o nível 8 acusa
  `property.notFound` em cada campo do `toArray()`.
- **Datas em `toIso8601String()`, sempre com `?->`.** Formato único, sem
  ambiguidade de fuso. As colunas de `timestamps()` são nullable e o `select`
  pode não as trazer; sem o `?->`, o Larastan nível 8 recusa a chamada.
- **Nada de dados sensíveis.** Hashes de senha, tokens, colunas internas de
  auditoria não saem na resposta.
