<?php

declare(strict_types=1);

namespace DaggerModule\Composer;

use Dagger\Attribute\DaggerObject;

#[DaggerObject]
enum PreferInstall: string
{
    case Dist = 'dist';
    case Source = 'source';
    case Auto = 'auto';
}
