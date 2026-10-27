<?php

namespace App\Enums;

enum ChatMessageType: string
{
    case TEXT = 'text';
    case IMAGE = 'image';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case LINK = 'link';
}
