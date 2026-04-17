<?php

declare(strict_types=1);

namespace IngestaoVetorial\DTO;

final class EmbeddingModelOption
{
    /**
     * @param int[] $dimensions
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $provider,
        public readonly array  $dimensions,
        public readonly int    $defaultDimension,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var int[] $dims */
        $dims = array_map('intval', (array) ($data['dimensions'] ?? []));

        return new self(
            id:               (string) $data['id'],
            name:             (string) $data['name'],
            provider:         (string) $data['provider'],
            dimensions:       $dims,
            defaultDimension: (int) ($data['defaultDimension'] ?? 0),
        );
    }
}
