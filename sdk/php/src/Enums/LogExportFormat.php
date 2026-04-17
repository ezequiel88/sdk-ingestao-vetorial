<?php

declare(strict_types=1);

namespace IngestaoVetorial\Enums;

enum LogExportFormat: string
{
    case Json = 'json';
    case Csv  = 'csv';
}
