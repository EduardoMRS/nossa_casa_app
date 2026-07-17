<?php

namespace App\Enums;

enum CategoryType: string
{
    case SUBSCRIPTION = 'subscription';
    case POST = 'post';
    case CLASSROOM = 'classroom';
    case EVENT = 'event';
    case MEDIA = 'media';
}
