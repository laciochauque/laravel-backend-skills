# Requests, DTOs e Actions

Esta é a espinha da escrita: o Request valida, o DTO transporta, a Action executa.

## FormRequest

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

final class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'role'     => ['required', new Enum(RoleEnum::class)],
        ];
    }

    // Sem messages() — usar lang/pt_PT/validation.php e lang/pt_PT/attributes.php
}
```

`authorize()` nunca devolve `true` cego. Se a autorização daquele módulo vive no
middleware da rota, devolver `true` é uma decisão — mas tem de ser uma decisão
tomada, não esquecimento. Ver a skill `laravel-rbac`.

O `Rule::unique` numa tabela com soft deletes continua a contar registos
eliminados. Se o negócio permitir reutilizar o valor, acrescentar
`->whereNull('deleted_at')`.

## DTO

```php
<?php

declare(strict_types=1);

namespace App\DTOs\User;

use App\Enums\RoleEnum;
use App\Http\Requests\User\StoreUserRequest;

/** Dados validados para criação de utilizador. */
final readonly class CreateUserDTO
{
    public function __construct(
        public string   $name,
        public string   $email,
        public string   $password,
        public RoleEnum $role,
    ) {}

    public static function fromRequest(StoreUserRequest $request): self
    {
        return new self(
            name:     $request->validated('name'),
            email:    $request->validated('email'),
            password: $request->validated('password'),
            role:     RoleEnum::from($request->validated('role')),
        );
    }
}
```

O DTO converte para tipos ricos (enums, datas) na fronteira. A partir daqui para
dentro, a Action não lida com strings soltas.

Para `UpdateEntityDTO` com campos opcionais, o cuidado é distinguir "campo não
enviado" de "campo enviado a null". Usar `$request->has()` em vez de assumir
que `null` significa ausência, senão um PATCH parcial apaga campos por engano.

## Action

```php
<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\DTOs\User\CreateUserDTO;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Cria utilizador e atribui perfil. */
final class CreateUserAction
{
    public function execute(CreateUserDTO $dto): User
    {
        return DB::transaction(function () use ($dto): User {
            $user = User::create([
                'name'     => $dto->name,
                'email'    => $dto->email,
                'password' => Hash::make($dto->password),
            ]);

            $user->assignRole($dto->role->value);

            return $user->load('roles:id,name');
        });
    }
}
```

Três coisas a reter:

- **Mais de uma escrita → `DB::transaction`.** Criar o utilizador e falhar a
  atribuir o perfil deixaria um utilizador sem perfil na base de dados.
- **O `load` final** devolve a relação já carregada, para o Resource não
  disparar query nova.
- **Nada de efeitos externos dentro da transacção.** Enviar e-mail ou chamar uma
  API de terceiros dentro de `DB::transaction` significa que um rollback não
  desfaz o e-mail. Despachar o job depois da transacção fechar.
