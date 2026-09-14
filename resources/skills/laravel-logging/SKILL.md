---
name: laravel-logging
description: Logs deste projecto — canal daily com retenção de 30 dias, contexto automático por pedido (request_id, user_id, ip, método, caminho) através do Context do Laravel, que segue também para os jobs, e convenções de escrita (mensagem fixa em pt, dados no array de contexto, report() para excepções, nunca dados sensíveis). Usar SEMPRE que escrever Log::, logger() ou report(), um bloco try/catch, o failed() de um job, uma integração com serviço externo (gateway de pagamento, SMS, API de terceiros), ou quando pedem para "registar no log", configurar os logs ou investigar um erro a partir dos logs.
---

# Logs

## Configuração: `daily`, 30 dias

```env
LOG_CHANNEL=daily
LOG_DAILY_DAYS=30
LOG_LEVEL=error
```

O canal `daily` escreve **um ficheiro por dia**. Tudo o que acontece num dia vai
para o mesmo ficheiro; à meia-noite abre-se o seguinte:

```
storage/logs/
├── laravel-2026-09-12.log
├── laravel-2026-09-13.log
└── laravel-2026-09-14.log   ← hoje
```

Na primeira escrita de cada dia novo, o Monolog apaga os ficheiros que passam
dos 30 mais recentes. Dias sem nenhuma linha de log não criam ficheiro, por isso
"30 ficheiros" pode cobrir mais de 30 dias de calendário.

Basta a variável de ambiente. No Laravel 13 o canal `daily` do
`config/logging.php` lê-a para a chave `max_files`; a chave antiga `days`
continua a ser aceite, por isso um `config/logging.php` já publicado funciona
na mesma.

`LOG_LEVEL` é o chão: com `error` (valor do projecto em produção), as linhas
`info` e `warning` **não são escritas**. Baixar para `warning` ou `info` é uma
decisão consciente — mais rasto, mais disco. Em local, `LOG_LEVEL=debug`.

Uma linha tem este aspecto:

```
[2026-09-14 10:32:11] production.ERROR: Falha ao cobrar factura no gateway {"invoice_id":"01J8ZQ...","gateway":"mpesa","status_code":502} {"request_id":"01J8ZQ4M5V...","user_id":"01H2K...","ip":"41.220.1.10","method":"POST","path":"api/v1/invoices/01J8ZQ.../pay"}
```

O primeiro JSON é o contexto passado na chamada. O segundo é o contexto do
pedido — vem sozinho, pelo middleware abaixo.

## Contexto automático por pedido

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** Junta a todos os logs do pedido — e dos jobs que ele despachar — quem, onde e que pedido. */
final class AddRequestContext
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->requestId($request);

        Context::add([
            'request_id' => $requestId,
            'user_id'    => $request->user('sanctum')?->getAuthIdentifier(),
            'ip'         => $request->ip(),
            'method'     => $request->method(),
            'path'       => $request->path(),
        ]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    /** Aceita o id de quem chama (frontend, proxy) só se tiver formato seguro. */
    private function requestId(Request $request): string
    {
        $incoming = (string) $request->headers->get('X-Request-Id');

        return preg_match('/^[A-Za-z0-9._-]{8,64}$/', $incoming) === 1
            ? $incoming
            : (string) Str::ulid();
    }
}
```

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->append(AddRequestContext::class);
})
```

- **Global, não no grupo `api`.** Assim até um 404 de rota inexistente ou um
  429 do throttle sai com `request_id`.
- **`$request->user('sanctum')`** resolve o utilizador pelo Bearer token antes
  de o `auth:sanctum` correr. O guard guarda o resultado, e o `auth:sanctum` a
  seguir não repete a query.
- **O `X-Request-Id` volta na resposta.** Quando alguém reporta um erro, o
  frontend mostra ou envia este id, e o pedido encontra-se com um `grep`.
- **O contexto segue para os jobs.** Um job despachado durante o pedido leva o
  `Context` no payload e repõe-no quando executa: os logs do worker saem com o
  mesmo `request_id` do pedido que o originou.
- **`Context::addHidden()`** para o que os jobs precisam de saber mas não deve
  ir parar ao log.

## Escrever uma linha de log

```php
// ❌ variáveis na mensagem — cada linha é uma frase diferente, impossível agrupar
Log::error("Falha ao cobrar a factura {$invoice->id}: {$e->getMessage()}");

// ✅ mensagem fixa; os dados vão no contexto
Log::error('Falha ao cobrar factura no gateway', [
    'invoice_id'  => $invoice->id,
    'gateway'     => 'mpesa',
    'status_code' => $response->status(),
]);
```

1. **Mensagem fixa**, curta, em pt pré-AO90. A mesma ocorrência produz sempre o
   mesmo texto, e `grep "Falha ao cobrar factura"` encontra-as todas. Nada de
   interpolação nem de placeholders `{chave}`.
