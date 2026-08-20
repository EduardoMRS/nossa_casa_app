<?php

namespace App\Enums;

enum UserRole: string
{
    case GUEST = 'guest';
    case MEMBER = 'member';
    case LEADER = 'leader';
    case MEDIA = 'media';
    case CHURCH_LEADER = 'church_leader';
    case SUPERADMIN = 'superadmin';
    case SYSTEM = 'system';
}
