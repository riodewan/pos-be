<?php

namespace App\Core\Enums;

enum WastageStatus: string
{
    case DRAFT = 'DRAFT';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
}
