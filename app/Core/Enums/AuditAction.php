<?php

namespace App\Core\Enums;

enum AuditAction: string
{
    case LOGIN = 'LOGIN';
    case LOGOUT = 'LOGOUT';
}
