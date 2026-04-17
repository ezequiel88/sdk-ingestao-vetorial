<?php

declare(strict_types=1);

namespace IngestaoVetorial;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use IngestaoVetorial\DTO\Collection;
use IngestaoVetorial\DTO\DashboardStats;
use IngestaoVetorial\DTO\Document;
use IngestaoVetorial\DTO\DocumentChunk;
use IngestaoVetorial\DTO\DocumentDetail;
use IngestaoVetorial\DTO\EmbeddingModelOption;
use IngestaoVetorial\DTO\JobProgress;
use IngestaoVetorial\DTO\LogEntry;
use IngestaoVetorial\DTO\LogFacets;
use IngestaoVetorial\DTO\LogList;
use IngestaoVetorial\DTO\LogSummary;
use IngestaoVetorial\DTO\RecentActivity;
use IngestaoVetorial\DTO\SearchResult;
use IngestaoVetorial\DTO\Tag;
use IngestaoVetorial\DTO\TopCollection;
use IngestaoVetorial\DTO\UploadOptions;
use IngestaoVetorial\DTO\UploadResponse;
use IngestaoVetorial\DTO\UploadsPerDay;
use IngestaoVetorial\DTO\VectorsPerWeek;
use IngestaoVetorial\Enums\LogExportFormat;
use IngestaoVetorial\Enums\ReprocessMode;
use IngestaoVetorial\Exceptions\ApiException;

final class Client
{
    private readonly ClientInterface $http;

    /**
     * @param string               $baseUrl    Base URL, e.g. `http://localhost:8000`
     * @param string               $apiKey     Sent as `X-API-Key` on every request
     * @param float                $timeout    Request timeout in seconds (default: 30)
     * @param ClientInterface|null $httpClient Inject a custom Guzzle client (useful for tests)
     */
    public function __construct(
        string                  $baseUrl,
        private readonly string $apiKey   = '',
        float                   $timeout  = 30.0,
        ?ClientInterface        $httpClient = null,
    ) {
        $this->http = $httpClient ?? new GuzzleClient([
            'base_uri' => rtrim($baseUrl, '/'),
            'timeout'  => $timeout,
        ]);
    }

    // ── Internals ─────────────────────────────────────────────────────────

    /** @return array<string, string> */
    private function buildHeaders(): array
    {
        $headers = ['Accept' => 'application/json'];
        if ($this->apiKey !== '') {
            $headers['X-API-Key'] = $this->apiKey;
        }
        return $headers;
    }

    /**
     * @param  array<string, scalar|null> $query
     * @return array<mixed>
     * @throws ApiException
     */
    private function doGet(string $path, array $query = []): array
    {
        return $this->doRequest('GET', $path, [
            'query' => array_filter($query, static fn(mixed $v): bool => $v !== null),
        ]);
    }

    /**
     * @param  array<string, scalar|null> $query
     * @return array<mixed>
     * @throws ApiException
     */
    private function doGetItems(string $path, array $query = []): array
    {
        $data = $this->doGet($path, $query);
        return isset($data['items']) && is_array($data['items']) ? array_values($data['items']) : $data;
    }

    /**
     * @param  array<string, scalar|null> $query
     * @return array<mixed>
     * @throws ApiException
     */
    private function doGetAllItems(string $path, array $query = [], int $limit = 100): array
    {
        $items = [];
        $skip = isset($query['skip']) ? (int) $query['skip'] : 0;
        $query['limit'] = isset($query['limit']) ? (int) $query['limit'] : $limit;

        do {
            $query['skip'] = $skip;
            $data = $this->doGet($path, $query);
            $pageItems = isset($data['items']) && is_array($data['items']) ? array_values($data['items']) : $data;
            $items = array_merge($items, $pageItems);
            $hasMore = (bool) (($data['meta']['has_more'] ?? false));
            $skip += count($pageItems);
        } while ($hasMore && count($pageItems) > 0);

        return $items;
    }

    /**
     * @param  array<string, mixed> $body
     * @return array<mixed>
     * @throws ApiException
     */
    private function doPost(string $path, array $body = []): array
    {
        return $this->doRequest('POST', $path, ['json' => $body]);
    }

