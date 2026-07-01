<?php

namespace App\Enum;

enum ProvisioningStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Provisioned = 'provisioned';
    case Failed = 'failed';
    case Deprovisioning = 'deprovisioning';
    case Deprovisioned = 'deprovisioned';
}
