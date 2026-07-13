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
#[Doc('PIE (PHP Install Extension) — the modern PHP extension installer. Use this for extensions available on the PIE registry (https://php-ie.github.io/).')]
final class Pie
{
    use ContainerTrait;

    private bool $pieInstalled = false;
    private Container $container;

    public function __construct(private Php $php)
    {
    }

    #[DaggerFunction]
    #[Doc('Return to the parent PHP container with all installed extensions applied.')]
    public function endPie(): Php
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

    private function installPie(): void
    {
        if ($this->pieInstalled === true) {
            return;
        }

        $alreadyInstalled = $this->getContainer()
            ->withExec(['which', 'pie'], expect: ReturnType::ANY)
            ->exitCode() === 0;

        if ($alreadyInstalled) {
            $this->pieInstalled = true;
            return;
        }

        $pieBin = dag()->container()->from('ghcr.io/php/pie:nightly-bin')->file('/pie');

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
            ->withFile('/usr/local/bin/pie', $pieBin)
        ;

        $this->pieInstalled = true;
    }

    #[DaggerFunction]
    #[Doc('Download, build, and install one or more PIE-compatible PHP extensions.')]
    public function install(
        #[ListOfType('string')]
        #[Doc('Package name and optional version constraint in the format {vendor/package}{?:{?version}{?@stability}}. Examples: "xdebug/xdebug", "xdebug/xdebug:^3.4", "xdebug/xdebug:^3.4@alpha".')]
        array $packages = [],

        #[ListOfType('string')]
        #[Doc('You can provide PIE a map of which packages to use for each missing extension. Examples: "example_pie_extension=asgrim/example-pie-extension", "redis=phpredis/phpredis".')]
        array $selects = [],

        #[Doc('Force installation even when the version does not match metadata constraints or when signature verification is unavailable.')]
        bool $force = false,
    ): Pie {
        $that = clone $this;
        $that->installPie();

        $installCmd = ['pie', 'install'];

        if ($force === true) {
            $installCmd[] = '--force';
        }

        $selects = array_filter(
            array_map(static fn (mixed $p) => trim((string) $p), $selects),
            static fn (string $p) => $p !== '',
        );

        $packages = array_filter(
            array_map(static fn (mixed $p) => trim((string) $p), $packages),
            static fn (string $p) => $p !== '',
        );

        foreach ($selects as $select) {
            $installCmd[] = "--select={$select}";
        }

        if ([] === $packages) {
            $that->container = $that->getContainer()->withExec($installCmd);
        } else {
            foreach ($packages as $package) {
                $that->container = $that->getContainer()->withExec([...$installCmd, $package]);
            }
        }

        return $that;
    }
}
