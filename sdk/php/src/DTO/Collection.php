<?php

declare(strict_types=1);

namespace IngestaoVetorial\DTO;

final class Collection
{
    public function __construct(
        public readonly string  $id,
        public readonly string  $name,
        public readonly string  $alias,
        public readonly ?string $description,
        public readonly bool    $is_public,
        public readonly string  $embedding_model,
        public readonly int     $dimension,
        public readonly int     $chunk_size,
        public readonly int     $chunk_overlap,
        public readonly string  $created_at,
        public readonly int     $document_count,
        public readonly ?string $user_id,
        public readonly ?string $project_id,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id:              (string) $data['id'],
            name:            (string) $data['name'],
            alias:           (string) ($data['alias'] ?? ''),
            description:     isset($data['description']) ? (string) $data['description'] : null,
            is_public:       (bool) ($data['is_public'] ?? false),
            embedding_model: (string) $data['embedding_model'],
            dimension:       (int) $data['dimension'],
            chunk_size:      (int) ($data['chunk_size'] ?? 1400),
            chunk_overlap:   (int) ($data['chunk_overlap'] ?? 250),
            created_at:      (string) $data['created_at'],
            document_count:  (int) ($data['document_count'] ?? 0),
            user_id:         isset($data['user_id']) ? (string) $data['user_id'] : null,
            project_id:      isset($data['project_id']) ? (string) $data['project_id'] : null,
        );
    }
}
