<?php

declare(strict_types=1);

namespace DaggerModule;

use Dagger\Attribute\DaggerFunction;
use Dagger\Attribute\DaggerObject;
use Dagger\Container;
use Dagger\Directory;
use JMS\Serializer\Annotation\Type;
use function Dagger\dag;
use function rtrim;
use function str_contains;

#[DaggerObject]
class Php
{
    use ContainerTrait;

    private Container $phpContainer;

    private bool $withComposer = false;
    private bool $withPie = false;

    /**
     * @var array<string, string>
     */
    #[Type('array<string, string>')]
    private array $envVariables = [];

    #[DaggerFunction]
    public function __construct(
        string $phpTagOrVersion = '',
        string $repository = ''
    ) {
        $this->withContainer($this->getContainerFromVersion($phpTagOrVersion, $repository));
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

    private function getContainer(): Container
    {
        return $this->phpContainer;
    }

    private function phpVersion(): string
    {
        return $this->phpContainer->envVariable('PHP_VERSION');
    }

    #[DaggerFunction]
    public function withSources(Directory $sources, string $path = '/app'): Php
    {
        $that = clone $this;
        $that->phpContainer = $that->phpContainer
            ->withMountedDirectory($path, $sources)
            ->withWorkdir($path)
        ;

        return $that;
    }

    #[DaggerFunction]
    public function withVersion(string $phpTagOrVersion = '', string $repository = ''): Php
    {
        $that = clone $this;
        $that->phpContainer = $that->getContainerFromVersion($phpTagOrVersion, $repository);

        return $that;
    }

    #[DaggerFunction]
    public function withComposer(): Composer
    {
        $that = clone $this;

        return new Composer($that);
    }

    #[DaggerFunction]
    public function withPie(): Pie
    {
        $that = clone $this;

        return new Pie($that);
    }

    #[DaggerFunction]
    public function withEnvVariable(string $name, string $value): Php
    {
        $that = clone $this;
        $that->envVariables[$name] = $value;

        return $that;
    }

    #[DaggerFunction]
    public function withContainer(Container $container): Php
    {
        $that = clone $this;
        $that->phpContainer = $container;

        $phpVersion = $that->phpVersion();
        $aptCache = dag()->cacheVolume("apt-cache-{$phpVersion}");

        $that->phpContainer = $that->phpContainer
            ->withMountedCache('/var/cache/apt/archives', $aptCache)
        ;

        return $that;
    }

    #[DaggerFunction]
    public function container(): Container
    {
        foreach ($this->envVariables as $name => $value) {
            $phpContainer = $phpContainer->withEnvVariable($name, $value);
        }

        return $this->phpContainer = $phpContainer;
    }
}
