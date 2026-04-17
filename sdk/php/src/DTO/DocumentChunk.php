<?php

declare(strict_types=1);

namespace IngestaoVetorial\DTO;

final class DocumentChunk
{
    /**
     * @param float[] $embedding
     */
    public function __construct(
        public readonly int           $index,
        public readonly string        $content,
        public readonly int           $tokens,
        public readonly array         $embedding,
        public readonly ChunkMetadata $metadata,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $metadata = isset($data['metadata']) && is_array($data['metadata'])
            ? ChunkMetadata::fromArray($data['metadata'])
            : new ChunkMetadata('', 0, '', 0, 0, '', '', '', '', 0);

        /** @var float[] $embedding */
        $embedding = array_map('floatval', (array) ($data['embedding'] ?? []));

        return new self(
            index:     (int) ($data['index'] ?? 0),
            content:   (string) ($data['content'] ?? ''),
            tokens:    (int) ($data['tokens'] ?? 0),
            embedding: $embedding,
            metadata:  $metadata,
        );
    }
}
