<?php

declare(strict_types=1);

namespace Laciochauque\BackendSkills;

use Illuminate\Support\ServiceProvider;
use Laciochauque\BackendSkills\Commands\CheckAo90Command;
use Laciochauque\BackendSkills\Commands\InstallCommand;
use Laciochauque\BackendSkills\Commands\SkillsListCommand;

final class BackendSkillsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/backend-skills.php',
            'backend-skills',
        );
    }

    public function boot(): void
    {
        // Nada deste pacote é preciso em runtime: só publica ficheiros e
        // regista comandos de consola.
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            InstallCommand::class,
            CheckAo90Command::class,
            SkillsListCommand::class,
        ]);

        $this->publishes([
            __DIR__ . '/../resources/skills' => base_path(
                (string) config('backend-skills.skills_path', '.claude/skills'),
            ),
        ], 'backend-skills');

        $this->publishes([
            __DIR__ . '/../resources/lang/pt_PT' => lang_path('pt_PT'),
        ], 'backend-lang');

        $this->publishes([
            __DIR__ . '/../resources/stubs/CLAUDE.md' => base_path('CLAUDE.md'),
        ], 'backend-claude-md');

        $this->publishes([
            __DIR__ . '/../config/backend-skills.php' => config_path('backend-skills.php'),
        ], 'backend-skills-config');
    }
}