    /**
     * @param  array<string, mixed> $body
     * @return array<mixed>
     * @throws ApiException
     */
    private function doPatch(string $path, array $body): array
    {
        return $this->doRequest('PATCH', $path, ['json' => $body]);
    }

    /** @throws ApiException */
    private function doDelete(string $path): void
    {
        try {
            $this->http->request('DELETE', $path, ['headers' => $this->buildHeaders()]);
        } catch (GuzzleException $e) {
            throw ApiException::fromGuzzle($e);
        }
    }

    /**
     * @param  array<string, scalar|null> $query
     * @throws ApiException
     */
    private function doGetBinary(string $path, array $query = []): string
    {
        try {
            $res = $this->http->request('GET', $path, [
                'headers' => $this->buildHeaders(),
                'query'   => array_filter($query, static fn(mixed $v): bool => $v !== null),
            ]);
            return (string) $res->getBody();
        } catch (GuzzleException $e) {
            throw ApiException::fromGuzzle($e);
        }
    }

    /**
     * Issue a POST with query-string params instead of a request body.
     *
     * @param  array<string, scalar|null> $query
     * @return array<mixed>
     * @throws ApiException
     */
    private function doPostQuery(string $path, array $query = []): array
    {
        return $this->doRequest('POST', $path, [
            'query' => array_filter($query, static fn(mixed $v): bool => $v !== null),
        ]);
    }

    /**
     * @param  array<string, mixed> $options Guzzle request options
     * @return array<mixed>
     * @throws ApiException
     */
    private function doRequest(string $method, string $path, array $options = []): array
    {
        try {
            $options['headers'] = $this->buildHeaders();
            $res     = $this->http->request($method, $path, $options);
            $decoded = json_decode((string) $res->getBody(), true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($decoded)) {
                return ['_raw' => $decoded];
            }

            return $decoded;
        } catch (GuzzleException $e) {
            throw ApiException::fromGuzzle($e);
        }
    }

    // ── Collections ───────────────────────────────────────────────────────

    /**
     * @return EmbeddingModelOption[]
     * @throws ApiException
     */
    public function embeddingModels(): array
    {
        /** @var array<array<string, mixed>> $data */
        $data = $this->doGetItems('/api/v1/collections/embedding-models');
        return array_map(
            static fn(mixed $item): EmbeddingModelOption => EmbeddingModelOption::fromArray((array) $item),
            $data,
        );
    }

    /**
     * @param  array<string, scalar|null> $params  skip, limit, logic, user_id, project_id, alias, query
     * @return Collection[]
     * @throws ApiException
     */
    public function collections(array $params = []): array
    {
        /** @var array<array<string, mixed>> $data */
        $data = $this->doGetItems('/api/v1/collections', $params);
        return array_map(
            static fn(mixed $item): Collection => Collection::fromArray((array) $item),
            $data,
        );
    }

    /**
     * @param  array<string, mixed> $params  name, embedding_model, dimension, chunk_size, etc.
     * @throws ApiException
     */
    public function createCollection(array $params): Collection
    {
        /** @var array<string, mixed> $data */
        $data = $this->doPost('/api/v1/collections', $params);
        return Collection::fromArray($data);
    }

    /** @throws ApiException */
    public function getCollection(string $collectionId): Collection
    {
        /** @var array<string, mixed> $data */
        $data = $this->doGet("/api/v1/collections/{$collectionId}");
        return Collection::fromArray($data);
    }

    /**
     * @param  array<string, mixed> $params  name, description, is_public
     * @throws ApiException
     */
    public function updateCollection(string $collectionId, array $params): Collection
    {
        /** @var array<string, mixed> $data */
        $data = $this->doPatch("/api/v1/collections/{$collectionId}", $params);
        return Collection::fromArray($data);
    }

    /** @throws ApiException */
    public function deleteCollection(string $collectionId): void
    {
        $this->doDelete("/api/v1/collections/{$collectionId}");
    }

