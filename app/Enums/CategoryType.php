<?php

namespace App\Enums;

enum CategoryType: string
{
    case POST = 'post';
    case CLASSROOM = 'classroom';
    case EVENT = 'event';
    case MEDIA = 'media';
    case FORM = 'form';
    case LIBRARY = 'library';
}
