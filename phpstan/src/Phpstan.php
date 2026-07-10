<?php

declare(strict_types=1);

namespace DaggerModule;

use Dagger\Attribute\DaggerFunction;
use Dagger\Attribute\DaggerObject;
use Dagger\Attribute\DefaultPath;
use Dagger\Attribute\Doc;
use Dagger\Attribute\Ignore;
use Dagger\Attribute\ListOfType;
use Dagger\Directory;
use function Dagger\dag;

#[DaggerObject]
#[Doc('PHPStan static analysis module. Runs type-safe analysis using the official PHPStan Docker image (ghcr.io/phpstan/phpstan). No local PHP installation required.')]
class Phpstan
{
    #[DaggerFunction]
    #[Doc('Analyse PHP source files and return the analysis output. Fails the pipeline if PHPStan finds errors.')]
    public function analyse(
        #[DefaultPath('.')]
        #[Ignore('vendor/', 'var/', '.git/', 'node_modules/')]
        #[Doc('Source directory to analyse. Defaults to the directory containing dagger.json.')]
        Directory $sources,

        #[Doc('Analysis level from 0 (most permissive) to 9 (strictest), or "max". See https://phpstan.org/user-guide/rule-levels.')]
        string $level = '5',

        #[Doc('Path to a PHPStan configuration file relative to the sources root (e.g. "phpstan.neon", "phpstan.dist.neon"). Auto-detected if left empty.')]
        string $config = '',

        #[ListOfType('string')]
        #[Doc('Specific paths within sources to analyse (e.g. ["src/", "tests/"]). Analyses the entire sources directory if empty.')]
        array $paths = [],
    ): string {
        $cmd = ['php', '/phpstan.phar', 'analyse', '--no-progress', '--no-interaction'];

        $cmd[] = "--level={$level}";

        if ($config !== '') {
            $cmd[] = "--configuration={$config}";
        }

        if ($paths !== []) {
            $cmd = [...$cmd, ...$paths];
        }

        return dag()
            ->container()
            ->from('ghcr.io/phpstan/phpstan:latest')
            ->withMountedDirectory('/app', $sources)
            ->withWorkdir('/app')
            ->withExec($cmd)
            ->stdout()
        ;
    }
}
