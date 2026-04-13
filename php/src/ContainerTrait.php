<?php

declare(strict_types=1);

namespace DaggerModule;

use Dagger\Attribute\DaggerFunction;
use Dagger\Container;

trait ContainerTrait
{
    abstract private function getContainer(): Container;

    #[DaggerFunction]
    public function stdout(): string
    {
        return $this->getContainer()->stdout();
    }

    #[DaggerFunction]
    public function stderr(): string
    {
        return $this->getContainer()->stderr();
    }
}
