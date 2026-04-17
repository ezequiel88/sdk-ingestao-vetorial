<?php

declare(strict_types=1);

namespace IngestaoVetorial\DTO;

final class DocumentDetail
{
    /**
     * @param string[]          $tags
     * @param DocumentVersion[] $versions
     */
    public function __construct(
        public readonly string           $id,
        public readonly string           $name,
        public readonly string           $size,
        public readonly string           $uploaded_at,
        public readonly int              $vector_count,
        public readonly int              $chunk_count,
        public readonly int              $version,
        public readonly string           $collection_id,
        public readonly array            $tags,
        public readonly int              $version_count,
        public readonly string           $checksum,
        public readonly DocumentMetadata $metadata,
        public readonly array            $versions,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $metadata = isset($data['metadata']) && is_array($data['metadata'])
            ? DocumentMetadata::fromArray($data['metadata'])
            : new DocumentMetadata('document', null, [], []);

        $versions = [];
        if (isset($data['versions']) && is_array($data['versions'])) {
            foreach ($data['versions'] as $v) {
                if (is_array($v)) {
                    $versions[] = DocumentVersion::fromArray($v);
                }
            }
        }

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
            checksum:      (string) ($data['checksum'] ?? ''),
            metadata:      $metadata,
            versions:      $versions,
        );
    }
}
