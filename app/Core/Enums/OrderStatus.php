<?php

namespace App\Core\Enums;

enum OrderStatus: string
{
    case PENDING = 'PENDING';
    case CONFIRMED = 'CONFIRMED';
    case PREPARING = 'PREPARING';
    case READY = 'READY';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
}
