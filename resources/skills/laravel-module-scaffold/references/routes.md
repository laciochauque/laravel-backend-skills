# Rotas modulares

Nada de `routes/api.php` monolítico. Um ficheiro por área de domínio.

```
routes/
├── api.php                 ← apenas carrega os módulos
└── api/v1/
    ├── auth.php
    ├── users.php
    └── documents.php
```

## Carregador central

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    require __DIR__ . '/api/v1/auth.php';
    require __DIR__ . '/api/v1/users.php';
    require __DIR__ . '/api/v1/documents.php';
    // Acrescentar cada novo módulo aqui
});
```

Um módulo novo cujo ficheiro não seja acrescentado aqui simplesmente não existe
— e o sintoma é um 404 sem erro nenhum nos logs. É o esquecimento mais comum
nesta arquitectura.

## Ficheiro de módulo

```php
<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:auth')->group(function (): void {
    Route::post('auth/login', LoginController::class);
    Route::post('auth/register', RegisterController::class);
});

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
    Route::post('auth/logout', LogoutController::class);
});
```

```php
<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\User\UserController;
use App\Http\Controllers\Api\V1\User\UserExportController;
use App\Http\Controllers\Api\V1\User\UserOptionsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
    Route::get('users/export', UserExportController::class)
        ->middleware('permission:user.read');

    Route::get('users/options', UserOptionsController::class);

    Route::apiResource('users', UserController::class);
});
```

## Ordem das rotas

`users/export` e `users/options` têm de vir **antes** do
`apiResource('users', ...)`. Senão o `GET users/{user}` apanha `export` ou
`options` como se fosse um ULID e devolve 404.

Esta é a regra que mais tempo faz perder a quem não a conhece: a rota existe,
o controller existe, e a resposta é 404 na mesma.

## Autorização coerente

No exemplo acima, o `UserExportController` está protegido por
`permission:user.read` no middleware, enquanto o `apiResource` deixa a
autorização para as Policies invocadas pelos `authorize()` dos FormRequests.

**Isto só é aceitável se for deliberado e documentado.** O risco real é o
`index` e o `show` do `apiResource`, que não têm FormRequest próprio e por isso
não têm `authorize()` nenhum — ficam abertos a qualquer utilizador autenticado
se ninguém o notar.

Duas formas de fechar isto, escolher uma por módulo:

```php
// (a) middleware por permissão em tudo
Route::middleware('permission:user.read')->group(function (): void {
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{user}', [UserController::class, 'show']);
});
```

```php
// (b) authorizeResource no construtor do controller
public function __construct()
{
    $this->authorizeResource(User::class, 'user');
}
```

Ver a skill `laravel-rbac` para o critério de escolha.

## Autorização das opções

As opções servem os formulários de **outros** módulos: quem cria um documento
precisa de escolher o responsável sem ter `user.read`. Por isso, por defeito, o
endpoint `options` só exige autenticação (`auth:sanctum`) — e é por essa razão
que o `label` nunca leva dados sensíveis.

Quando a própria lista de nomes é sensível (cidadãos, utentes, doentes),
proteger com as permissões de quem a consome, por exemplo
`->middleware('permission:document.create|document.update')`. É uma decisão por
módulo, registada na spec.

## Constantes de cada ficheiro de módulo

- middleware de autenticação (`auth:sanctum`) e de rate limit (`throttle:api`)
- versionamento herdado do carregador — não repetir `v1` aqui
- protecção por permissão coerente com o mecanismo escolhido para o módulo
- rotas fixas (`export`, `options`, …) antes do `apiResource`
