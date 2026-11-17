<?php

namespace App\Enums;

enum CreditTransactionType: string
{
    case Hold = 'hold';
    case Release = 'release';
    case Charge = 'charge';
    case Purchase = 'purchase';
}
