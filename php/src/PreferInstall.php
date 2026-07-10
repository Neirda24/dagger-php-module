<?php

declare(strict_types=1);

namespace DaggerModule;

enum PreferInstall: string
{
    case Dist = 'dist';
    case Source = 'source';
    case Auto = 'auto';
}
