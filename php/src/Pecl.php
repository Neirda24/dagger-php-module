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
use function trim;

#[DaggerObject]
#[Doc('PECL legacy PHP extension installer. Use this for extensions not yet available via PIE (e.g. redis, imagick, memcached). Prefer PIE when the extension is available there.')]
final class Pecl
{
    use ContainerTrait;

    private bool $peclInstalled = false;
    private Container $container;

    public function __construct(private Php $php)
    {
    }

    #[DaggerFunction]
    #[Doc('Return to the parent PHP container with all installed PECL extensions applied.')]
    public function endPecl(): Php
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

    private function installPecl(): void
    {
        if ($this->peclInstalled === true) {
            return;
        }

        $alreadyInstalled = $this->getContainer()
            ->withExec(['which', 'pecl'], expect: ReturnType::ANY)
            ->exitCode() === 0;

        if ($alreadyInstalled) {
            $this->peclInstalled = true;
            return;
        }

        $this->container = $this->getContainer()
            ->withExec(['apt', 'update'])
            ->withExec(['apt', 'install', '-y', '--no-install-recommends',
                'php-pear',
                'php-dev',
                'gcc',
                'make',
                'autoconf',
                'pkg-config',
            ])
            ->withExec(['pecl', 'channel-update', 'pecl.php.net'])
        ;

        $this->peclInstalled = true;
    }

    #[DaggerFunction]
    #[Doc('Install one or more PECL extensions. Each extension is installed and enabled automatically.')]
    public function install(
        #[ListOfType('string')]
        #[Doc('PECL extension names with optional version (e.g. "redis", "xdebug-3.3.0", "imagick").')]
        array $packages = [],

        #[Doc('Force installation even when the extension is already installed.')]
        bool $force = false,
    ): Pecl {
        $that = clone $this;
        $that->installPecl();

        $packages = array_filter(
            array_map(static fn (mixed $p) => trim((string) $p), $packages),
            static fn (string $p) => $p !== '',
        );

        $forceFlag = $force ? ['-f'] : [];

        foreach ($packages as $package) {
            $extensionName = explode('-', $package)[0];

            $that->container = $that->getContainer()
                ->withExec(['pecl', 'install', ...$forceFlag, $package])
                ->withExec(['bash', '-c', "echo 'extension={$extensionName}.so' > /usr/local/etc/php/conf.d/{$extensionName}.ini"])
            ;
        }

        return $that;
    }
}
