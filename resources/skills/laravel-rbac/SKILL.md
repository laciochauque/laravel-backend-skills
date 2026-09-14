---
name: laravel-rbac
description: Controlo de acesso deste projecto com Spatie Permission — nomenclatura entidade.accao, RoleEnum e PermissionEnum com match() completo, policies, seeder e protecção de rotas. Usar SEMPRE que envolver permissões, perfis, roles, autorização, policies, "quem pode fazer o quê", proteger uma rota, ou criar/alterar um enum de permissões. Usar também ao acrescentar uma entidade nova, porque toda a entidade nova precisa das suas permissões registadas no enum e no seeder.
---

# RBAC — controlo de acesso

## Nomenclatura

- **Entidade:** inglês, singular, minúsculas — `user`, `document`, `payment`
- **Permissão:** `entidade.accao` — `user.create`, `payment.evaluate`
- **Wildcard Spatie:** `user.*`, `*.create`, `*.*`

## Acções disponíveis

| Acção | Métodos cobertos | Exemplo |
|---|---|---|
| `create` | `store` | `user.create` |
| `read` | `index`, `show` | `user.read` |
| `update` | `update` | `user.update` |
| `delete` | `destroy`, `cancel` | `user.delete` |
| `evaluate` | `grant`, `revoke`, `accept`, `reject` | `user.evaluate` |
| `execute` | `run`, `finalize`, `execute` | `user.execute` |

Uma acção nova só se inventa se nenhuma destas servir. Seis acções bem
aplicadas são mais legíveis que vinte específicas.

## As duas armadilhas do `match()`

Estas duas quebram em produção, não na escrita. Verificar sempre.

**1. Os nomes dos casos no `match` têm de coincidir exactamente com os
declarados.** `self::ADMIN`, não `self::Admin` — senão o PHP lança erro de
constante indefinida.

**2. O `match` tem de cobrir todos os casos**, incluindo os wildcard (`ALL`,
`USER_ALL`). Um caso em falta lança `UnhandledMatchError` só quando aquele valor
concreto aparece — tipicamente já em produção, num perfil pouco usado.

```php
enum RoleEnum: string
{
    use HasEnumHelpers;

    case ADMIN      = 'ADMIN';
    case AUDITOR    = 'AUDITOR';
    case MANAGER    = 'MANAGER';
    case CONSULTANT = 'CONSULTANT';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN      => 'Administrador',
            self::AUDITOR    => 'Auditor',
            self::MANAGER    => 'Gestor',
            self::CONSULTANT => 'Consultor',
        };
    }

    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }
}
```

```php
enum PermissionEnum: string
{
    use HasEnumHelpers;

    case ALL = '*.*';

    case USER_ALL      = 'user.*';
    case USER_CREATE   = 'user.create';
    case USER_READ     = 'user.read';
    case USER_UPDATE   = 'user.update';
    case USER_DELETE   = 'user.delete';
    case USER_EVALUATE = 'user.evaluate';

    public function label(): string
    {
        return match ($this) {
            self::ALL           => 'Acesso total',
            self::USER_ALL      => 'Gerir utentes',
            self::USER_CREATE   => 'Adicionar utente',
            self::USER_READ     => 'Visualizar utente',
            self::USER_UPDATE   => 'Actualizar utente',
            self::USER_DELETE   => 'Eliminar utente',
            self::USER_EVALUATE => 'Avaliar utente',
        };
    }
}
```

Um teste barato que apanha as duas armadilhas de uma vez:

```php
it('tem label para todos os casos do enum', function () {
    foreach (PermissionEnum::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});
```

Vale a pena ter este teste para cada enum do projecto.

## Seeder

```php
final class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionEnum::cases() as $permission) {
            Permission::firstOrCreate(['name' => $permission->value]);
        }

        $admin = Role::firstOrCreate(['name' => RoleEnum::ADMIN->value]);
        $admin->givePermissionTo(PermissionEnum::ALL->value);

        foreach ([RoleEnum::MANAGER, RoleEnum::AUDITOR, RoleEnum::CONSULTANT] as $roleEnum) {
            Role::firstOrCreate(['name' => $roleEnum->value]);
        }
    }
}
```

`firstOrCreate` e não `create` — o seeder tem de ser idempotente para poder
correr em ambientes já povoados.

## Onde colocar a autorização: escolher um sítio

Há dois mecanismos disponíveis e o projecto tem de usar **um deles de forma
consistente** por módulo:

**(a) Middleware de permissão na rota**

```php
Route::middleware(['auth:sanctum', 'permission:user.create'])->group(...);
```

Vantagem: visível no ficheiro de rotas, fácil de auditar.
Limite: só sabe da permissão, não sabe do registo concreto.

**(b) Policy invocada pelo `authorize()` do FormRequest**

```php
public function authorize(): bool
{
    return $this->user()?->can('create', User::class) ?? false;
}
```

Vantagem: consegue decidir com base no próprio registo (posse, estado).
Limite: não se vê no ficheiro de rotas.

**Regra prática:** usar a Policy como fonte de verdade sempre que a decisão
dependa do registo (o utilizador só pode editar os seus próprios documentos).
Usar o middleware para acções que dependem apenas do perfil (exportações,
relatórios globais).

O que **não** fazer é proteger metade das rotas de um módulo por middleware e a
outra metade por policy sem critério — é assim que aparecem endpoints
desprotegidos que ninguém nota durante meses. Ao criar um módulo, declarar no
ticket ou na spec qual dos dois mecanismos rege aquele módulo, e aplicar o
mesmo a todas as rotas dele.

## Checklist de entidade nova

- [ ] Casos acrescentados ao `PermissionEnum`, com `label()` para cada um
- [ ] `match()` do `label()` cobre todos os casos, incluindo os wildcard
- [ ] Seeder actualizado (corre automaticamente por percorrer `cases()`)
- [ ] Policy criada e registada
- [ ] Rotas protegidas de forma coerente com o mecanismo escolhido
- [ ] Teste de 403 para perfil sem permissão
- [ ] Teste de 401 para pedido sem autenticação
