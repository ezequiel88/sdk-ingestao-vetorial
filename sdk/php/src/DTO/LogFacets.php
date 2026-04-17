<?php

declare(strict_types=1);

namespace IngestaoVetorial\DTO;

final class LogFacets
{
    /**
     * @param string[] $apps
     * @param string[] $endpoints
     * @param string[] $projects
     * @param string[] $users
     */
    public function __construct(
        public readonly array $apps,
        public readonly array $endpoints,
        public readonly array $projects,
        public readonly array $users,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            apps:      array_map('strval', (array) ($data['apps'] ?? [])),
            endpoints: array_map('strval', (array) ($data['endpoints'] ?? [])),
            projects:  array_map('strval', (array) ($data['projects'] ?? [])),
            users:     array_map('strval', (array) ($data['users'] ?? [])),
        );
    }
}
