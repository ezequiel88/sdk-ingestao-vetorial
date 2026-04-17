<?php

declare(strict_types=1);

namespace IngestaoVetorial\DTO;

final class LogSummary
{
    /**
     * @param array<string, int>                        $byLevel
     * @param array<string, int>                        $byApp
     * @param array<array{endpoint: string, c: int}>    $topEndpoints
     */
    public function __construct(
        public readonly int   $total,
        public readonly array $byLevel,
        public readonly array $byApp,
        public readonly array $topEndpoints,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var array<string, int> $byLevel */
        $byLevel = is_array($data['byLevel'] ?? null) ? $data['byLevel'] : [];
        /** @var array<string, int> $byApp */
        $byApp = is_array($data['byApp'] ?? null) ? $data['byApp'] : [];
        /** @var array<array{endpoint: string, c: int}> $topEndpoints */
        $topEndpoints = is_array($data['topEndpoints'] ?? null) ? $data['topEndpoints'] : [];

        return new self(
            total:        (int) ($data['total'] ?? 0),
            byLevel:      $byLevel,
            byApp:        $byApp,
            topEndpoints: $topEndpoints,
        );
    }
}
