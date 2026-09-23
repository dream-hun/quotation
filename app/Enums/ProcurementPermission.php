<?php

declare(strict_types=1);

namespace App\Enums;

enum ProcurementPermission: string
{
    case ViewAllRequests = 'view all purchase requests';
    case ManageRequests = 'manage purchase requests';
    case DecideApprovals = 'decide approvals';
    case ManageAccess = 'manage access';
}
