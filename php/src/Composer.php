<?php

declare(strict_types=1);

namespace DaggerModule;

use Dagger\Attribute\DaggerFunction;
use Dagger\Attribute\DaggerObject;
use Dagger\Attribute\Doc;
use Dagger\Attribute\ListOfType;
use Dagger\Container;
use Dagger\ReturnType;
use function array_filter;
use function array_map;
use function Dagger\dag;
use function trim;

#[DaggerObject]
#[Doc('Composer dependency manager. Provides install, update, and arbitrary composer commands on top of a PHP container.')]
final class Composer
{
    use ContainerTrait;

    private bool $composerInstalled = false;
    private Container $container;

    public function __construct(private Php $php)
    {
    }

    #[DaggerFunction]
    #[Doc('Return to the parent PHP container with all Composer changes applied.')]
    public function endComposer(): Php
    {
        return $this->php->withContainer($this->getContainer());
    }

    #[DaggerFunction]
    #[Doc('Export the underlying container directly, without returning to the PHP chain.')]
    public function container(): Container
    {
        return $this->getContainer();
    }

    private function getContainer(): Container
    {
        return $this->container ??= $this->php->container();
    }

    private function installComposer(): void
    {
        if ($this->composerInstalled === true) {
            return;
        }

        $alreadyInstalled = $this->getContainer()
            ->withExec(['which', 'composer'], expect: ReturnType::ANY)
            ->exitCode() === 0;

        if ($alreadyInstalled) {
            $this->composerInstalled = true;
            return;
        }

        $composerBin = dag()->container()->from('composer/composer:latest-bin')->file('/composer');
        $composerCache = dag()->cacheVolume('composer-cache');

        $this->container = $this->getContainer()
            ->withMountedCache('/root/.composer/cache/files', $composerCache)
            ->withMountedFile('/usr/bin/composer', $composerBin)
            ->withEnvVariable('COMPOSER_ALLOW_SUPERUSER', '1')
            ->withExec(['apt', 'update'])
            ->withExec(['apt', 'install', '-y', '--no-install-recommends', 'git', 'zip'])
        ;

        $globalDataDir = trim($this->container->withExec(['composer', 'global', 'config', 'data-dir'])->stdout());
        $globalBinDir = trim($this->container->withExec(['composer', 'global', 'config', 'bin-dir'])->stdout());

        $this->container = $this->container->withEnvVariable(
            'PATH',
            "{$this->container->envVariable('PATH')}:{$globalDataDir}/{$globalBinDir}",
        );

        $this->composerInstalled = true;
    }

    #[DaggerFunction]
    #[Doc('Run `composer install`. Installs dependencies defined in composer.json.')]
    public function install(
        #[ListOfType('string')]
        #[Doc('Specific packages to install (e.g. "vendor/package:^1.0"). Leave empty to install all from composer.json.')]
        array $packages = [],

        #[Doc('Include dev dependencies (--dev). Set to false for production installs.')]
        bool $dev = true,

        #[Doc('Generate the autoloader. Set to false to skip autoloader generation.')]
        bool $autoloader = true,

        #[Doc('Optimize the autoloader by converting PSR-0/4 autoloading to classmap (--optimize-autoloader).')]
        bool $optimizeAutoloader = true,

        #[Doc('Use classmap-authoritative mode for maximum autoloader performance (--classmap-authoritative).')]
        bool $classMapAuthoritative = false,

        #[Doc('Show a progress indicator during download (--no-progress to disable).')]
        bool $progress = true,

        #[Doc('Run a security audit after install and format the output.')]
        AuditFormat|null $audit = null,

        #[Doc('Preferred installation method: dist (default), source, or auto.')]
        PreferInstall|null $preferInstall = null,
    ): Composer {
        $that = clone $this;
        $that->installComposer();

        $cmd = ['composer', 'install', '--no-interaction'];

        $packages = array_filter(
            array_map(static fn (mixed $p) => trim((string) $p), $packages),
            static fn (string $p) => $p !== '',
        );

        if ([] !== $packages) {
            $cmd = [...$cmd, ...$packages];
        }

        if ($preferInstall !== null) {
            $cmd[] = "--prefer-install={$preferInstall->value}";
        }

        if ($dev === false) {
            $cmd[] = '--no-dev';
        }

        if ($autoloader === false) {
            $cmd[] = '--no-autoloader';
        }

        if ($optimizeAutoloader === true) {
            $cmd[] = '--optimize-autoloader';
        }

        if ($classMapAuthoritative === true) {
            $cmd[] = '--classmap-authoritative';
        }

        if ($progress === false) {
            $cmd[] = '--no-progress';
        }

        if ($audit !== null) {
            $cmd[] = '--audit';
            $cmd[] = "--audit-format={$audit->value}";
        }

        $that->container = $that->getContainer()->withExec($cmd);

        return $that;
    }

    #[DaggerFunction]
    #[Doc('Run an arbitrary Composer command (e.g. ["dump-autoload", "--optimize"] or ["require", "vendor/pkg"]).')]
    public function run(
        #[ListOfType('string')]
        #[Doc('Composer command and its arguments (without the leading "composer" keyword).')]
        array $args,
    ): Composer {
        $that = clone $this;
        $that->installComposer();

        $that->container = $that->getContainer()->withExec(['composer', ...$args]);

        return $that;
    }
}
