<?php

declare(strict_types=1);

namespace IngestaoVetorial\DTO;

final class ChunkMetadata
{
    public function __construct(
        public readonly string $document_path,
        public readonly int    $page_number,
        public readonly string $section,
        public readonly int    $start_char,
        public readonly int    $end_char,
        public readonly string $chunk_id,
        public readonly string $collection_id,
        public readonly string $created_at,
        public readonly string $model,
        public readonly int    $dimension,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            document_path: (string) ($data['document_path'] ?? ''),
            page_number:   (int) ($data['page_number'] ?? 0),
            section:       (string) ($data['section'] ?? ''),
            start_char:    (int) ($data['start_char'] ?? 0),
            end_char:      (int) ($data['end_char'] ?? 0),
            chunk_id:      (string) ($data['chunk_id'] ?? ''),
            collection_id: (string) ($data['collection_id'] ?? ''),
            created_at:    (string) ($data['created_at'] ?? ''),
            model:         (string) ($data['model'] ?? ''),
            dimension:     (int) ($data['dimension'] ?? 0),
        );
    }
}
