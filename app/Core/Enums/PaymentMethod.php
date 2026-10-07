<?php

namespace App\Core\Enums;

enum PaymentMethod: string
{
    case CASH = 'CASH';
    case QRIS = 'QRIS';
    case TRANSFER = 'TRANSFER';
    case EDC = 'EDC';
    case ONLINE = 'ONLINE';
}
