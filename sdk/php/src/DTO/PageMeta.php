<?php

declare(strict_types=1);

namespace IngestaoVetorial\DTO;

final class PageMeta
{
    public function __construct(
        public readonly int $page,
        public readonly int $pageSize,
        public readonly int $total,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            page:     (int) ($data['page'] ?? 1),
            pageSize: (int) ($data['pageSize'] ?? 50),
            total:    (int) ($data['total'] ?? 0),
        );
    }
}
