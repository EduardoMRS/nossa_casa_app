<?php

namespace App\Enums;

enum UserCategory: string
{
    case MEMBER = 'member';
    case LEADER = 'leader';
    case MEDIA = 'media';
    case WORKER = 'worker';
    case PASTOR = 'pastor';
    case MISSIONARY = 'missionary';
}
