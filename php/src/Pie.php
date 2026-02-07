<?php

declare(strict_types=1);

namespace DaggerModule;

use Dagger\Attribute\DaggerFunction;
use Dagger\Attribute\DaggerObject;
use Dagger\Attribute\Doc;
use Dagger\Attribute\ListOfType;
use Dagger\Container;
use Dagger\ReturnType;
use function array_reduce;
use function Dagger\dag;
use function trim;

#[DaggerObject]
final class Pie
{
    use ContainerTrait;

    private bool $pieInstalled;
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
        $this->pieInstalled ??= $this->getContainer()->withExec(['which', 'pie'], expect: ReturnType::ANY)->exitCode() === 0;

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
    #[Doc('Download, build, and install a PIE-compatible PHP extension.')]
    public function install(
        #[ListOfType('string')]
        #[Doc('The PIE package name and version constraint to use, in the format {vendor/package}{?:{?version-constraint}{?@stability}}, for example `xdebug/xdebug:^3.4@alpha`, `xdebug/xdebug:@alpha`, `xdebug/xdebug:^3.4`, etc.')]
        array $packages = [],

        #[Doc('To attempt to install a version that doesn\'t match the version constraints from the meta-data, for instance to install an older version than recommended, or when the signature is not available.')]
        bool $force = false,
    ): Pie {
        $that = clone $this;
        $that->installPie();

        $installCmd = ['pie', 'install', '--allow-non-interactive-project-install'];

        if (true === $force) {
            $installCmd[] = '--force';
        }

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
            $installCmd = [...$installCmd, ...$packages];
        }

        $that->container = $that->getContainer()
            ->withExec($installCmd)
        ;

        return $that;
    }
}
