# Pesquisa por texto — FULLTEXT

## O problema com `LIKE '%...%'`

1. **Não usa índice.** O `%` inicial obriga o MySQL a ler a tabela inteira
   (`EXPLAIN` → `type: ALL`). Num campo de pesquisa, cada tecla é um varrimento
   completo de uma tabela que pode ter milhões de registos.
2. **Procura a frase inteira.** `LIKE '%Rosário Mateus%'` só encontra quem tem
   exactamente essa sequência de letras.

O `AllowedFilter::partial()` do Spatie gera precisamente este `LIKE` (com
`LOWER()` à volta da coluna), por isso também não se usa em colunas de texto.

## O comportamento pretendido

Pesquisa: **"Rosário Mateus"**

| Registo | Resultado | Porquê |
|---|---|---|
| Carlos do Rosário Mateus | ✅ primeiro | tem os dois termos |
| Carlos Agostinho do Rosário | ✅ | tem "Rosário" |
| Mateus Cuna Júnior | ✅ | tem "Mateus" |
| Ana Maria Chissano | ❌ | não tem nenhum |

- cada palavra é um termo, e basta um termo coincidir;
- quem coincide com mais termos aparece primeiro;
- cada termo é um prefixo — `Mat` encontra `Mateus`, por isso funciona enquanto
  o utilizador escreve;
- acentos e maiúsculas não contam — `rosario` encontra `Rosário`.

Comportamento verificado em MySQL 8.4 com as collations `utf8mb4_unicode_ci`
(a do Laravel por defeito) e `utf8mb4_0900_ai_ci`.

## 1. Migração: índice FULLTEXT

```php
Schema::create('users', function (Blueprint $table): void {
    $table->ulid('id')->primary();
    $table->string('name');
    // ...
    $table->fullText('name');
    // várias colunas pesquisadas em conjunto → um índice só:
    // $table->fullText(['first_name', 'last_name']);
});
```

Um `index('name')` normal não serve: um índice B-tree só ajuda um `LIKE 'x%'`
ancorado à esquerda.

## 2. Trait no model

```php
<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Pesquisa por termos sobre um índice FULLTEXT.
 *
 * "Rosário Mateus" encontra quem tenha "Rosário" OU "Mateus" em qualquer
 * posição, e ordena primeiro quem tem os dois.
 */
trait HasFullTextSearch
{
    /** Partículas dos nomes: só trariam ruído. */
    private const SEARCH_IGNORED_WORDS = ['de', 'da', 'do', 'das', 'dos', 'e'];

    /** Termos além deste número são ignorados. */
    private const SEARCH_MAX_TERMS = 5;

    /**
     * Colunas de um índice FULLTEXT da migração — exactamente as mesmas.
     *
     * @return non-empty-list<literal-string>
     */
    abstract public function fullTextColumns(): array;

    /** @param Builder<static> $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $against = self::toBooleanQuery($term);

        if ($against === null) {
            return;
        }

        // O texto do utilizador vai só no binding; o SQL é todo literal.
        $match = 'MATCH (' . implode(', ', $this->fullTextColumns()) . ') AGAINST (? IN BOOLEAN MODE)';

        $query->whereRaw($match, [$against])
            ->orderByRaw($match . ' DESC', [$against]);
    }

    /**
     * "Carlos do Rosário-Mat" → "carlos* rosário* mat*"
     *
     * Tudo o que não é letra nem algarismo separa termos. Isto elimina também
     * os operadores do modo booleano (+ - ( ) " @ ~ < > *): o utilizador não
     * os controla e, desequilibrados, dão erro de sintaxe no MySQL.
     */
    private static function toBooleanQuery(?string $term): ?string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($term ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $terms = array_filter(
            array_unique($words),
            fn (string $word): bool => mb_strlen($word) >= 2
                && ! in_array($word, self::SEARCH_IGNORED_WORDS, true),
        );

        if ($terms === []) {
            return null;
        }

        return implode(' ', array_map(
            fn (string $word): string => $word . '*',
            array_slice($terms, 0, self::SEARCH_MAX_TERMS),
        ));
    }
}
```

O que cada decisão evita:

| Decisão | Sem ela |
|---|---|
| Limpar tudo o que não é letra nem algarismo | `?search=rosário)(` ou `@2` dá `ERROR 1064` → 500; `-mateus` **exclui** os Mateus |
| Termos com menos de 2 letras ignorados | `a*` coincide com meia tabela |
| Partículas (`de`, `do`, `dos`…) ignoradas | "Carlos dos Santos" traz todos os "dos" |
| Máximo de 5 termos | uma pesquisa colada de um documento inteiro |
| O mesmo `MATCH` filtra e ordena | quem tem os dois termos misturado com quem tem um |
| Colunas como `literal-string` | o Laravel 13 exige SQL literal no `whereRaw`/`orderByRaw`, e o Larastan nível 8 recusa colunas vindas de variáveis |

