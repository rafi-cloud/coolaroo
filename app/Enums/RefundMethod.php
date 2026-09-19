<?php

namespace App\Enums;

enum RefundMethod: string
{
    case Stripe = 'stripe';
    case Cash = 'cash';
    case Manual = 'manual';
}
