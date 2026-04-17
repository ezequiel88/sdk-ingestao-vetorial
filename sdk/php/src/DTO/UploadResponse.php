<?php

declare(strict_types=1);

namespace IngestaoVetorial\DTO;

final class UploadResponse
{
    public function __construct(
        public readonly bool    $success,
        public readonly string  $document_id,
        public readonly int     $vector_count,
        public readonly int     $version,
        public readonly ?string $message,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            success:      (bool) ($data['success'] ?? false),
            document_id:  (string) ($data['document_id'] ?? ''),
            vector_count: (int) ($data['vector_count'] ?? 0),
            version:      (int) ($data['version'] ?? 1),
            message:      isset($data['message']) ? (string) $data['message'] : null,
        );
    }
}
