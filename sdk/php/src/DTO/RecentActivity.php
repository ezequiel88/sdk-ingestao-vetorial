<?php

declare(strict_types=1);

namespace IngestaoVetorial\DTO;

final class RecentActivity
{
    /** @param array<string, mixed> $details */
    public function __construct(
        public readonly string $id,
        public readonly string $action,
        public readonly string $entity,
        public readonly string $timestamp,
        public readonly array  $details,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id:        (string) $data['id'],
            action:    (string) ($data['action'] ?? ''),
            entity:    (string) ($data['entity'] ?? ''),
            timestamp: (string) ($data['timestamp'] ?? ''),
            details:   is_array($data['details'] ?? null) ? $data['details'] : [],
        );
    }
}
