<?php

declare(strict_types=1);

namespace IngestaoVetorial\DTO;

final class JobProgress
{
    public function __construct(
        public readonly string  $job_id,
        public readonly string  $document_id,
        public readonly int     $version,
        /** Known values: extracting, chunking, upserting, completed, error, cancelled */
        public readonly string  $status,
        public readonly float   $percent,
        public readonly int     $processed_chunks,
        public readonly int     $total_chunks,
        /** Unix timestamp (seconds). */
        public readonly float   $started_at,
        /** Unix timestamp (seconds). */
        public readonly float   $updated_at,
        public readonly ?float  $eta_seconds,
        public readonly string  $message,
        public readonly string  $document_name,
        public readonly string  $collection_id,
        public readonly string  $error,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            job_id:           (string) ($data['job_id'] ?? ''),
            document_id:      (string) ($data['document_id'] ?? ''),
            version:          (int) ($data['version'] ?? 1),
            status:           (string) ($data['status'] ?? ''),
            percent:          (float) ($data['percent'] ?? 0.0),
            processed_chunks: (int) ($data['processed_chunks'] ?? 0),
            total_chunks:     (int) ($data['total_chunks'] ?? 0),
            started_at:       (float) ($data['started_at'] ?? 0.0),
            updated_at:       (float) ($data['updated_at'] ?? 0.0),
            eta_seconds:      isset($data['eta_seconds']) ? (float) $data['eta_seconds'] : null,
            message:          (string) ($data['message'] ?? ''),
            document_name:    (string) ($data['document_name'] ?? ''),
            collection_id:    (string) ($data['collection_id'] ?? ''),
            error:            (string) ($data['error'] ?? ''),
        );
    }
}
