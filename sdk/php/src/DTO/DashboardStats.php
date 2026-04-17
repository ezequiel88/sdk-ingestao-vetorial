<?php

declare(strict_types=1);

namespace IngestaoVetorial\DTO;

final class DashboardStats
{
    public function __construct(
        public readonly int   $total_collections,
        public readonly int   $total_documents,
        public readonly int   $total_vectors,
        public readonly float $total_size_mb,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            total_collections: (int) ($data['total_collections'] ?? 0),
            total_documents:   (int) ($data['total_documents'] ?? 0),
            total_vectors:     (int) ($data['total_vectors'] ?? 0),
            total_size_mb:     (float) ($data['total_size_mb'] ?? 0.0),
        );
    }
}
