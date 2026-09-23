<?php

declare(strict_types=1);

namespace App\Enums;

enum ProcurementRole: string
{
    case Buyer = 'buyer';
    case Approver = 'approver';
    case Auditor = 'auditor';
    case Administrator = 'administrator';

    /**
     * The permissions granted by this role.
     *
     * @return list<ProcurementPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Buyer => [ProcurementPermission::ViewAllRequests, ProcurementPermission::ManageRequests],
            self::Approver => [ProcurementPermission::ViewAllRequests, ProcurementPermission::DecideApprovals],
            self::Auditor => [ProcurementPermission::ViewAllRequests],
            self::Administrator => [ProcurementPermission::ManageAccess],
        };
    }
}