    /**
     * Return the raw Qdrant collection info.
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function collectionRaw(string $collectionId): array
    {
        return $this->doGet("/api/v1/collections/{$collectionId}/raw");
    }

    /**
     * @return Document[]
     * @throws ApiException
     */
    public function collectionDocuments(
        string $collectionId,
        int    $skip  = 0,
        int    $limit = 100,
    ): array {
        /** @var array<array<string, mixed>> $data */
        $data = $this->doGetItems(
            "/api/v1/collections/{$collectionId}/documents",
            ['skip' => $skip, 'limit' => $limit],
        );
        return array_map(
            static fn(mixed $item): Document => Document::fromArray((array) $item),
            $data,
        );
    }

    // ── Documents ─────────────────────────────────────────────────────────

    /**
     * @return Document[]
     * @throws ApiException
     */
    public function documents(
        int     $skip          = 0,
        int     $limit         = 100,
        ?string $collectionId  = null,
    ): array {
        /** @var array<array<string, mixed>> $data */
        $data = $this->doGetItems('/api/v1/documents', [
            'skip'          => $skip,
            'limit'         => $limit,
            'collection_id' => $collectionId,
        ]);
        return array_map(
            static fn(mixed $item): Document => Document::fromArray((array) $item),
            $data,
        );
    }

    /** @throws ApiException */
    public function document(string $documentId): DocumentDetail
    {
        /** @var array<string, mixed> $data */
        $data = $this->doGet("/api/v1/documents/{$documentId}");
        return DocumentDetail::fromArray($data);
    }

    /**
     * @return DocumentChunk[]
     * @throws ApiException
     */
    public function documentChunks(string $documentId, ?int $version = null, ?string $q = null): array
    {
        /** @var array<array<string, mixed>> $data */
        $data = $this->doGetAllItems(
            "/api/v1/documents/{$documentId}/chunks",
            ['version' => $version, 'q' => $q],
        );
        return array_map(
            static fn(mixed $item): DocumentChunk => DocumentChunk::fromArray((array) $item),
            $data,
        );
    }

    /**
     * Download extracted markdown as raw bytes.
     *
     * @throws ApiException
     */
    public function documentMarkdown(string $documentId, ?int $version = null): string
    {
        return $this->doGetBinary(
            "/api/v1/documents/{$documentId}/markdown",
            ['version' => $version],
        );
    }

    /** @throws ApiException */
    public function deleteDocument(string $documentId): void
    {
        $this->doDelete("/api/v1/documents/{$documentId}");
    }

    /**
     * Re-run the ingestion pipeline. Params are sent on the query string.
     *
     * @throws ApiException
     */
    public function reprocessDocument(
        string        $documentId,
        ReprocessMode $mode          = ReprocessMode::Replace,
        ?int          $sourceVersion = null,
        ?string       $extractionTool = null,
    ): UploadResponse {
        /** @var array<string, mixed> $data */
        $data = $this->doPostQuery("/api/v1/documents/{$documentId}/reprocess", [
            'mode'            => $mode->value,
            'source_version'  => $sourceVersion,
            'extraction_tool' => $extractionTool,
        ]);
        return UploadResponse::fromArray($data);
    }

    /** @throws ApiException */
    public function deleteDocumentVersion(string $documentId, int $version): void
    {
        $this->doDelete("/api/v1/documents/{$documentId}/versions/{$version}");
    }

    /** @throws ApiException */
    public function setVersionActive(
        string $documentId,
        int    $version,
        bool   $isActive,
    ): DocumentDetail {
        /** @var array<string, mixed> $data */
        $data = $this->doPatch(
            "/api/v1/documents/{$documentId}/versions/{$version}",
            ['is_active' => $isActive],
        );
        return DocumentDetail::fromArray($data);
    }

    // ── Upload ────────────────────────────────────────────────────────────

