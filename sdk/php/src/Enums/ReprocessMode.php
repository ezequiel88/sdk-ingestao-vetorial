<?php

declare(strict_types=1);

namespace IngestaoVetorial\Enums;

enum ReprocessMode: string
{
    case Replace = 'replace';
    case Append  = 'append';
}