Pesquisa vazia, `null` ou só com partículas não filtra nada: devolve a
listagem normal.

## 3. Model

```php
final class User extends Authenticatable
{
    use HasFullTextSearch, HasUlids, SoftDeletes;

    /** @return non-empty-list<literal-string> */
    public function fullTextColumns(): array
    {
        return ['name'];
    }
}
```

Os nomes das colunas vão sem crases, tal como aparecem no `MATCH`. Numa query
com `join` a outra tabela que também tenha `name`, qualificar aqui mesmo:
`['users.name']`.

## 4. Onde se usa

**Listagem (`index`)** — `?filter[search]=Rosário Mateus`, com um filtro Spatie
partilhado por todos os módulos:

```php
<?php

declare(strict_types=1);

namespace App\Http\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * Liga ?filter[search]= ao scope search() dos models com HasFullTextSearch.
 *
 * @implements Filter<Model>
 */
final class FullTextSearchFilter implements Filter
{
    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        // O Spatie parte o valor nas vírgulas: "Mateus, Rosário" chega como array.
        $term = is_array($value) ? implode(' ', $value) : (string) $value;

        $query->scopes(['search' => [$term]]);
    }
}
```

```php
->allowedFilters(
    AllowedFilter::custom('search', new FullTextSearchFilter()),
    // ...
)
```

**Opções de select** — `?search=Rosário`, no endpoint invocável de opções. Ver
`laravel-module-scaffold/references/controllers.md`.

Com pesquisa, a relevância ordena primeiro; o `sort` pedido (ou o
`defaultSort`) desempata.

## 5. Testes

**A base de dados dos testes é MySQL.** No SQLite, o `$table->fullText()` da
migração rebenta ("This database driver does not support fulltext index
creation.").

**Testes com `?search=` usam `DatabaseTruncation`, não `RefreshDatabase`.** O
InnoDB só actualiza o índice FULLTEXT no `COMMIT`. O `RefreshDatabase` corre cada
teste dentro de uma transacção que nunca é confirmada, por isso a pesquisa
devolve **zero** registos — e um teste do tipo "não encontra X" passa por
engano.

Ficam numa pasta própria, com suite própria:

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

```php
// tests/Search/UserSearchTest.php
it('encontra por qualquer termo e ordena primeiro quem tem os dois', function () {
    Sanctum::actingAs(User::factory()->create(['name' => 'Operador']));

    foreach (['Carlos do Rosário Mateus', 'Carlos Agostinho do Rosário', 'Mateus Cuna Júnior', 'Ana Maria Chissano'] as $name) {
        User::factory()->create(['name' => $name]);
    }

    $this->getJson('/api/v1/users/options?search=rosario mateus')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.label', 'Carlos do Rosário Mateus');
});

it('não rebenta com operadores do modo booleano', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/users/options?search=' . urlencode('rosário)( @2 -mateus'))
        ->assertOk();
});
```

## 6. Limites conhecidos

- **Palavras com menos de 3 letras não são indexadas** (`innodb_ft_min_token_size=3`
  por defeito): "Zé" não encontra "Zé Carlos". Resolve-se com
  `innodb_ft_min_token_size=2` na configuração do servidor MySQL, recriando
  depois os índices FULLTEXT. É decisão de infra-estrutura, não de código.
- **Sem tolerância a erros de escrita.** "Rozário" não encontra "Rosário". Isso
  já é Laravel Scout com Meilisearch ou Typesense — outra ordem de
  complexidade; não adoptar sem necessidade real.
- **As colunas do `MATCH` são exactamente as de um índice.** `fullTextColumns()`
  a devolver `['first_name', 'last_name']` exige
  `$table->fullText(['first_name', 'last_name'])`; dois índices separados não
  servem (erro 1191).
- **Códigos e documentos** (NUIT, BI, referência, correio electrónico) não vão
  para o FULLTEXT: pesquisam-se por igualdade, com `AllowedFilter::exact` e
  índice normal, ou por prefixo com `AllowedFilter::beginsWith`.
- **A collation da coluna tem de ignorar acentos e maiúsculas** — as do Laravel
  e do MySQL 8 por defeito (`utf8mb4_unicode_ci`, `utf8mb4_0900_ai_ci`) já o
  fazem. Uma coluna `_bin` ou `_as_cs` perde isso.
