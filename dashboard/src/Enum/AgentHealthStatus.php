<?php

namespace App\Enum;

enum AgentHealthStatus: string
{
    case Ok = 'ok';
    case Unreachable = 'unreachable';
    case VersionMismatch = 'version_mismatch';
    case Unknown = 'unknown';
}
