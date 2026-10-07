<?php

namespace App\Core\Enums;

enum MovementType: string
{
    case PURCHASE = 'PURCHASE';
    case SALE = 'SALE';
    case WASTAGE = 'WASTAGE';
    case ADJUSTMENT = 'ADJUSTMENT';
}
