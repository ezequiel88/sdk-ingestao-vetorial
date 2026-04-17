<?php

declare(strict_types=1);

namespace IngestaoVetorial\DTO;

final class SearchResult
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public readonly string  $id,
        public readonly string  $type,
        public readonly string  $document_name,
        public readonly string  $collection_id,
        public readonly string  $collection_name,
        public readonly ?int    $chunk_index,
        public readonly string  $content,
        public readonly float   $score,
        public readonly array   $metadata,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id:              (string) $data['id'],
            type:            (string) ($data['type'] ?? 'chunk'),
            document_name:   (string) ($data['document_name'] ?? ''),
            collection_id:   (string) ($data['collection_id'] ?? ''),
            collection_name: (string) ($data['collection_name'] ?? ''),
            chunk_index:     isset($data['chunk_index']) ? (int) $data['chunk_index'] : null,
            content:         (string) ($data['content'] ?? ''),
            score:           (float) ($data['score'] ?? 0.0),
            metadata:        is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
        );
    }
}
