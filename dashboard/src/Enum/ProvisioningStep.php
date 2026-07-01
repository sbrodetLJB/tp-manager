<?php

namespace App\Enum;

enum ProvisioningStep: string
{
    case LinuxAccount = 'linux_account';
    case Database = 'database';
    case Webroot = 'webroot';
}
