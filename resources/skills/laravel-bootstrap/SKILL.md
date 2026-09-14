---
name: laravel-bootstrap
description: Arranque de um projecto novo de API Laravel 13 com este stack — Sanctum, Spatie Permission, Activity Log, Media Library, Query Builder, Scramble, Pest 3, Larastan nível 8, pré-visualização de SMS em desenvolvimento (michal78/laravel-sms-catcher), ULID, rate limiting, logs daily com contexto, base de dados de testes e variáveis de ambiente. Usar quando criam um projecto de raiz, quando pedem "configura o projecto", "instala as dependências", ou quando é preciso preparar o esqueleto antes de construir o primeiro módulo. Não usar para acrescentar módulos a um projecto já configurado.
---

# Arranque do projecto

## Dependências

```bash
composer require laravel/sanctum
composer require spatie/laravel-permission
composer require spatie/laravel-activitylog
composer require spatie/laravel-medialibrary
composer require spatie/laravel-query-builder
composer require dedoc/scramble
```

```bash
composer require --dev pestphp/pest pestphp/pest-plugin-laravel
composer require --dev larastan/larastan
composer require --dev michal78/laravel-sms-catcher
```

Laravel 13 exige **PHP 8.3 ou superior**. Confirmar com `php -v` antes de
começar — instalar num PHP 8.2 falha na resolução de dependências, com um erro
do Composer que não diz claramente qual é o problema.

## ULID como chave primária

Todas as migrações usam `ulid('id')->primary()`. Nunca `id()` auto-increment.
Todos os models usam `HasUlids` (não `HasUuids`), com `$keyType = 'string'` e
`$incrementing = false`. Ver a skill `laravel-migrations`.

## Rate limiting

```php
// app/Providers/AppServiceProvider.php
RateLimiter::for('api', function (Request $request): Limit {
    return $request->user()
        ? Limit::perMinute(120)->by($request->user()->id)
        : Limit::perMinute(30)->by($request->ip());
});

RateLimiter::for('auth', function (Request $request): Limit {
    return Limit::perMinute(10)->by($request->ip());
});
```

O limitador `auth` cobre login, registo e reposição de senha — os endpoints que
sofrem tentativas por força bruta.

## Filas

```env
QUEUE_CONNECTION=database
```

```bash
php artisan make:queue-table && php artisan migrate
```

## Logs

```env
LOG_CHANNEL=daily
LOG_DAILY_DAYS=30
```

Um ficheiro por dia em `storage/logs/`, guardando os 30 mais recentes, e o
middleware global `AddRequestContext` a juntar `request_id`, `user_id`, IP,
método e caminho a cada linha — incluindo as dos jobs despachados pelo pedido.
Código e convenções na skill `laravel-logging`.

O `php artisan backend:install` já escreve estas duas variáveis no `.env`.

## SMS em desenvolvimento — `michal78/laravel-sms-catcher`

O equivalente ao Mailpit para SMS: apanha as notificações do canal `sms` e
mostra-as numa caixa de entrada com aspecto de telemóvel, em `/sms-catcher`.
Não precisa de base de dados — guarda num ficheiro JSON em
`storage/logs/sms-catcher.json`.

| | |
|---|---|
| Versão | 1.2.0 (Julho de 2026), licença MIT |
| Compatibilidade | Laravel 10 a 13, PHP 8.2 ou superior |
| Instalação | só `--dev`: produção instala com `composer install --no-dev` e o pacote não existe lá |

### Como funciona — e o que isso obriga

Lido no código-fonte da 1.2.0: o pacote escuta o evento `NotificationSending` e,
quando o canal é `'sms'`, grava a mensagem e devolve `false`. **Com o catcher
activo, o SMS não é enviado de verdade.** Daí quatro regras:

1. **Todo o SMS sai por uma Notification com canal `sms`.** Um Service que chama
   o gateway directamente (`Http::post(...)`) não passa pelo evento: não é
   apanhado e, em desenvolvimento, chega a um telemóvel real.
