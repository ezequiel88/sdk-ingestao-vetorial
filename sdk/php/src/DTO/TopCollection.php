<?php

declare(strict_types=1);

namespace IngestaoVetorial\DTO;

final class TopCollection
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly int    $document_count,
        public readonly int    $vector_count,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id:             (string) $data['id'],
            name:           (string) $data['name'],
            document_count: (int) ($data['document_count'] ?? 0),
            vector_count:   (int) ($data['vector_count'] ?? 0),
        );
    }
}
