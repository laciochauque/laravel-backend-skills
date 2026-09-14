<?php

declare(strict_types=1);

namespace Laciochauque\BackendSkills\Commands;

use Illuminate\Console\Command;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Detecta ortografia AO90 em ficheiros que deviam estar em pré-AO90.
 *
 * Devolve código de saída 1 quando encontra ocorrências, para poder correr
 * no CI a par do PHPStan.
 */
final class CheckAo90Command extends Command
{
    protected $signature = 'backend:check-ao90
        {paths?* : Caminhos a analisar (por defeito os de config)}
        {--strict : Inclui "fato", que também é palavra válida em pré-AO90}';

    protected $description = 'Procura ortografia AO90 nos textos do projecto.';

    /** @var array<string, string> */
    private const array PADROES = [
        '/\bação\b/iu'               => 'acção',
        '/\bações\b/iu'              => 'acções',
        '/\bativ(o|a|os|as|ar|ado|ada|ados|adas)\b/iu' => 'activ…',
        '/\batua(l|is|lizar|lização|lizações|lizado|lizada)\b/iu' => 'actua…',
        '/\bcorret(o|a|os|as)\b/iu'  => 'correct…',
        '/\bcorreç(ão|ões)\b/iu'     => 'correcç…',
        '/\botimiz\w*/iu'            => 'optimiz…',
        '/\bótim(o|a|os|as)\b/iu'    => 'óptim…',
        '/\bobjetiv\w*/iu'           => 'objectiv…',
        '/\bdiretóri\w*/iu'          => 'directóri…',
        '/\bdiretriz\w*/iu'          => 'directriz…',
        '/\bexceç(ão|ões)\b/iu'      => 'excepç…',
        '/\bseleç(ão|ões)\b/iu'      => 'selecç…',
        '/\bselecionad\w*/iu'        => 'seleccionad…',
        '/\bcoleç(ão|ões)\b/iu'      => 'colecç…',
        '/\barquitetura\b/iu'        => 'arquitectura',
        '/\bprojet(o|os)\b/iu'       => 'project…',
        '/\beletrónic\w*/iu'         => 'electrónic…',
        '/\badoção\b/iu'             => 'adopção',
        '/\baspet(o|os)\b/iu'        => 'aspect…',
        '/\bdeteç(ão|ões)\b/iu'      => 'detecç…',
    ];

    public function handle(): int
    {
        /** @var list<string> $paths */
        $paths = $this->argument('paths');

        if ($paths === []) {
            /** @var list<string> $paths */
            $paths = (array) config('backend-skills.ao90_paths', ['lang', 'app']);
        }

        $existing = array_values(array_filter(
            array_map(static fn (string $p): string => base_path($p), $paths),
            static fn (string $p): bool => is_dir($p) || is_file($p),
        ));

        if ($existing === []) {
            $this->components->error('Nenhum dos caminhos indicados existe.');

            return self::FAILURE;
        }

        $padroes = self::PADROES;

        if ($this->option('strict')) {
            $padroes['/\bfato\b/iu'] = 'facto (se for acontecimento)';
        }

        $total = 0;

        foreach ($this->files($existing) as $file) {
            $total += $this->checkFile($file, $padroes);
        }

        $this->newLine();

        if ($total === 0) {
            $this->components->info('Ortografia pré-AO90 em ordem.');

            return self::SUCCESS;
        }

        $this->components->error("{$total} ocorrência(s) de ortografia AO90.");

        return self::FAILURE;
    }

    /**
     * @param  list<string>  $paths
     * @return iterable<SplFileInfo>
     */
    private function files(array $paths): iterable
    {
        $dirs  = array_values(array_filter($paths, 'is_dir'));
        $files = array_values(array_filter($paths, 'is_file'));

        foreach ($files as $file) {
            yield new SplFileInfo($file);
        }

        if ($dirs === []) {
            return;
        }

        yield from Finder::create()
            ->files()
            ->in($dirs)
            ->name(['*.php', '*.md', '*.json'])
            ->notPath('vendor')
            ->notPath('node_modules');
    }

    /** @param  array<string, string>  $padroes */
    private function checkFile(SplFileInfo $file, array $padroes): int
    {
        $contents = @file_get_contents($file->getPathname());

        if ($contents === false) {
            return 0;
        }

        $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
        $found    = 0;

        foreach (explode("\n", $contents) as $number => $line) {
            foreach ($padroes as $pattern => $correction) {
                if (preg_match($pattern, $line, $matches) !== 1) {
                    continue;
                }

                $this->line(sprintf(
                    '  <fg=yellow>%s:%d</>  "%s" → %s',
                    $relative,
                    $number + 1,
                    $matches[0],
                    $correction,
                ));

                $found++;
            }
        }

        return $found;
    }
}