2. **O canal chama-se exactamente `sms`**, registado com `Notification::extend()`.
   Um `via()` que devolva `SmsChannel::class` não é reconhecido — o pacote
   compara com a string `'sms'`.
3. **A Notification tem `toSms()`**, a devolver o texto (uma string, ou um
   array/objecto com `body` e opcionalmente `from`). O destinatário vem de
   `routeNotificationForSms()` no model notificável.
4. **Registar o canal mesmo que em local nunca corra.** Com o catcher activo, o
   Laravel cancela o envio antes de resolver o canal: um canal por registar só
   rebenta ("Driver [sms] not supported") em staging ou em produção.

```php
// app/Providers/AppServiceProvider.php
Notification::extend('sms', fn (Application $app): SmsChannel => $app->make(SmsChannel::class));
```

```php
final class OtpCodeNotification extends Notification
{
    public function __construct(private readonly string $code) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['sms'];
    }

    public function toSms(object $notifiable): string
    {
        return __('sms.otp', ['code' => $this->code]);   // texto em lang/pt_PT/sms.php
    }
}
```

```php
// app/Models/User.php
public function routeNotificationForSms(): ?string
{
    return $this->mobile;
}
```

### Configuração

```env
# .env (local)
SMS_CATCHER_ENABLED=true

# .env.example, staging e produção
SMS_CATCHER_ENABLED=false
```

A variável fica sempre explícita porque o valor por defeito do pacote é
perigoso: liga-se com `APP_ENV=local` **ou com `APP_DEBUG=true`**. Um servidor
de testes com `APP_DEBUG=true` e as dependências de dev instaladas deixa de
enviar SMS — em silêncio — e expõe o painel.

O painel (`/sms-catcher` e `/sms-catcher/api/messages`) tem só o middleware
`web`: **não pede autenticação**, e os SMS apanhados trazem códigos OTP e
números de telemóvel. Se algum dia for preciso num servidor partilhado,
publicar a configuração (`php artisan vendor:publish --tag=sms-catcher-config`)
e acrescentar autenticação a `route.middleware`.

O ficheiro JSON é reescrito por inteiro a cada mensagem, sem bloqueio: com
vários workers de fila em simultâneo pode perder-se uma mensagem. Para
desenvolvimento, chega.

## Base de dados dos testes: MySQL

O `phpunit.xml` do Laravel vem com SQLite em memória. Neste projecto os testes
correm em MySQL: o `$table->fullText()` rebenta no SQLite, e um teste verde em
SQLite não garante nada sobre o MySQL de produção.

```xml
<!-- phpunit.xml -->
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_DATABASE" value="nome_da_api_testing"/>
<env name="SMS_CATCHER_ENABLED" value="false"/>
```

A base `nome_da_api_testing` cria-se uma vez, à mão. Ver a skill
`laravel-pest-tests` para a suite `Search`.

## Larastan

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

