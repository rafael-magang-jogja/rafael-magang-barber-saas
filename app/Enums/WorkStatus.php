<?php

declare(strict_types=1);

namespace App\Enums;

enum WorkStatus: string
{
    case Active = 'active';
    case Break = 'break';
    case Off = 'off';
}
