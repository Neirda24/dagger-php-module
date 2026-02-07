<?php

declare(strict_types=1);

namespace DaggerModule;

use Dagger\Attribute\DaggerFunction;
use Dagger\Attribute\DaggerObject;
use Dagger\Container;
use function Dagger\dag;

#[DaggerObject]
final class Composer
{
    use ContainerTrait;

    private bool $composerInstalled = false;
    private Container $container;

    public function __construct(private Php $php)
    {
    }

    #[DaggerFunction]
    public function endComposer(): Php
    {
        return $this->php->withContainer($this->getContainer());
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

        $composerBin = dag()->container()->from('composer/composer:latest-bin')->file('/composer');

        $composerCache = dag()->cacheVolume('composer-cache');

        $this->container = $this->getContainer()
            ->withMountedCache('/root/.composer/cache/files', $composerCache)
            ->withMountedFile('/usr/bin/composer', $composerBin)
            ->withEnvVariable('COMPOSER_ALLOW_SUPERUSER', '1')
            ->withExec(['apt', 'update'])
            ->withExec(['apt', 'install', '-y', '--no-install-recommends',
                'git',
                'zip',
            ])
        ;

        $globalDataDir = trim($this->container->withExec(['composer', 'global', 'config', 'data-dir'])->stdout());
        $globalBinDir = trim($this->container->withExec(['composer', 'global', 'config', 'bin-dir'])->stdout());

        $this->container = $this->container
            ->withEnvVariable(
                'PATH',
                "{$this->container->envVariable('PATH')}:{$globalDataDir}/{$globalBinDir}",
            )
        ;

        $this->composerInstalled = true;
    }

    #[DaggerFunction]
    public function install(): Composer
    {
        $that = clone $this;
        $that->installComposer();

        $that->container = $that->getContainer()
            ->withExec(['composer', 'install'])
        ;

        return $that;
    }
}
