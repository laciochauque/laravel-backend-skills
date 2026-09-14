<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Sem CLAUDE.md, o backend:install pergunta se o cria: os testes que
    // instalam o lang respondem a essa pergunta, por isso o estado é fixo.
    File::delete(base_path('CLAUDE.md'));
});

it('instala as skills no caminho configurado', function () {
    $this->artisan('backend:install', ['--no-env' => true, '--skills-only' => true])
        ->assertSuccessful();

    $path = base_path('.claude/skills');

    expect(File::isDirectory($path))->toBeTrue()
        ->and(File::exists($path . '/laravel-migrations/SKILL.md'))->toBeTrue()
        ->and(File::exists($path . '/laravel-logging/SKILL.md'))->toBeTrue()
        ->and(File::exists($path . '/grill-me/SKILL.md'))->toBeTrue();
});

it('instala os ficheiros de língua pt_PT', function () {
    $this->artisan('backend:install', ['--no-env' => true, '--lang-only' => true])
        ->expectsConfirmation('Criar o CLAUDE.md com as regras sempre activas?', 'no')
        ->assertSuccessful();

    foreach (['validation', 'auth', 'passwords', 'pagination', 'messages', 'attributes'] as $file) {
        expect(File::exists(lang_path("pt_PT/{$file}.php")))->toBeTrue();
    }
});

it('não sobrepõe ficheiros existentes sem --force', function () {
    File::ensureDirectoryExists(lang_path('pt_PT'));
    File::put(lang_path('pt_PT/messages.php'), '<?php return ["custom" => "meu"];');

    $this->artisan('backend:install', ['--no-env' => true, '--lang-only' => true])
        ->expectsConfirmation('Criar o CLAUDE.md com as regras sempre activas?', 'no')
        ->assertSuccessful();

    expect(File::get(lang_path('pt_PT/messages.php')))->toContain('meu');
});

it('recusa --skills-only e --lang-only em simultâneo', function () {
    $this->artisan('backend:install', ['--skills-only' => true, '--lang-only' => true])
        ->assertFailed();
});

it('configura os logs daily com 30 dias no .env', function () {
    $env         = base_path('.env');
    $originalEnv = File::exists($env) ? File::get($env) : null;

    File::put($env, "APP_NAME=Teste\nLOG_CHANNEL=stack\n");

    try {
        $this->artisan('backend:install', ['--lang-only' => true])
            ->expectsConfirmation('Criar o CLAUDE.md com as regras sempre activas?', 'no')
            ->expectsConfirmation('Aplicar?', 'yes')
            ->assertSuccessful();

        expect(File::get($env))
            ->toContain("LOG_CHANNEL=daily\n")
            ->toContain("LOG_DAILY_DAYS=30\n")
            ->not->toContain('LOG_CHANNEL=stack');
    } finally {
        $originalEnv === null ? File::delete($env) : File::put($env, $originalEnv);
    }
});
