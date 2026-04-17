<?php

declare(strict_types=1);

namespace IngestaoVetorial\DTO;

final class Tag
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id:   (string) $data['id'],
            name: (string) $data['name'],
        );
    }
}
