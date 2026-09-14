---
name: laravel-pest-tests
description: Convenções de teste deste projecto com Pest 3 — base de dados MySQL, describe aninhado por endpoint, Sanctum::actingAs, cobertura obrigatória de 401/403/422, asserção de estrutura (incluindo a paginação completa), testes do endpoint de opções e testes de pesquisa FULLTEXT com DatabaseTruncation. Usar SEMPRE que escrever, corrigir ou rever testes, quando pedem "escreve os testes", "testa este endpoint", quando um teste falha, ou ao terminar um módulo. Usar também quando alguém pergunta o que é preciso testar num endpoint novo.
---

# Testes com Pest 3

## Estrutura

```
tests/
├── Feature/Api/V1/User/UserTest.php
├── Search/UserSearchTest.php          ← testes com ?search= (DatabaseTruncation)
├── Unit/Actions/User/CreateUserActionTest.php
└── Pest.php
```

Feature testa o endpoint de ponta a ponta. Unit testa a Action isolada. A
maioria do valor está nos Feature — são os que apanham falhas de autorização,
de serialização e de rota.

## Base de dados: MySQL

```xml
<!-- phpunit.xml -->
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_DATABASE" value="nome_da_api_testing"/>
<env name="SMS_CATCHER_ENABLED" value="false"/>
```

O `phpunit.xml` do Laravel vem com SQLite em memória. Aqui não serve: o
`$table->fullText()` das migrações rebenta no SQLite, e um teste verde em SQLite
não garante nada sobre o MySQL de produção.

`SMS_CATCHER_ENABLED=false` porque, com o `APP_DEBUG=true` do `.env` local, o
catcher ficaria activo nos testes: cancelaria as notificações `sms` e escreveria
em `storage/logs/`. Os testes de notificações usam `Notification::fake()`.

## Forma

`describe` aninhado: controller no exterior, endpoint no interior. Torna a
saída do runner legível como documentação da API.

```php
<?php

use App\Enums\RoleEnum;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('UserController', function () {

    describe('GET /api/v1/users', function () {

        it('devolve lista resumida de utilizadores', function () {
            Sanctum::actingAs(User::factory()->create()->assignRole(RoleEnum::ADMIN->value));
            User::factory()->count(3)->create();

            $this->getJson('/api/v1/users')
                ->assertOk()
                ->assertJsonStructure(['data' => [['id', 'name', 'email', 'active']]]);
        });

        it('bloqueia utilizador sem autenticação', function () {
            $this->getJson('/api/v1/users')->assertUnauthorized();
        });

    });

});
```

Os nomes dos testes vão em português — são a descrição legível do
comportamento. O código continua em inglês.

## Cobertura mínima por endpoint

Nenhum endpoint fica dado como feito sem estes quatro:

| Caso | Asserção |
|---|---|
| Caminho feliz | `assertOk()` / `assertCreated()` + `assertJsonStructure` |
| Sem autenticação | `assertUnauthorized()` (401) |
| Sem permissão | `assertForbidden()` (403) |
| Dados inválidos | `assertUnprocessable()` + `assertJsonValidationErrors` |

Os três últimos são os que quase nunca se escrevem e são os que quase sempre
falham quando alguém mexe nas rotas.

## Asserção de estrutura, não só de estado

```php
// ❌ passa mesmo que o Resource devolva um objecto vazio
$this->getJson('/api/v1/users')->assertOk();

// ✅
$this->getJson('/api/v1/users')
    ->assertOk()
    ->assertJsonStructure(['data' => [['id', 'name', 'email', 'active']]]);
```

Para listagens, assertar também os invólucros da paginação:

```php
->assertJsonStructure([
    'data'  => [['id', 'name']],
    'links' => ['first', 'last', 'prev', 'next'],
    'meta'  => [
        'current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total',
        'links' => [['url', 'label', 'page', 'active']],
    ],
]);
```

## Paginação: limites do `per_page`

