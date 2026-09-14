---
name: laravel-migrations
description: Regras de migrações deste projecto Laravel 13 — em desenvolvimento edita-se SEMPRE a migração de origem em vez de criar ficheiros add_*/change_*, PK em ULID, soft deletes, e índices declarados desde o início, incluindo FULLTEXT nas colunas de texto pesquisáveis (nomes, designações). Usar SEMPRE que envolver criar tabela, acrescentar ou alterar coluna, mudar tipo, acrescentar índice, chave estrangeira, tabela pivot, ou correr migrate/migrate:fresh. Usar mesmo quando o pedido parece trivial ("adiciona só o campo telefone") — é exactamente aí que o hábito errado entra.
---

# Migrações

Esta skill existe porque contraria o instinto por defeito. O reflexo normal em
Laravel é `php artisan make:migration add_phone_to_users_table`. **Neste
projecto, em desenvolvimento, isso está errado.**

## Regra 1 — Em desenvolvimento, edita-se a origem

Enquanto a tabela **não estiver em produção**, qualquer coluna nova, índice ou
alteração faz-se abrindo a migração de criação e editando-a no sítio certo.

```
❌ 2026_06_22_000001_add_phone_to_users_table.php
❌ 2026_06_22_000002_change_status_on_orders_table.php
```

```php
// ✅ database/migrations/2026_03_01_000000_create_users_table.php
Schema::create('users', function (Blueprint $table): void {
    $table->ulid('id')->primary();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('phone')->nullable();   // ← acrescentada aqui, na origem
    $table->boolean('active')->default(true);
    $table->timestamps();
    $table->softDeletes();

    $table->index('active');
});
```

Depois:

```bash
php artisan migrate:fresh --seed
```

A razão é simples: um histórico de dez migrações aditivas para uma tabela que
nunca saiu do portátil de ninguém não documenta nada — só torna impossível ler
o esquema real sem abrir dez ficheiros.

## Regra 2 — Migrações aditivas só depois da produção

A partir do momento em que a tabela existe num ambiente de produção, **deixa de
ser permitido editar a migração original**. Só a partir daí se criam migrações
aditivas:

```php
// ✅ permitido apenas quando a tabela JÁ está em produção
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('phone');
        });
    }
};
```

### Como saber em que caso se está

A tabela considera-se em produção quando:

- o programador o disser explicitamente ("isto já está em produção", "a tabela
  X já foi lançada"); **ou**
- houver uma tag de release ou entrada de CHANGELOG que cubra a migração.

Sem esse sinal, **assume-se desenvolvimento e edita-se a origem**.

Na dúvida, perguntar antes de criar o ficheiro — é uma pergunta de dez segundos
que evita um ficheiro a mais no histórico ou, pior, uma migração de origem
editada depois de estar em produção.

## Regra 3 — Uma migração por tabela ou por domínio coeso

- Cada tabela tem a sua migração de criação.
- Tabelas fortemente acopladas do mesmo domínio (`orders` + `order_items`)
  podem partilhar uma migração, desde que sejam criadas e derrubadas em conjunto.
- Nunca juntar tabelas não relacionadas no mesmo ficheiro.
- Pivot tem migração própria, nomeada pelo par em ordem alfabética
  (`role_user`, não `user_role`).

```
database/migrations/
├── 2026_03_01_000000_create_users_table.php
├── 2026_03_01_000100_create_documents_table.php
├── 2026_03_01_000200_create_orders_domain_tables.php   ← orders + order_items
└── 2026_03_01_000300_create_role_user_table.php        ← pivot
```

## Regra 4 — Índices desde a origem

Toda a coluna usada em `where`, `orderBy`, num `AllowedFilter`, num
`allowedSorts` ou como chave estrangeira leva índice **na própria migração**.

```php
$table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
$table->index(['status', 'created_at']);   // índice composto para listagens
```

A ordem das colunas num índice composto importa: a coluna mais selectiva ou a
que é sempre filtrada vem primeiro; a de ordenação vem a seguir.

Isto elimina a categoria inteira dos "add index" tardios — que são quase sempre
criados depois de a listagem já estar lenta em produção.

### Texto pesquisável: `fullText`

Nomes, designações, títulos — tudo o que o utilizador pesquisa escrevendo — leva
índice FULLTEXT, não índice normal:

```php
$table->fullText('name');
$table->fullText(['first_name', 'last_name']);   // pesquisadas em conjunto: um índice só
```

A pesquisa por texto deste projecto é `MATCH ... AGAINST` sobre este índice,
nunca `LIKE '%...%'` — ver `laravel-query-optimization`,
`references/text-search.md`. As colunas do índice são exactamente as que o
model devolve em `fullTextColumns()`; um índice com outras colunas não serve.

## Esqueleto por defeito

```php
Schema::create('entities', function (Blueprint $table): void {
    $table->ulid('id')->primary();

    $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('status')->default(StatusEnum::DRAFT->value);

    $table->timestamps();
    $table->softDeletes();

    $table->index(['status', 'created_at']);
    $table->fullText('name');
});
```

O model correspondente leva sempre:

```php
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Entity extends Model
{
    use HasUlids, SoftDeletes;

    protected $keyType      = 'string';
    public    $incrementing = false;
}
```

`HasUlids`, não `HasUuids` — por coerência com a PK.

## Armadilhas

- **Soft delete e índices únicos.** Um `unique` numa coluna não conta o
  `deleted_at`: um registo eliminado por soft delete continua a bloquear o
  valor. Se o negócio precisar de reutilizar o valor, usar índice único
  composto com `deleted_at` ou tratar no nível da validação.
- **`foreignUlid` exige que a tabela referenciada já exista** — a ordem dos
  timestamps nos nomes dos ficheiros é o que garante isso.
- **FULLTEXT não existe no SQLite.** `$table->fullText()` rebenta com "This
  database driver does not support fulltext index creation.". Os testes correm
  em MySQL — ver `laravel-pest-tests`.
- **`migrate:fresh` apaga tudo.** Só em local. Nunca sugerir em ambiente
  partilhado sem o dizer claramente.
