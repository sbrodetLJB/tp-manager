<?php

namespace App\Enum;

enum ProvisioningAction: string
{
    case Create = 'create';
    case Delete = 'delete';
    case Reset = 'reset';
}
