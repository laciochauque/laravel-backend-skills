<?php

declare(strict_types=1);

namespace Laciochauque\BackendSkills\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

final class InstallCommand extends Command
{
    protected $signature = 'backend:install
        {--force : Sobrepõe ficheiros já existentes}
        {--skills-only : Instala apenas as skills}
        {--lang-only : Instala apenas os ficheiros de língua}
        {--no-env : Não altera o ficheiro .env}';

    protected $description = 'Instala as skills, o scaffold de traduções pt_PT e a configuração de localização.';

    public function handle(Filesystem $files): int
    {
        $this->components->info('Instalação — laravel-backend-skills');

        $skillsOnly = (bool) $this->option('skills-only');
        $langOnly   = (bool) $this->option('lang-only');

        if ($skillsOnly && $langOnly) {
            $this->components->error('--skills-only e --lang-only são mutuamente exclusivos.');

            return self::FAILURE;
        }

        if (! $langOnly) {
            $this->installSkills($files);
        }

        if (! $skillsOnly) {
            $this->installLang($files);
            $this->installClaudeMd($files);

            if (! $this->option('no-env')) {
                $this->patchEnv($files);
            }
        }

        $this->newLine();
        $this->components->info('Instalação concluída.');
        $this->line('  Confirme com: <fg=cyan>php artisan config:clear</> e depois');
        $this->line("  <fg=cyan>php artisan tinker --execute=\"echo __('validation.required', ['attribute' => 'nome']);\"</>");
        $this->newLine();

        return self::SUCCESS;
    }

    private function installSkills(Filesystem $files): void
    {
        $source      = __DIR__ . '/../../resources/skills';
        $destination = base_path((string) config('backend-skills.skills_path', '.claude/skills'));

        $files->ensureDirectoryExists($destination);

        /** @var list<string> $wanted */
        $wanted    = (array) config('backend-skills.skills', []);
        $installed = 0;
        $skipped   = 0;

        foreach ($files->directories($source) as $directory) {
            $name = basename($directory);

            if ($wanted !== [] && ! in_array($name, $wanted, true)) {
                continue;
            }

            $target = $destination . DIRECTORY_SEPARATOR . $name;

            if ($files->exists($target) && ! $this->option('force')) {
                $this->components->twoColumnDetail($name, '<fg=yellow>existe, ignorado</>');
                $skipped++;

                continue;
            }

            $files->copyDirectory($directory, $target);
            $this->components->twoColumnDetail($name, '<fg=green>instalada</>');
            $installed++;
        }

        $this->newLine();
        $this->line("  Skills: {$installed} instalada(s), {$skipped} ignorada(s).");

        if ($skipped > 0) {
            $this->line('  Use <fg=cyan>--force</> para sobrepor as existentes.');
        }

        $this->newLine();
    }

    private function installLang(Filesystem $files): void
    {
        $source      = __DIR__ . '/../../resources/lang/pt_PT';
        $destination = lang_path('pt_PT');

        $files->ensureDirectoryExists($destination);

        foreach ($files->files($source) as $file) {
            $target = $destination . DIRECTORY_SEPARATOR . $file->getFilename();

            // attributes.php e messages.php crescem com o projecto: nunca
            // sobrepor sem --force, ou perdem-se os campos já acrescentados.
            if ($files->exists($target) && ! $this->option('force')) {
                $this->components->twoColumnDetail(
                    'lang/pt_PT/' . $file->getFilename(),
                    '<fg=yellow>existe, ignorado</>',
                );

                continue;
            }

            $files->copy($file->getPathname(), $target);
            $this->components->twoColumnDetail(
                'lang/pt_PT/' . $file->getFilename(),
                '<fg=green>instalado</>',
            );
        }

        $this->newLine();
    }

    private function installClaudeMd(Filesystem $files): void
    {
        $source = __DIR__ . '/../../resources/stubs/CLAUDE.md';
        $target = base_path('CLAUDE.md');

        if ($files->exists($target) && ! $this->option('force')) {
            $this->components->twoColumnDetail('CLAUDE.md', '<fg=yellow>existe, ignorado</>');

            return;
        }

        if (! $this->confirm('Criar o CLAUDE.md com as regras sempre activas?', true)) {
            return;
        }

        $files->copy($source, $target);
        $this->components->twoColumnDetail('CLAUDE.md', '<fg=green>criado</>');
    }

    private function patchEnv(Filesystem $files): void
    {
        $path = base_path('.env');

        if (! $files->exists($path)) {
            $this->components->warn('.env não encontrado — configuração de locale e de logs ignorada.');

            return;
        }

        /** @var array<string, string> $values */
        $values = [
            'APP_LOCALE'          => (string) config('backend-skills.locale', 'pt_PT'),
            'APP_FALLBACK_LOCALE' => (string) config('backend-skills.fallback_locale', 'en'),
            'APP_FAKER_LOCALE'    => (string) config('backend-skills.locale', 'pt_PT'),
            'APP_TIMEZONE'        => (string) config('backend-skills.timezone', 'Africa/Maputo'),
            'LOG_CHANNEL'         => (string) config('backend-skills.log_channel', 'daily'),
            'LOG_DAILY_DAYS'      => (string) config('backend-skills.log_daily_days', 30),
        ];

        $contents = $files->get($path);

        /** @var array<string, string> $changed */
        $changed = [];

        foreach ($values as $key => $value) {
            $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

            if (preg_match($pattern, $contents) === 1) {
                $current = $this->currentEnvValue($contents, $key);

                if ($current === $value) {
                    continue;
                }

                $contents      = (string) preg_replace($pattern, "{$key}={$value}", $contents);
                $changed[$key] = "{$key}: {$current} → {$value}";

                continue;
            }

            $contents      = rtrim($contents, "\n") . "\n{$key}={$value}\n";
            $changed[$key] = "{$key}={$value} (acrescentado)";
        }

        if ($changed === []) {
            $this->components->twoColumnDetail('.env', '<fg=gray>já configurado</>');

            return;
        }

        $this->newLine();
        $this->line('  Alterações ao .env:');

        foreach ($changed as $line) {
            $this->line("    • {$line}");
        }

        if (! $this->confirm('Aplicar?', true)) {
            $this->components->warn('.env não alterado.');

            return;
        }

        $files->put($path, $contents);
        $this->components->twoColumnDetail('.env', '<fg=green>actualizado</>');

        if (! array_key_exists('APP_TIMEZONE', $changed)) {
            return;
        }

        $this->newLine();
        $this->components->warn(
            'APP_TIMEZONE altera o fuso em que os timestamps são GRAVADOS, '
            . 'não só apresentados. Se já existirem dados em UTC, ver a secção '
            . 'sobre fuso horário na skill laravel-pt-mz-strings antes de continuar.',
        );
    }

    private function currentEnvValue(string $contents, string $key): string
    {
        $pattern = '/^' . preg_quote($key, '/') . '=(.*)$/m';

        return preg_match($pattern, $contents, $matches) === 1
            ? trim($matches[1])
            : '';
    }
}