```php
it('mantém per_page entre 1 e 100', function () {
    Sanctum::actingAs(User::factory()->create()->assignRole(RoleEnum::ADMIN->value));

    $this->getJson('/api/v1/users?per_page=-1')->assertOk()->assertJsonPath('meta.per_page', 1);
    $this->getJson('/api/v1/users?per_page=5000')->assertOk()->assertJsonPath('meta.per_page', 100);
});
```

O `-1` não é teórico: sem limite inferior, o Query Builder descarta o `limit`
negativo e o MySQL recebe um `OFFSET` sem `LIMIT` — erro de sintaxe e resposta
500.

## Endpoint de opções

```php
describe('GET /api/v1/users/options', function () {

    it('devolve só id e label, no máximo 100', function () {
        Sanctum::actingAs(User::factory()->create());
        User::factory()->count(120)->create();

        $response = $this->getJson('/api/v1/users/options')->assertOk();

        expect($response->json('data'))->toHaveCount(100)
            ->and(array_keys($response->json('data.0')))->toBe(['id', 'label']);
    });

    it('bloqueia utilizador sem autenticação', function () {
        $this->getJson('/api/v1/users/options')->assertUnauthorized();
    });

    it('recusa pesquisa com mais de 100 caracteres', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/users/options?search=' . str_repeat('a', 101))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('search');
    });

});
```

O caso 403 só existe se o módulo proteger as opções com permissão
(`laravel-module-scaffold/references/routes.md`).

## Pesquisa por texto: `tests/Search/` com `DatabaseTruncation`

Os testes que passam `?search=` ou `?filter[search]=` **não podem** usar
`RefreshDatabase`. O InnoDB só actualiza o índice FULLTEXT no `COMMIT`; dentro
da transacção que o `RefreshDatabase` abre e nunca confirma, a pesquisa devolve
zero registos. Um teste "encontra X" falha e — pior — um teste "não encontra Y"
passa sem testar nada.

Por isso ficam em `tests/Search/`, com suite própria e `DatabaseTruncation`:

```xml
<!-- phpunit.xml -->
<testsuite name="Search">
    <directory>tests/Search</directory>
</testsuite>
```

```php
// tests/Pest.php
use Illuminate\Foundation\Testing\DatabaseTruncation;

pest()->extend(Tests\TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Search');
```

Exemplos em `laravel-query-optimization`, `references/text-search.md`.

## Testar o que a optimização promete

Se o projecto proíbe N+1, isso tem de ser verificável:

```php
it('não dispara queries extra por cada utilizador da listagem', function () {
    Sanctum::actingAs(User::factory()->create()->assignRole(RoleEnum::ADMIN->value));
    User::factory()->count(10)->create();

    DB::enableQueryLog();
    $this->getJson('/api/v1/users?include=roles')->assertOk();
    $count = count(DB::getQueryLog());

    User::factory()->count(10)->create();

    DB::flushQueryLog();
    $this->getJson('/api/v1/users?include=roles')->assertOk();

    expect(count(DB::getQueryLog()))->toBe($count);
});
```

O princípio: duplicar os dados não pode aumentar o número de queries. É a
definição operacional de "não há N+1".

## Dados de teste

Usar factories, nunca inserir com `DB::table()`. Nomes e e-mails nos testes em
contexto moçambicano (`joao@exemplo.co.mz`) — mantém coerência com o resto do
projecto e evita colisões com domínios reais.

Cada teste cria o que precisa. Testes que dependem da ordem de execução ou de
estado deixado por outro teste são a principal fonte de suites instáveis.

## Comandos

```bash
php artisan test
php artisan test --testsuite=Feature
php artisan test --testsuite=Search
php artisan test --filter=UserController
php artisan test --coverage --min=80
```

O `--min=80` faz a suite falhar abaixo de 80% de cobertura. Notar que cobertura
alta não significa testes bons: um teste que chama o endpoint e não asserta nada
conta para a cobertura na mesma. A cobertura é um chão, não um objectivo.
