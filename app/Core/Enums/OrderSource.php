<?php

namespace App\Core\Enums;

enum OrderSource: string
{
    case POS = 'POS';
    case QR = 'QR';
}
