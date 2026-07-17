<?php

namespace App\Enums;

enum ChurchStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case CLOSED = 'closed';
    case DELETED = 'deleted';
}
