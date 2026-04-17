<?php

declare(strict_types=1);

namespace IngestaoVetorial\DTO;

final class DocumentMetadata
{
    /**
     * @param string[]             $tags
     * @param array<string, mixed>[] $custom_fields
     */
    public function __construct(
        public readonly string  $document_type,
        public readonly ?string $description,
        public readonly array   $tags,
        public readonly array   $custom_fields,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            document_type: (string) ($data['document_type'] ?? 'document'),
            description:   isset($data['description']) ? (string) $data['description'] : null,
            tags:          array_map('strval', (array) ($data['tags'] ?? [])),
            custom_fields: (array) ($data['custom_fields'] ?? []),
        );
    }
}
