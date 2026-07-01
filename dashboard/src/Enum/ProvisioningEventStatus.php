<?php

namespace App\Enum;

enum ProvisioningEventStatus: string
{
    case Started = 'started';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
