<?php

declare(strict_types=1);

namespace App\Enums;

enum PayrollStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Paid = 'paid';
    case Closed = 'closed';
}
