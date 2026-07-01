<?php

namespace App\Enum;

enum DbEngine: string
{
    case Mysql = 'mysql';
    case Postgresql = 'postgresql';
}
