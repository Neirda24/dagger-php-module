<?php

declare(strict_types=1);

namespace DaggerModule;

enum AuditFormat: string
{
    case Table = 'table';
    case Plain = 'plain';
    case Json = 'json';
    case Summary = 'summary';
}
