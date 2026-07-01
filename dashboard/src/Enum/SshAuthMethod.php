<?php

namespace App\Enum;

enum SshAuthMethod: string
{
    case Password = 'password';
    case PublicKey = 'public_key';
}
