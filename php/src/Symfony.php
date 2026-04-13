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
#[Doc('Symfony framework tooling. Provides the Symfony CLI binary, bin/console command runner, and common Symfony operations such as cache clearing and database migrations.')]
final class Symfony
{
    use ContainerTrait;

    private bool $cliInstalled = false;
    private Container $container;

    public function __construct(private Php $php, private string $appEnv = 'prod')
    {
        $this->container = $php->container()->withEnvVariable('APP_ENV', $appEnv);
    }

    #[DaggerFunction]
    #[Doc('Return to the parent PHP container with all Symfony changes applied.')]
    public function endSymfony(): Php
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
        return $this->container;
    }

    private function installCli(): void
    {
        if ($this->cliInstalled === true) {
            return;
        }

        $alreadyInstalled = $this->getContainer()
            ->withExec(['which', 'symfony'], expect: ReturnType::ANY)
            ->exitCode() === 0;

        if ($alreadyInstalled) {
            $this->cliInstalled = true;
            return;
        }

        $this->container = $this->getContainer()
            ->withExec(['apt', 'update'])
            ->withExec(['apt', 'install', '-y', '--no-install-recommends', 'curl', 'ca-certificates'])
            ->withExec(['bash', '-c',
                "curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | bash"
            ])
            ->withExec(['apt', 'install', '-y', 'symfony-cli'])
        ;

        $this->cliInstalled = true;
    }

    #[DaggerFunction]
    #[Doc('Install the Symfony CLI binary. Required before calling symfony check:requirements or symfony serve.')]
    public function withCli(): Symfony
    {
        $that = clone $this;
        $that->installCli();

        return $that;
    }

    #[DaggerFunction]
    #[Doc('Run a bin/console command inside the container.')]
    public function console(
        #[ListOfType('string')]
        #[Doc('Console command and arguments (e.g. ["cache:clear"], ["doctrine:migrations:migrate", "--no-interaction"]).')]
        array $args,
    ): Symfony {
        $that = clone $this;

        $args = array_filter(
            array_map(static fn (mixed $a) => trim((string) $a), $args),
            static fn (string $a) => $a !== '',
        );

        $that->container = $that->getContainer()->withExec(['php', 'bin/console', ...$args]);

        return $that;
    }

    #[DaggerFunction]
    #[Doc('Clear the Symfony application cache (bin/console cache:clear).')]
    public function clearCache(): Symfony
    {
        return $this->console(['cache:clear']);
    }

    #[DaggerFunction]
    #[Doc('Run Doctrine database migrations (bin/console doctrine:migrations:migrate --no-interaction).')]
    public function migrate(): Symfony
    {
        return $this->console(['doctrine:migrations:migrate', '--no-interaction']);
    }
}
