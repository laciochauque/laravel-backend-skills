<?php

declare(strict_types=1);

namespace Laciochauque\BackendSkills\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

final class SkillsListCommand extends Command
{
    protected $signature = 'backend:skills {--available : Lista as skills do pacote em vez das instaladas}';

    protected $description = 'Lista as skills instaladas no projecto e o seu estado.';

    public function handle(Filesystem $files): int
    {
        $source    = __DIR__ . '/../../resources/skills';
        $installed = base_path((string) config('backend-skills.skills_path', '.claude/skills'));

        if ($this->option('available')) {
            $this->components->info('Skills disponíveis no pacote');

            foreach ($files->directories($source) as $directory) {
                $name = basename($directory);
                $this->components->twoColumnDetail($name, $this->summary($files, $directory));
            }

            return self::SUCCESS;
        }

        if (! $files->isDirectory($installed)) {
            $this->components->warn('Nenhuma skill instalada. Correr: php artisan backend:install');

            return self::SUCCESS;
        }

        $this->components->info("Skills em {$installed}");

        $packaged = array_map('basename', $files->directories($source));

        foreach ($files->directories($installed) as $directory) {
            $name   = basename($directory);
            $origem = in_array($name, $packaged, true) ? '' : ' <fg=cyan>(local)</>';

            $this->components->twoColumnDetail(
                $name . $origem,
                $this->summary($files, $directory),
            );
        }

        $ausentes = array_diff($packaged, array_map('basename', $files->directories($installed)));

        if ($ausentes !== []) {
            $this->newLine();
            $this->line('  Por instalar: <fg=yellow>' . implode(', ', $ausentes) . '</>');
        }

        return self::SUCCESS;
    }

    private function summary(Filesystem $files, string $directory): string
    {
        $skill = $directory . '/SKILL.md';

        if (! $files->exists($skill)) {
            return '<fg=red>SKILL.md em falta</>';
        }

        $lines  = substr_count($files->get($skill), "\n") + 1;
        $extras = count($files->allFiles($directory)) - 1;

        return $extras > 0
            ? "{$lines} linhas, +{$extras} ficheiros"
            : "{$lines} linhas";
    }
}
