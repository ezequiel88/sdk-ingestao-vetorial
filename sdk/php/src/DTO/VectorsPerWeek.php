<?php

declare(strict_types=1);

namespace IngestaoVetorial\DTO;

final class VectorsPerWeek
{
    public function __construct(
        public readonly string $week_start,
        public readonly int    $count,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            week_start: (string) $data['week_start'],
            count:      (int) ($data['count'] ?? 0),
        );
    }
}
