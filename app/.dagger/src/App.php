<?php

declare(strict_types=1);

namespace DaggerModule;

use Dagger\Attribute\DaggerFunction;
use Dagger\Attribute\DaggerObject;
use Dagger\Attribute\DefaultPath;
use Dagger\Attribute\Doc;
use Dagger\Directory;
use function Dagger\dag;

#[DaggerObject]
#[Doc('A generated module for App functions')]
class App
{
    #[DaggerFunction]
    public function debug(
        #[DefaultPath('.')]
        Directory $sources
    ): \Dagger\Php {
        return dag()
            ->php('8.5-cli')
            ->withSources($sources)
            ->withPie()
                ->install()
            ->endPie()
            ->withComposer()
                ->install()
            ->endComposer()
        ;
    }
}
