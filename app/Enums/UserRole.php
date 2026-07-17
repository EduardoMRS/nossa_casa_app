<?php

namespace App\Enums;

enum UserRole: string
{
    case GUEST = 'guest';
    case MEMBER = 'member';
    case LEADER = 'leader';
    case MEDIA = 'media';
    case ADMIN = 'admin';
    case SUPERADMIN = 'superadmin';
}
