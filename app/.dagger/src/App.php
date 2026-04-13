<?php

declare(strict_types=1);

namespace DaggerModule;

use Dagger\Attribute\DaggerFunction;
use Dagger\Attribute\DaggerObject;
use Dagger\Attribute\DefaultPath;
use Dagger\Attribute\Doc;
use Dagger\Attribute\Ignore;
use Dagger\Attribute\ListOfType;
use Dagger\Container;
use Dagger\Directory;
use function Dagger\dag;

#[DaggerObject]
#[Doc('Demo and integration test module. Showcases the php and phpstan modules with real-world usage examples.')]
class App
{
    #[DaggerFunction]
    #[Doc('Basic PHP setup: mount sources, install PIE extensions, install Composer dependencies. Opens an interactive terminal for debugging.')]
    public function debug(
        #[DefaultPath('.')]
        #[Ignore('vendor/', 'var/', '.git/')]
        Directory $sources,
    ): Container {
        return dag()
            ->php('8.3-cli')
            ->withSources($sources)

            ->withPie()
                ->install()
            ->endPie()

            ->withComposer()
                ->install()
            ->endComposer()

            ->container()
        ;
    }

    #[DaggerFunction]
    #[Doc('Symfony pipeline: install dependencies (no dev), clear cache, and run migrations.')]
    public function symfony(
        #[DefaultPath('.')]
        #[Ignore('vendor/', 'var/', '.git/')]
        Directory $sources,
    ): Container {
        return dag()
            ->php('8.3-fpm')
            ->withSources($sources)

            ->withComposer()
                ->install(dev: false, optimizeAutoloader: true)
            ->endComposer()

            ->withSymfony(appEnv: 'prod')
                ->clearCache()
                ->migrate()
            ->endSymfony()

            ->container()
        ;
    }

    #[DaggerFunction]
    #[Doc('Run PHPUnit tests. Returns the test output. Fails the pipeline if any test fails.')]
    public function test(
        #[DefaultPath('.')]
        #[Ignore('vendor/', 'var/', '.git/')]
        Directory $sources,

        #[ListOfType('string')]
        #[Doc('Extra PHPUnit arguments (e.g. ["--testsuite=unit", "--coverage-text"]).')]
        array $phpunitArgs = [],
    ): string {
        return dag()
            ->php('8.3-cli')
            ->withSources($sources)

            ->withComposer()
                ->install()
            ->endComposer()

            ->phpunit($phpunitArgs)
            ->stdout()
        ;
    }

    #[DaggerFunction]
    #[Doc('Run PHPStan static analysis on the sources directory. Returns the analysis report.')]
    public function analyse(
        #[DefaultPath('.')]
        #[Ignore('vendor/', 'var/', '.git/')]
        Directory $sources,

        #[Doc('PHPStan level from 0 to 9, or "max".')]
        string $level = '8',

        #[Doc('Path to a PHPStan config file (e.g. "phpstan.neon"). Auto-detected if empty.')]
        string $config = '',
    ): string {
        return dag()
            ->phpstan()
            ->analyse(sources: $sources, level: $level, config: $config)
        ;
    }

    #[DaggerFunction]
    #[Doc('Demo of PHP extension installation via PIE and PECL.')]
    public function withExtensions(
        #[DefaultPath('.')]
        #[Ignore('vendor/', 'var/', '.git/')]
        Directory $sources,
    ): Container {
        return dag()
            ->php('8.3-cli')
            ->withSources($sources)

            ->withPie()
                ->install(['xdebug/xdebug'])
            ->endPie()

            ->withPecl()
                ->install(['redis'])
            ->endPecl()

            ->container()
        ;
    }
}