2. **Dados no array**, chaves em inglês `snake_case`, identificadores (`*_id`)
   em vez de objectos. Nunca `'user' => $user`: serializa o model inteiro,
   incluindo colunas sensíveis.
3. **Excepções com o objecto, não com a mensagem.** `'exception' => $e` sai com
   classe, ficheiro, linha e stack trace; `'error' => $e->getMessage()` perde
   tudo isso.
4. **Apanhar e engolir → `report($e)`. Apanhar e relançar → não registar**, o
   handler global já o faz — senão a mesma falha aparece duas vezes.

```php
try {
    $gateway->send($phone, $text);
} catch (SmsGatewayException $e) {
    report($e);      // stack trace + contexto do pedido

    return false;    // o fluxo continua sem SMS
}
```

Excepções de domínio podem trazer o seu próprio contexto, que o `report()`
junta à linha:

```php
final class PaymentDeclinedException extends RuntimeException
{
    public function __construct(private readonly string $invoiceId, private readonly string $gatewayCode)
    {
        parent::__construct('Pagamento recusado pelo gateway');
    }

    /** @return array<string, string> */
    public function context(): array
    {
        return ['invoice_id' => $this->invoiceId, 'gateway_code' => $this->gatewayCode];
    }
}
```

## Níveis

| Nível | Quando | Exemplo |
|---|---|---|
| `debug` | diagnóstico em local; nunca fica ligado em produção | payload trocado com um gateway em teste |
| `info` | evento de negócio que vale a pena reconstituir | pagamento confirmado, conta activada |
| `warning` | anomalia recuperada — o sistema seguiu | nova tentativa ao gateway, SMS reenviado |
| `error` | falha que precisa de alguém | job falhado após todas as tentativas |
| `critical` | serviço ou dependência em baixo | base de dados inacessível, certificado expirado |

## O que nunca vai para o log

Os logs ficam 30 dias e são lidos por mais gente do que a base de dados.

- senhas, tokens, chaves de API, o cabeçalho `Authorization`;
- códigos OTP — e por isso o texto dos SMS que os contêm;
- dados de cartão;
- documentos de identificação completos (NUIT, BI, NUIB). Se for mesmo preciso,
  mascarar: `'nuit' => Str::mask($nuit, '*', 0, -3)` → `******789`.

## Integrações externas

Registar o que permite reconstituir a chamada sem expor dados: fornecedor,
código HTTP, referência devolvida pelo fornecedor. Não o corpo completo do
pedido nem da resposta.

```php
Log::warning('Gateway de SMS devolveu erro', [
    'provider'     => 'movitel',
    'status_code'  => $response->status(),
    'provider_ref' => $response->json('message_id'),
]);
```

## Volumes grandes

Nunca uma linha por registo num export ou num job que percorre um milhão de
linhas. Uma linha de resumo no fim: `['processed' => $n, 'failed' => $m]`.

## Jobs

O `failed()` é obrigatório (ver `laravel-queues`) e segue as mesmas regras. O
`request_id` do pedido de origem vem pelo `Context`, sem nada a acrescentar:

```php
public function failed(\Throwable $exception): void
{
    Log::error('Falha no envio de notificação por correio electrónico', [
        'user_id'   => $this->user->id,
        'exception' => $exception,
    ]);
}
```

## Ler os logs

```bash
# tudo o que um pedido fez, incluindo os jobs que despachou
grep '01J8ZQ4M5V' storage/logs/laravel-*.log

# o dia de hoje, em directo
tail -f storage/logs/laravel-$(date +%F).log
```

O `php artisan pail` não funciona no Windows (exige a extensão `pcntl`). Em
Laragon, usar `tail -f` no Git Bash.

## Testes

```php
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;

it('regista a falha do gateway com contexto', function () {
    Log::spy();
    // ... provocar a falha

    Log::shouldHaveReceived('error')->withArgs(
        fn (string $message, array $context): bool => $message === 'Falha ao cobrar factura no gateway'
            && $context['invoice_id'] === $invoice->id,
    );
});

it('reporta a excepção do gateway sem interromper o fluxo', function () {
    Exceptions::fake();
    // ... provocar a falha

    Exceptions::assertReported(SmsGatewayException::class);
});
```

## Checklist

- [ ] `LOG_CHANNEL=daily` e `LOG_DAILY_DAYS=30` no `.env` e no `.env.example`
- [ ] `AddRequestContext` registado como middleware global
- [ ] Mensagens fixas, dados no array de contexto
- [ ] `'exception' => $e` ou `report($e)` — nunca só `$e->getMessage()`
- [ ] Nenhum dado sensível, nenhum model inteiro no contexto
- [ ] `failed()` em todos os jobs, com os ids necessários para reconstituir
