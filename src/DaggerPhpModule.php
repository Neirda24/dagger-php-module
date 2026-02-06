<?php

declare(strict_types=1);

namespace DaggerModule;

use Dagger\Attribute\DaggerFunction;
use Dagger\Attribute\DaggerObject;
use Dagger\Container;

use function Dagger\dag;
use function rtrim;
use function str_contains;

#[DaggerObject]
class DaggerPhpModule
{
    private Container $phpContainer;

    private bool $withComposer = false;
    private bool $withPie = false;

    /**
     * @var array<string, string>
     */
    private array $envVariables = [];

    #[DaggerFunction]
    public function __construct(
        string $phpTagOrVersion = '',
        string $repository = ''
    ) {
        $this->phpContainer = $this->getContainerFromVersion($phpTagOrVersion, $repository);
    }

    private function getContainerFromVersion(string $phpTagOrVersion = '', string $repository = ''): Container
    {
        $repository = rtrim($repository, '/');
        if ('' !== $repository) {
            $repository .= '/';
        }

        if ('' === $phpTagOrVersion) {
            $phpTagOrVersion = 'php:latest';
        } elseif (!str_contains($phpTagOrVersion, ':')) {
            $phpTagOrVersion = "php:{$phpTagOrVersion}";
        }

        return dag()->container()->from("{$repository}{$phpTagOrVersion}");
    }

    private function phpVersion(): string
    {
        return $this->phpContainer->envVariable('PHP_VERSION');
    }

    #[DaggerFunction]
    public function withVersion(string $phpTagOrVersion = '', string $repository = ''): DaggerPhpModule
    {
        $that = clone $this;
        $that->phpContainer = $that->getContainerFromVersion($phpTagOrVersion, $repository);

        return $that;
    }

    #[DaggerFunction]
    public function withComposer(bool $withComposer = true): DaggerPhpModule
    {
        $that = clone $this;
        $that->withComposer = $withComposer;

        return $that;
    }

    #[DaggerFunction]
    public function withPie(bool $withPie = true): DaggerPhpModule
    {
        $that = clone $this;
        $that->withPie = $withPie;

        return $that;
    }

    #[DaggerFunction]
    public function withEnvVariable(string $name, string $value): DaggerPhpModule
    {
        $that = clone $this;
        $that->envVariables[$name] = $value;

        return $that;
    }

    #[DaggerFunction]
    public function container(): Container
    {
        $phpVersion = $this->phpVersion();
        $aptCache = dag()->cacheVolume("apt-cache-{$phpVersion}");

        $phpContainer = $this->phpContainer
            ->withMountedCache('/var/cache/apt/archives', $aptCache)
        ;

        if (true === $this->withPie) {
            // TODO: check if php>8.1
            $pieBin = dag()->container()->from('ghcr.io/php/pie:bin')->file('/pie');

            $phpContainer = $phpContainer
                ->withExec(['apt', 'update'])
                ->withExec(['apt', 'install', '-y', '--no-install-recommends',
                    'gcc',
                    'make',
                    'autoconf',
                    'libtool',
                    'bison',
                    're2c',
                    'pkg-config',
                    'php-dev',
                    'unzip',
                ])
                ->withFile('/usr/bin/pie', $pieBin)
            ;
        }

        if (true === $this->withComposer) {
            $composerBin = dag()->container()->from('composer/composer:latest-bin')->file('/composer');

            $composerCache = dag()->cacheVolume('composer-cache');

            $phpContainer = $phpContainer
                ->withMountedCache('/root/.composer/cache/files', $composerCache)
                ->withMountedFile('/usr/bin/composer', $composerBin)
                ->withEnvVariable('COMPOSER_ALLOW_SUPERUSER', '1')
                ->withExec(['apt', 'update'])
                ->withExec(['apt', 'install', '-y', '--no-install-recommends',
                    'git',
                    'zip',
                ])
            ;

            $globalDataDir = trim($phpContainer->withExec(['composer', 'global', 'config', 'data-dir'])->stdout());
            $globalBinDir = trim($phpContainer->withExec(['composer', 'global', 'config', 'bin-dir'])->stdout());

            $phpContainer = $phpContainer
                ->withEnvVariable(
                    'PATH',
                    "{$phpContainer->envVariable('PATH')}:{$globalDataDir}/{$globalBinDir}",
                )
            ;
        }

        foreach ($this->envVariables as $name => $value) {
            $phpContainer = $phpContainer->withEnvVariable($name, $value);
        }

        return $phpContainer;
    }
}