Sem `checkMissingIterableValueType`: o PHPStan 2, que é o que o Larastan 3
instala, removeu a opção, e com ela no ficheiro recusa arrancar ("Unexpected
item"). O projecto quer os tipos dos arrays verificados — ver
`laravel-static-analysis` —, por isso a linha simplesmente sai.

## Scramble

```php
// config/scramble.php
return [
    'api_path' => 'api',
    'info'     => ['title' => 'Nome da API', 'version' => '1.0.0'],
];
```

Disponível em `/docs/api` apenas com `APP_ENV=local`. Nunca expor a
documentação em produção sem autenticação.

## Estrutura de rotas

Criar desde o início, mesmo vazia — é mais fácil que migrar depois:

```
routes/
├── api.php                 ← apenas carrega os módulos
└── api/v1/
    ├── auth.php
    └── users.php
```

## Localização — passo obrigatório do arranque

Isto faz-se **no arranque**, antes do primeiro módulo. Um projecto que comece
com mensagens em inglês acumula strings inline por todo o lado, e depois já
ninguém as vai buscar.

### 1. Publicar os ficheiros de língua

Não traduzir de raiz. A skill `laravel-pt-mz-strings` traz um scaffold completo
e verificado em `assets/lang/pt_PT/` — 111 chaves de validação alinhadas com o
Laravel 13, mais `auth`, `passwords`, `pagination`, `messages` e `attributes`.

```bash
php artisan backend:install --lang-only
```

Sem o pacote `laciochauque/laravel-backend-skills`:

```bash
./scripts/install-lang.sh /caminho/para/o/projecto
```

Ou à mão:

```bash
mkdir -p lang/pt_PT
cp <skills>/laravel-pt-mz-strings/assets/lang/pt_PT/*.php lang/pt_PT/
```

O `php artisan lang:publish` só é preciso se quiserem personalizar o fallback
em inglês — sem ele, o Laravel usa os ficheiros internos do framework.

### 2. Configurar o `.env`

```env
APP_LOCALE=pt_PT
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=pt_PT
APP_TIMEZONE=Africa/Maputo
```

O locale é **`pt_PT`**, não `pt_MZ`: Carbon, Faker, `Number::` e os pacotes
Spatie reconhecem `pt_PT`, e o português escrito formal moçambicano segue a
norma europeia. O registo moçambicano está no vocabulário das mensagens.

Maputo é CAT (UTC+2) sem horário de Verão. Ver a skill `laravel-pt-mz-strings`
para as três consequências de definir `APP_TIMEZONE` — em especial o facto de
os timestamps passarem a ser **gravados** em hora local, e o fuso do MySQL ter
de ficar alinhado.

### 3. Carbon, Number e moeda

```php
// app/Providers/AppServiceProvider.php
use Carbon\CarbonImmutable;
use Illuminate\Support\Number;

public function boot(): void
{
    Number::useLocale('pt_PT');
    Number::useCurrency('MZN');
    CarbonImmutable::setLocale('pt');
}
```

### 4. Confirmar

```bash
php artisan config:clear
php artisan tinker --execute="echo __('validation.required', ['attribute' => 'nome']);"
# → O campo nome é obrigatório.

php artisan tinker --execute="echo now()->format('Y-m-d H:i T');"
# → hora de Maputo, com CAT
```

Se a primeira devolver a frase em inglês, a pasta está mal nomeada (`pt` em vez
de `pt_PT`) ou falta limpar a cache de configuração.

### 5. Verificação ortográfica no CI

```bash
php artisan backend:check-ao90
```

Devolve 1 se encontrar ortografia AO90. Vale a pena correr a par do PHPStan.

## Variáveis de ambiente

```env
APP_NAME="Nome da API"
APP_ENV=production
APP_DEBUG=false
APP_FOLDER_NAME=api.sdo
APP_URL="https://${APP_FOLDER_NAME}.test"

APP_LOCALE=pt_PT
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=pt_PT
APP_TIMEZONE=Africa/Maputo

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE="${APP_FOLDER_NAME}"
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database

SANCTUM_STATEFUL_DOMAINS=

LOG_CHANNEL=daily
LOG_DAILY_DAYS=30
LOG_LEVEL=error

SMS_CATCHER_ENABLED=false
```

Nunca versionar senhas reais. O `.env.example` vai com valores vazios.

## Sobre atributos PHP no Laravel 13

O Laravel 13 traz sintaxe opcional de atributos (`#[Table(...)]`,
`#[WithQueue(...)]`). Este projecto **mantém a configuração por propriedades**
para consistência com o código existente. Adoptar atributos apenas onde tragam
clareza real, e nunca a meio de um módulo — misturar os dois estilos na mesma
classe é pior que qualquer um deles sozinho.

## Verificação final

```bash
php artisan migrate:fresh --seed
php artisan test
./vendor/bin/phpstan analyse
```

Os três têm de passar antes de o primeiro módulo de negócio começar.
