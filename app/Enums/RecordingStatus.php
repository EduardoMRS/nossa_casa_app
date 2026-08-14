<?php

namespace App\Enums;

enum RecordingStatus: string
{
    case WAITING_UPLOAD = 'waiting_upload';
    case UPLOADING = 'uploading';
    case READY = 'ready';
    case FAILED = 'failed';
}
