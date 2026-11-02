<?php

namespace App\Core\Enums;

enum TenantStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case PENDING = 'PENDING';
    case REJECTED = 'REJECTED';
}
