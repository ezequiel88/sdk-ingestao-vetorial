<?php

declare(strict_types=1);

namespace IngestaoVetorial\DTO;

/**
 * Input options for {@see \IngestaoVetorial\Client::upload()}.
 *
 * The SDK serialises `metadata` fields internally to the JSON string
 * the API expects — callers always work with a typed object.
 */
final class UploadOptions
{
    /**
     * @param string[]               $tags
     * @param array<string, mixed>[] $customFields
     */
    public function __construct(
        public readonly string  $collectionId,
        public readonly string  $documentType       = 'document',
        public readonly string  $description        = '',
        public readonly array   $tags               = [],
        public readonly array   $customFields       = [],
        public readonly bool    $overwriteExisting  = false,
        public readonly ?string $embeddingModel     = null,
        public readonly ?int    $dimension          = null,
        public readonly ?string $extractionTool     = null,
    ) {}
}
