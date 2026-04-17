<?php

declare(strict_types=1);

namespace IngestaoVetorial\DTO;

final class Document
{
    /**
     * @param string[] $tags
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $size,
        public readonly string $uploaded_at,
        public readonly int    $vector_count,
        public readonly int    $chunk_count,
        public readonly int    $version,
        public readonly string $collection_id,
        public readonly array  $tags,
        public readonly int    $version_count,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id:            (string) $data['id'],
            name:          (string) $data['name'],
            size:          (string) ($data['size'] ?? ''),
            uploaded_at:   (string) $data['uploaded_at'],
            vector_count:  (int) ($data['vector_count'] ?? 0),
            chunk_count:   (int) ($data['chunk_count'] ?? 0),
            version:       (int) ($data['version'] ?? 1),
            collection_id: (string) $data['collection_id'],
            tags:          array_map('strval', (array) ($data['tags'] ?? [])),
            version_count: (int) ($data['version_count'] ?? 1),
        );
    }
}
