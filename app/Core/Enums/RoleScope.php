<?php

namespace App\Core\Enums;

enum RoleScope: string
{
    case INTERNAL = 'INTERNAL';
    case TENANT = 'TENANT';
}
