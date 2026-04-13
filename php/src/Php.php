<?php

declare(strict_types=1);

namespace DaggerModule;

use Dagger\Attribute\DaggerFunction;
use Dagger\Attribute\DaggerObject;
use Dagger\Attribute\Doc;
use Dagger\Attribute\DefaultPath;
use Dagger\Attribute\Ignore;
use Dagger\Attribute\ListOfType;
use Dagger\Container;
use Dagger\Directory;
use function Dagger\dag;
use function rtrim;
use function str_contains;

#[DaggerObject]
#[Doc('PHP container builder for CI/CD pipelines. Provides a fluent API to configure PHP containers with Composer, PIE, PECL, Symfony, and PHPUnit support.')]
class Php
{
    use ContainerTrait;

    private Container $phpContainer;

    #[DaggerFunction]
    #[Doc('Create a PHP container. Accepts any official PHP image tag or version string.')]
    public function __construct(
        #[Doc('PHP image tag or version string (e.g. "8.3-cli", "8.3-fpm", "8.2", "latest"). Defaults to "php:latest".')]
        string $phpTagOrVersion = '',

        #[Doc('Custom Docker registry prefix (e.g. "registry.example.com"). Defaults to Docker Hub.')]
        string $repository = '',
    ) {
        $this->phpContainer = $this
            ->withContainer($this->getContainerFromVersion($phpTagOrVersion, $repository))
            ->phpContainer
        ;
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
    #[Doc('Mount source code into the container and set it as the working directory.')]
    public function withSources(
        #[DefaultPath('.')]
        #[Ignore('vendor/', 'var/', '.git/')]
        #[Doc('Source directory to mount. Defaults to the directory containing dagger.json.')]
        Directory $sources,

        #[Doc('Mount path inside the container.')]
        string $path = '/app',
    ): Php {
        $that = clone $this;
        $that->phpContainer = $that->phpContainer
            ->withMountedDirectory($path, $sources)
            ->withWorkdir($path)
        ;

        return $that;
    }

    #[DaggerFunction]
    #[Doc('Switch to a different PHP version while preserving the current container configuration.')]
    public function withVersion(
        #[Doc('PHP image tag or version string (e.g. "8.3-cli", "8.2-fpm").')]
        string $phpTagOrVersion = '',

        #[Doc('Custom Docker registry prefix. Defaults to Docker Hub.')]
        string $repository = '',
    ): Php {
        $that = clone $this;
        $that->phpContainer = $that->getContainerFromVersion($phpTagOrVersion, $repository);

        return $that;
    }

    #[DaggerFunction]
    #[Doc('Enter the Composer sub-context to manage PHP dependencies. Chain composer operations and call endComposer() to return here.')]
    public function withComposer(): Composer
    {
        $that = clone $this;

        return new Composer($that);
    }

    #[DaggerFunction]
    #[Doc('Enter the PIE sub-context to install modern PHP extensions. Chain install() calls and call endPie() to return here.')]
    public function withPie(): Pie
    {
        $that = clone $this;

        return new Pie($that);
    }

    #[DaggerFunction]
    #[Doc('Enter the PECL sub-context to install legacy PHP extensions not yet available via PIE. Chain install() calls and call endPecl() to return here.')]
    public function withPecl(): Pecl
    {
        $that = clone $this;

        return new Pecl($that);
    }

    #[DaggerFunction]
    #[Doc('Enter the Symfony sub-context for framework-specific tooling (CLI, bin/console, cache, migrations). Call endSymfony() to return here.')]
    public function withSymfony(
        #[Doc('Value to set for the APP_ENV environment variable.')]
        string $appEnv = 'prod',
    ): Symfony {
        $that = clone $this;

        return new Symfony($that, $appEnv);
    }

    #[DaggerFunction]
    #[Doc('Set an environment variable on the PHP container.')]
    public function withEnvVariable(
        #[Doc('Environment variable name.')]
        string $name,

        #[Doc('Environment variable value.')]
        string $value,
    ): Php {
        $that = clone $this;
        $that->phpContainer = $that->phpContainer->withEnvVariable($name, $value);

        return $that;
    }

    #[DaggerFunction]
    #[Doc('Replace the underlying container with a custom one, while preserving apt cache mounts keyed by PHP version.')]
    public function withContainer(
        #[Doc('A pre-configured Dagger container to use as the new base.')]
        Container $container,
    ): Php {
        $that = clone $this;
        $that->phpContainer = $container;

        $phpVersion = $that->phpVersion();
        $aptCache = dag()->cacheVolume("apt-cache-archives-{$phpVersion}");

        $that->phpContainer = $that->phpContainer
            ->withMountedCache('/var/cache/apt/archives', $aptCache)
        ;

        return $that;
    }

    #[DaggerFunction]
    #[Doc('Run PHPUnit tests using the vendor/bin/phpunit binary installed via Composer.')]
    public function phpunit(
        #[ListOfType('string')]
        #[Doc('Additional PHPUnit arguments (e.g. ["--testsuite=unit", "--coverage-text"]). Runs with default configuration if empty.')]
        array $args = [],
    ): Container {
        return $this->phpContainer->withExec(['vendor/bin/phpunit', ...$args]);
    }

    #[DaggerFunction]
    #[Doc('Export the underlying Dagger container to use directly or pass to other Dagger functions.')]
    public function container(): Container
    {
        return $this->phpContainer;
    }
}
