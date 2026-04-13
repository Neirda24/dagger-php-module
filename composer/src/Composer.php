<?php

declare(strict_types=1);

namespace DaggerModule;

use Dagger\Attribute\DaggerFunction;
use Dagger\Attribute\DaggerObject;
use Dagger\Attribute\ListOfType;
use Dagger\Container;
use Dagger\ReturnType;
use function Dagger\dag;

#[DaggerObject]
class Composer
{
    private bool $composerInstalled;

    #[DaggerFunction]
    public function __construct(
        private Container $phpContainer,
    ) {
        $this->phpContainer = $this->withContainer($phpContainer)->phpContainer;
    }

    private function installComposer(): void
    {
        $this->composerInstalled ??= $this->phpContainer->withExec(['which', 'composer'], expect: ReturnType::ANY)->exitCode() === 0;

        if ($this->composerInstalled === true) {
            return;
        }

        $composerBin = dag()->container()->from('composer/composer:latest-bin')->file('/composer');

        $composerCache = dag()->cacheVolume('composer-cache');

        $this->phpContainer = $this->phpContainer
            ->withMountedCache('${HOME}/.composer/cache/files', $composerCache, expand: true)
            ->withMountedFile('/usr/bin/composer', $composerBin)
            ->withEnvVariable('COMPOSER_ALLOW_SUPERUSER', '1')
        ;

        $gitInstalled = $this->phpContainer->withExec(['which', 'git'], expect: ReturnType::ANY)->exitCode() === 0;
        $zipInstalled = $this->phpContainer->withExec(['which', 'zip'], expect: ReturnType::ANY)->exitCode() === 0;

        if ($gitInstalled === false || $zipInstalled === false) {
            $this->phpContainer = $this->phpContainer
                ->withExec(['apt', 'update'])
                ->withExec(['apt', 'install', '-y', '--no-install-recommends',
                    'git',
                    'zip',
                ])
            ;
        }

        $globalDataDir = trim($this->phpContainer->withExec(['composer', 'global', 'config', 'data-dir'])->stdout());
        $globalBinDir = trim($this->phpContainer->withExec(['composer', 'global', 'config', 'bin-dir'])->stdout());

        $this->phpContainer = $this->phpContainer
            ->withEnvVariable(
                'PATH',
                "{$this->phpContainer->envVariable('PATH')}:{$globalDataDir}/{$globalBinDir}",
            )
        ;

        $this->composerInstalled = true;
    }

    public function withContainer(Container $container): Composer
    {
        $that = clone $this;
        unset($this->composerInstalled);
        $that->phpContainer = $container;

        $that->installComposer();

        return $that;
    }

    #[DaggerFunction]
    public function install(
        #[ListOfType('string')]
        array $packages = [],

        bool $dev = true,
        bool $autoloader = true,
        bool $optimizeAutoloader = true,
        bool $classMapAuthoritative = false,
        bool $progress = true,
        AuditFormat|null $audit = null,

        PreferInstall|null $preferInstall = null,
    ): Composer {
        $that = clone $this;

        $cmd = ['composer', 'install', '--no-interaction'];

        $packages = array_reduce($packages, function (array|null $packages, mixed $package): array|null {
            if ([] === $packages || null === $packages) {
                return null;
            }

            if (null === $package) {
                return $packages;
            }

            $package = trim((string) $package);

            if ('' === $package) {
                return $packages;
            }

            $packages ??= [];
            $packages[] = $package;

            return $packages;
        }, null);

        if ([] !== $packages && null !== $packages) {
            $cmd = [...$cmd, ...$packages];
        }

        if (null !== $preferInstall) {
            $cmd[] = "--prefer-install={$preferInstall->value}";
        }

        if (false === $dev) {
            $cmd[] = '--no-dev';
        }

        if (false === $autoloader) {
            $cmd[] = '--no-autoloader';
        }

        if (true === $optimizeAutoloader) {
            $cmd[] = '--optimize-autoloader';
        }

        if (true === $classMapAuthoritative) {
            $cmd[] = '--classmap-authoritative';
        }

        if (false === $progress) {
            $cmd[] = '--no-progress';
        }

        if (null !== $audit) {
            $cmd[] = '--audit';
            $cmd[] = "--audit-format={$audit->value}";
        }

        $that->phpContainer = $that->phpContainer
            ->withExec($cmd)
        ;

        return $that;
    }

    #[DaggerFunction]
    public function container(): Container
    {
        return $this->phpContainer;
    }
}
