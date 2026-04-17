<?php

declare(strict_types=1);

namespace IngestaoVetorial\Enums;

enum JobStatus: string
{
    case Extracting = 'extracting';
    case Chunking   = 'chunking';
    case Upserting  = 'upserting';
    case Completed  = 'completed';
    case Error      = 'error';
    case Cancelled  = 'cancelled';
}
