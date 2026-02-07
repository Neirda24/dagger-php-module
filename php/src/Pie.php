<?php

declare(strict_types=1);

namespace DaggerModule;

use Dagger\Attribute\DaggerFunction;
use Dagger\Attribute\DaggerObject;
use Dagger\Container;
use function Dagger\dag;

#[DaggerObject]
final class Pie
{
    use ContainerTrait;

    private bool $pieInstalled = false;
    private Container $container;

    public function __construct(private Php $php)
    {
    }

    #[DaggerFunction]
    public function endPie(): Php
    {
        return $this->php->withContainer($this->getContainer());
    }

    private function getContainer(): Container
    {
        return $this->container ??= $this->php->container();
    }

    private function installPie(): void
    {
        if ($this->pieInstalled === true) {
            return;
        }

        // TODO: check if php>8.1
        $pieBin = dag()->container()->from('ghcr.io/php/pie:bin')->file('/pie');

        $this->container = $this->getContainer()
            ->withExec(['apt', 'update'])
            ->withExec(['apt', 'install', '-y', '--no-install-recommends',
                'gcc',
                'make',
                'autoconf',
                'libtool',
                'bison',
                're2c',
                'pkg-config',
                'unzip',
            ])
            ->withFile('/usr/bin/pie', $pieBin)
        ;

        $this->pieInstalled = true;
    }

    #[DaggerFunction]
    public function install(): Pie
    {
        $that = clone $this;
        $that->installPie();

        $that->container = $that->getContainer()
            ->withExec(['pie', 'install', '--allow-non-interactive-project-install'])
        ;

        return $that;
    }
}
