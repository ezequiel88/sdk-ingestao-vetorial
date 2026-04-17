<?php

declare(strict_types=1);

namespace IngestaoVetorial\DTO;

final class DocumentVersion
{
    public function __construct(
        public readonly int    $version,
        public readonly string $uploaded_at,
        public readonly int    $vector_count,
        public readonly string $checksum,
        public readonly bool   $is_active,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            version:      (int) $data['version'],
            uploaded_at:  (string) $data['uploaded_at'],
            vector_count: (int) ($data['vector_count'] ?? 0),
            checksum:     (string) ($data['checksum'] ?? ''),
            is_active:    (bool) ($data['is_active'] ?? false),
        );
    }
}
