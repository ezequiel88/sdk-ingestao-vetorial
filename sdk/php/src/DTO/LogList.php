<?php

declare(strict_types=1);

namespace IngestaoVetorial\DTO;

final class LogList
{
    /**
     * @param LogEntry[] $items
     */
    public function __construct(
        public readonly array    $items,
        public readonly PageMeta $meta,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $items = [];
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                if (is_array($item)) {
                    $items[] = LogEntry::fromArray($item);
                }
            }
        }

        $meta = isset($data['meta']) && is_array($data['meta'])
            ? PageMeta::fromArray($data['meta'])
            : new PageMeta(1, 50, 0);

        return new self(items: $items, meta: $meta);
    }
}