    /**
     * Upload a file and start the ingestion pipeline.
     *
     * @param string|\SplFileInfo $file       File path or SplFileInfo
     * @throws ApiException
     */
    public function upload(string|\SplFileInfo $file, UploadOptions $options): UploadResponse
    {
        $path     = $file instanceof \SplFileInfo ? $file->getPathname() : $file;
        $filename = basename($path);

        $metadata = [
            'document_type' => $options->documentType,
            'description'   => $options->description,
            'tags'          => $options->tags,
            'custom_fields' => $options->customFields,
        ];

        $multipart = [
            ['name' => 'file',               'contents' => fopen($path, 'r'), 'filename' => $filename],
            ['name' => 'collection_id',       'contents' => $options->collectionId],
            ['name' => 'metadata',            'contents' => json_encode($metadata, JSON_THROW_ON_ERROR)],
            ['name' => 'overwrite_existing',  'contents' => $options->overwriteExisting ? 'true' : 'false'],
        ];

        if ($options->embeddingModel !== null) {
            $multipart[] = ['name' => 'embedding_model', 'contents' => $options->embeddingModel];
        }
        if ($options->dimension !== null) {
            $multipart[] = ['name' => 'dimension', 'contents' => (string) $options->dimension];
        }
        if ($options->extractionTool !== null) {
            $multipart[] = ['name' => 'extraction_tool', 'contents' => $options->extractionTool];
        }

        try {
            $res = $this->http->request('POST', '/api/v1/upload', [
                'headers'    => $this->buildHeaders(),
                'multipart'  => $multipart,
            ]);
            /** @var array<string, mixed> $data */
            $data = json_decode((string) $res->getBody(), true, 512, JSON_THROW_ON_ERROR);
            return UploadResponse::fromArray($data);
        } catch (GuzzleException $e) {
            throw ApiException::fromGuzzle($e);
        }
    }

    // ── Search ────────────────────────────────────────────────────────────

    /**
     * @return SearchResult[]
     * @throws ApiException
     */
    public function search(
        string  $query,
        ?string $collectionId = null,
        int     $limit        = 10,
        int     $offset       = 0,
        float   $minScore     = 0.0,
    ): array {
        /** @var array<array<string, mixed>> $data */
        $data = $this->doGetItems('/api/v1/search', [
            'query'         => $query,
            'collection_id' => $collectionId,
            'limit'         => $limit,
            'offset'        => $offset,
            'min_score'     => $minScore,
        ]);
        return array_map(
            static fn(mixed $item): SearchResult => SearchResult::fromArray((array) $item),
            $data,
        );
    }

    // ── Tags ──────────────────────────────────────────────────────────────

    /**
     * List tags (returns plain name strings).
     *
     * @return string[]
     * @throws ApiException
     */
    public function tags(int $skip = 0, int $limit = 100): array
    {
        /** @var string[] $data */
        $data = $this->doGetItems('/api/v1/tags', ['skip' => $skip, 'limit' => $limit]);
        return array_map('strval', $data);
    }

    /**
     * @return string[]
     * @throws ApiException
     */
    public function searchTags(string $q): array
    {
        /** @var string[] $data */
        $data = $this->doGetItems('/api/v1/tags/search', ['q' => $q]);
        return array_map('strval', $data);
    }

    /** @throws ApiException */
    public function createTag(string $name): Tag
    {
        /** @var array<string, mixed> $data */
        $data = $this->doPost('/api/v1/tags', ['name' => $name]);
        return Tag::fromArray($data);
    }

    // ── Stats ─────────────────────────────────────────────────────────────

    /** @throws ApiException */
    public function dashboardStats(): DashboardStats
    {
        /** @var array<string, mixed> $data */
        $data = $this->doGet('/api/v1/stats/dashboard');
        return DashboardStats::fromArray($data);
    }

    /**
     * @return RecentActivity[]
     * @throws ApiException
     */
    public function recentActivity(int $limit = 5): array
    {
        /** @var array<array<string, mixed>> $data */
        $data = $this->doGetItems('/api/v1/stats/activity', ['limit' => $limit]);
        return array_map(
            static fn(mixed $item): RecentActivity => RecentActivity::fromArray((array) $item),
            $data,
        );
    }

    /**
     * @return TopCollection[]
     * @throws ApiException
     */
    public function topCollections(int $limit = 5): array
    {
        /** @var array<array<string, mixed>> $data */
        $data = $this->doGetItems('/api/v1/stats/top-collections', ['limit' => $limit]);
        return array_map(
            static fn(mixed $item): TopCollection => TopCollection::fromArray((array) $item),
            $data,
        );
    }

    /**
     * @return UploadsPerDay[]
     * @throws ApiException
     */
    public function uploadsPerDay(int $days = 7): array
    {
        /** @var array<array<string, mixed>> $data */
        $data = $this->doGetItems('/api/v1/stats/uploads-per-day', ['days' => $days]);
        return array_map(
            static fn(mixed $item): UploadsPerDay => UploadsPerDay::fromArray((array) $item),
            $data,
        );
    }

