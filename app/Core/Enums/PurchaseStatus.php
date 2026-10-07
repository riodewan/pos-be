<?php

namespace App\Core\Enums;

enum PurchaseStatus: string
{
    case DRAFT = 'DRAFT';
    case ORDERED = 'ORDERED';
    case RECEIVED = 'RECEIVED';
    case CANCELLED = 'CANCELLED';
}
