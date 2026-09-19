<?php

namespace App\Enums;

enum VisitCloseReason: string
{
    case StaffClear = 'staff_clear';
    case AutoClear = 'auto_clear';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';
    case Unassigned = 'unassigned';
    case Override = 'override';
}