    /**
     * @return VectorsPerWeek[]
     * @throws ApiException
     */
    public function vectorsPerWeek(int $weeks = 6): array
    {
        /** @var array<array<string, mixed>> $data */
        $data = $this->doGetItems('/api/v1/stats/vectors-per-week', ['weeks' => $weeks]);
        return array_map(
            static fn(mixed $item): VectorsPerWeek => VectorsPerWeek::fromArray((array) $item),
            $data,
        );
    }

    // ── Progress ──────────────────────────────────────────────────────────

    /**
     * @return JobProgress[]
     * @throws ApiException
     */
    public function activeJobs(): array
    {
        /** @var array<array<string, mixed>> $data */
        $data = $this->doGetAllItems('/api/v1/progress/active');
        return array_map(
            static fn(mixed $item): JobProgress => JobProgress::fromArray((array) $item),
            $data,
        );
    }

    /** @throws ApiException */
    public function jobProgress(string $documentId, int $version): JobProgress
    {
        /** @var array<string, mixed> $data */
        $data = $this->doGet("/api/v1/progress/{$documentId}/versions/{$version}");
        return JobProgress::fromArray($data);
    }

    /**
     * Request cancellation of an in-progress ingestion job.
     * Returns `['ok' => true]` on success.
     *
     * @return array<string, mixed>
     * @throws ApiException
     */
    public function cancelIngestion(string $documentId, int $version): array
    {
        return $this->doPostQuery(
            "/api/v1/progress/{$documentId}/versions/{$version}/cancel",
        );
    }

    // ── Logs ──────────────────────────────────────────────────────────────

    /**
     * @param  array<string, scalar|null> $params  page, page_size, order_by, order_dir, from_ts, to_ts, etc.
     * @throws ApiException
     */
    public function logs(array $params = []): LogList
    {
        if (isset($params['from_ts']) && $params['from_ts'] instanceof \DateTimeInterface) {
            $params['from_ts'] = $params['from_ts']->format(\DateTimeInterface::ATOM);
        }
        if (isset($params['to_ts']) && $params['to_ts'] instanceof \DateTimeInterface) {
            $params['to_ts'] = $params['to_ts']->format(\DateTimeInterface::ATOM);
        }

        /** @var array<string, mixed> $data */
        $data = $this->doGet('/api/v1/logs', $params);
        return LogList::fromArray($data);
    }

    /** @throws ApiException */
    public function logFacets(): LogFacets
    {
        /** @var array<string, mixed> $data */
        $data = $this->doGet('/api/v1/logs/facets');
        return LogFacets::fromArray($data);
    }

    /**
     * @param  \DateTimeInterface|string|null $fromTs
     * @param  \DateTimeInterface|string|null $toTs
     * @throws ApiException
     */
    public function logSummary(
        \DateTimeInterface|string|null $fromTs = null,
        \DateTimeInterface|string|null $toTs   = null,
    ): LogSummary {
        $params = [];
        if ($fromTs !== null) {
            $params['from_ts'] = $fromTs instanceof \DateTimeInterface
                ? $fromTs->format(\DateTimeInterface::ATOM)
                : $fromTs;
        }
        if ($toTs !== null) {
            $params['to_ts'] = $toTs instanceof \DateTimeInterface
                ? $toTs->format(\DateTimeInterface::ATOM)
                : $toTs;
        }

        /** @var array<string, mixed> $data */
        $data = $this->doGet('/api/v1/logs/summary', $params);
        return LogSummary::fromArray($data);
    }

    /**
     * Export logs as raw bytes (JSON or CSV).
     *
     * @param  array<string, scalar|null> $params  format, limit, from_ts, to_ts, etc.
     * @throws ApiException
     */
    public function exportLogs(
        LogExportFormat $format = LogExportFormat::Json,
        int             $limit  = 10000,
        array           $params = [],
    ): string {
        return $this->doGetBinary('/api/v1/logs/export', array_merge($params, [
            'format' => $format->value,
            'limit'  => $limit,
        ]));
    }
}
