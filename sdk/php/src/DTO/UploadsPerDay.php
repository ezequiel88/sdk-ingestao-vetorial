<?php

declare(strict_types=1);

namespace IngestaoVetorial\DTO;

final class UploadsPerDay
{
    public function __construct(
        public readonly string $date,
        public readonly int    $count,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            date:  (string) $data['date'],
            count: (int) ($data['count'] ?? 0),
        );
    }
}
