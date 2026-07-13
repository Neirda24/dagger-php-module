<?php

declare(strict_types=1);

namespace DaggerModule\Composer;

use Dagger\Attribute\DaggerObject;

#[DaggerObject]
enum AuditFormat: string
{
    case Table = 'table';
    case Plain = 'plain';
    case Json = 'json';
    case Summary = 'summary';
}
