<?php

namespace App\Enums;

enum UserRelationships: string
{
    case FRIEND = 'friend';
    case FOLLOWER = 'follower';
    case BLOCKED = 'blocked';
    case SPOUSE = 'spouse';
    case PARENT = 'parent';
    case CHILD = 'child';    
}
