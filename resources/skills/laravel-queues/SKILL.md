---
name: laravel-queues
description: Filas deste projecto com o driver database — jobs em inglês, tries e backoff, failed() com log contextual, e routing centralizado com Queue::route() do Laravel 13. Usar SEMPRE que envolver jobs, filas, processamento assíncrono, envio de notificações ou e-mails, exports pesados, relatórios em segundo plano, ou quando pedem para "mandar isto para background". Usar também quando um job falha ou fica preso.
---

# Filas

```env
QUEUE_CONNECTION=database
```

```bash
php artisan make:queue-table && php artisan migrate
php artisan queue:work --tries=3 --backoff=60
```

## Routing centralizado (Laravel 13)

O Laravel 13 permite declarar num único sítio a fila e a conexão de cada job,
com a mesma filosofia do `RateLimiter::for()`: a topologia de infra-estrutura
vive num sítio, não espalhada por vinte classes de job.

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Support\Facades\Queue;
use App\Jobs\GenerateReportJob;
use App\Jobs\SendEmailNotificationJob;

public function boot(): void
{
    Queue::route(GenerateReportJob::class, queue: 'reports');
    Queue::route(SendEmailNotificationJob::class, queue: 'notifications');
}
```

Preferir isto a `$queue` dentro da classe do job ou a `->onQueue()` espalhado
pelos controllers. A pergunta "para que fila vai este job?" deve ter uma
resposta só, e num ficheiro só.

## Estrutura do job

Nomes de classes sempre em inglês. Mensagens de log em pt-MZ, com as regras da
skill `laravel-logging`.

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class SendEmailNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60;

    public function __construct(
        private readonly User   $user,
        private readonly string $subject,
        private readonly string $body,
    ) {}

    public function handle(): void
    {
        // lógica de envio
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Falha no envio de notificação por correio electrónico', [
            'user_id'   => $this->user->id,
            'exception' => $exception,
        ]);
    }
}
```

## Regras

**Passar o model, não o array.** O `SerializesModels` guarda só a chave e
recarrega o registo fresco na execução. Passar o objecto serializado inteiro
significa trabalhar com dados velhos.

Consequência a conhecer: se o registo for eliminado entre o despacho e a
execução, o job falha com `ModelNotFoundException`. Para jobs que devem sobreviver
a isso, usar `$deleteWhenMissingModels = true`.

**Jobs têm de ser idempotentes.** Com `tries = 3`, o `handle()` pode correr três
vezes. Se ele cria um registo, é preciso garantir que não cria três — usar
`firstOrCreate` ou verificar estado antes de agir.

**Nunca despachar dentro de `DB::transaction`.** Com o driver database o job
pode ser apanhado por um worker antes do commit, e encontrar dados que ainda não
existem. Despachar depois da transacção fechar, ou usar `afterCommit()`.

**Nada de consultas grandes dentro do job sem `lazyById`/`chunkById`.** Ver a
skill `laravel-query-optimization`. Um export com `->get()` sobre um milhão de
linhas mata o worker por memória.

**`failed()` sempre implementado**, com contexto suficiente no log para
reconstituir o que falhou sem adivinhar. Passar `'exception' => $exception`, não
`$exception->getMessage()` — só o objecto traz ficheiro, linha e stack trace. O
`request_id` do pedido que despachou o job chega sozinho, pelo `Context` (ver
`laravel-logging`).

## Diagnóstico

```bash
php artisan queue:failed          # lista jobs falhados
php artisan queue:retry all       # re-tenta todos
php artisan queue:flush           # limpa a tabela de falhados
```

Em produção o worker precisa de supervisor (systemd ou Supervisor) — o
`queue:work` sozinho morre com a sessão. E depois de qualquer deploy é preciso
`php artisan queue:restart`, senão os workers antigos continuam a correr código
velho em memória.
