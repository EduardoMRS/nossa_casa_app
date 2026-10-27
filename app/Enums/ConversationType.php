<?php

namespace App\Enums;

enum ConversationType: string
{
    /** One-to-one between a member and the church leadership/finance. */
    case DIRECT = 'direct';

    /** Group chat attached to an Event, Group or Classroom. */
    case CONTEXT = 'context';
}
