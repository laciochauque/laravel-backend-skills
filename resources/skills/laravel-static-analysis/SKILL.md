---
name: laravel-static-analysis
description: Larastan nível 8 neste projecto — configuração, e como resolver os erros mais comuns sem recorrer a ignore. Usar SEMPRE que o PHPStan acusar erros, quando pedem para "passar a análise estática", ao configurar o CI, ou quando é preciso anotar tipos genéricos de relações Eloquent e colecções. Usar também ao terminar um módulo, porque nenhum commit pode introduzir erros de nível 8.
---

# Análise estática — Larastan nível 8

```neon
# phpstan.neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    paths:
        - app
    level: 8
    checkModelProperties: true
```

Sem `checkMissingIterableValueType`: a opção foi removida no PHPStan 2 (o que o
Larastan 3 instala) e, se ficar no ficheiro, o PHPStan recusa arrancar. Os tipos
dos arrays passam a ser verificados — que é o que a secção "Tipos genéricos"
abaixo já pedia.

```bash
./vendor/bin/phpstan analyse
```

**Nenhum commit introduz erros de nível 8. O CI bloqueia PRs com falhas.**

## O que o nível 8 exige na prática

O nível 8 acrescenta a verificação de chamadas sobre valores possivelmente
`null`. É de longe a fonte principal de erros neste projecto.

### `null` em relações e models

```php
// ❌ $user->profile pode ser null
$name = $user->profile->name;

// ✅
$name = $user->profile?->name;

// ✅ quando null não é aceitável, falhar explicitamente
$name = $user->profile?->name ?? throw new ProfileMissingException();
```

```php
// ❌ find() devolve ?Model
$user = User::find($id);
$user->name;

// ✅
$user = User::findOrFail($id);
```

### `auth()->user()` é nullable

```php
// ❌
$id = $request->user()->id;

// ✅
$id = $request->user()?->id ?? throw new AuthenticationException();
```

Em rotas atrás de `auth:sanctum` sabemos que existe, mas o PHPStan não. O `?->`
com fallback documenta a presunção em vez de a esconder.

### Datas nullable nos Resources

```php
// ❌ se o select não trouxe created_at, isto rebenta
'created_at' => $this->created_at->toIso8601String(),

// ✅
'created_at' => $this->created_at?->toIso8601String(),
```

### Propriedades nos Resources: `@mixin`

```php
/**
 * Versão resumida — usada em listagens.
 *
 * @mixin User
 */
final class UserSummaryResource extends JsonResource
```

Sem o `@mixin`, o Larastan não liga `$this->name` à coluna do model e acusa
`property.notFound` em cada campo do `toArray()`. Com ele, os tipos vêm das
migrações — e é aí que aparece o `Carbon|null` das datas acima.

## Tipos genéricos

O nível 8 quer saber o que está dentro dos arrays e colecções.

```php
/** @return array<string, mixed> */
public function toArray(Request $request): array

/** @return list<string> */
public static function values(): array

/** @return AnonymousResourceCollection<int, UserSummaryResource> */
public function index(Request $request): AnonymousResourceCollection
```

Relações Eloquent:

```php
/** @return BelongsTo<User, $this> */
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}

/** @return HasMany<Document, $this> */
public function documents(): HasMany
{
    return $this->hasMany(Document::class);
}
```

## `checkModelProperties: true`

Esta opção faz o PHPStan validar que as propriedades acedidas nos models
existem mesmo nas migrações. Apanha erros de escrita em nomes de colunas — que
de outra forma só aparecem em runtime, e só naquela rota específica.

Exige que os models tenham `$casts` e `$fillable` coerentes com o esquema. Vale
o incómodo.

## Sobre `@phpstan-ignore`

Cada ignore é uma dívida. Antes de o usar, verificar se o erro não está a
apontar para um bug verdadeiro — no nível 8, "possivelmente null" está certo
com muito mais frequência do que parece.

Quando o ignore for mesmo a resposta certa (limitação do Larastan, código de
terceiros), usar a forma específica e comentar porquê:

```php
/** @phpstan-ignore-next-line — o Spatie Permission não tem stubs para assignRole() */
```

Nunca `ignoreErrors` genérico no `phpstan.neon` a apagar categorias inteiras de
erro. Isso não resolve o problema; esconde-o de toda a equipa de uma vez.

## Quando aumentar o nível

O nível 8 é o mínimo, não o tecto. Se a equipa quiser subir, o nível 9 acrescenta
o tratamento estrito de `mixed` — é significativamente mais exigente e vale a
pena fazê-lo por módulo antes de o impor ao projecto inteiro.
