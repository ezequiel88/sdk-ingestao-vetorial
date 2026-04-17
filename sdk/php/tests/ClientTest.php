<?php

declare(strict_types=1);

namespace IngestaoVetorial\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use IngestaoVetorial\Client;
use IngestaoVetorial\DTO\Collection;
use IngestaoVetorial\DTO\DashboardStats;
use IngestaoVetorial\DTO\Document;
use IngestaoVetorial\DTO\DocumentDetail;
use IngestaoVetorial\DTO\JobProgress;
use IngestaoVetorial\DTO\LogFacets;
use IngestaoVetorial\DTO\LogList;
use IngestaoVetorial\DTO\SearchResult;
use IngestaoVetorial\DTO\Tag;
use IngestaoVetorial\DTO\UploadOptions;
use IngestaoVetorial\DTO\UploadResponse;
use IngestaoVetorial\Enums\ReprocessMode;
use IngestaoVetorial\Exceptions\ApiException;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private function makeClient(MockHandler $mock): Client
    {
        $stack  = HandlerStack::create($mock);
        $guzzle = new GuzzleClient(['handler' => $stack]);

        return new Client('http://localhost:8000', 'test-key', 30.0, $guzzle);
    }

    private function jsonResponse(mixed $data, int $status = 200): Response
    {
        return new Response(
            $status,
            ['Content-Type' => 'application/json'],
            json_encode($data, JSON_THROW_ON_ERROR),
        );
    }

    private function paginated(array $items, bool $hasMore = false): array
    {
        return [
            'items' => $items,
            'meta' => [
                'skip' => 0,
                'limit' => count($items) > 0 ? count($items) : 100,
                'total' => count($items),
                'has_more' => $hasMore,
            ],
        ];
    }

    // ── Collections ───────────────────────────────────────────────────────

    public function testCollectionsReturnTypedArray(): void
    {
        $payload = [[
            'id' => 'col-1', 'name' => 'My Col', 'alias' => 'my-col',
            'description' => null, 'is_public' => false,
            'embedding_model' => 'text-embedding-3-small', 'dimension' => 1536,
            'chunk_size' => 1400, 'chunk_overlap' => 250,
            'created_at' => '2024-01-01T00:00:00Z', 'document_count' => 3,
            'user_id' => null, 'project_id' => null,
        ]];

        $mock   = new MockHandler([$this->jsonResponse($payload)]);
        $client = $this->makeClient($mock);
        $result = $client->collections();

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Collection::class, $result[0]);
        $this->assertSame('col-1', $result[0]->id);
        $this->assertSame(1536, $result[0]->dimension);
    }

    public function testCreateCollectionReturnsCollection(): void
    {
        $payload = [
            'id' => 'new-col', 'name' => 'Test', 'alias' => 'test',
            'description' => 'desc', 'is_public' => false,
            'embedding_model' => 'text-embedding-3-small', 'dimension' => 1536,
            'chunk_size' => 1400, 'chunk_overlap' => 250,
            'created_at' => '2024-01-01T00:00:00Z', 'document_count' => 0,
            'user_id' => null, 'project_id' => null,
        ];

        $mock   = new MockHandler([$this->jsonResponse($payload)]);
        $client = $this->makeClient($mock);
        $result = $client->createCollection([
            'name'            => 'Test',
            'embedding_model' => 'text-embedding-3-small',
            'dimension'       => 1536,
        ]);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertSame('new-col', $result->id);
    }

    public function testDeleteCollectionSendsNoResponse(): void
    {
        $mock   = new MockHandler([new Response(204)]);
        $client = $this->makeClient($mock);

        // Should not throw
        $client->deleteCollection('col-1');
        $this->assertTrue(true);
    }

    // ── Documents ─────────────────────────────────────────────────────────

    public function testDocumentsReturnTypedArray(): void
    {
        $payload = [[
            'id' => 'doc-1', 'name' => 'file.pdf', 'size' => '1.2 MB',
            'uploaded_at' => '2024-01-01T00:00:00Z', 'vector_count' => 100,
            'chunk_count' => 10, 'version' => 1, 'collection_id' => 'col-1',
            'tags' => ['pdf'], 'version_count' => 1,
        ]];

        $mock   = new MockHandler([$this->jsonResponse($payload)]);
        $client = $this->makeClient($mock);
        $result = $client->documents();

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Document::class, $result[0]);
        $this->assertSame('doc-1', $result[0]->id);
        $this->assertContains('pdf', $result[0]->tags);
    }

    public function testDocumentDetailReturnsDTOWithNestedObjects(): void
    {
        $payload = [
            'id' => 'doc-1', 'name' => 'file.pdf', 'size' => '1.2 MB',
            'uploaded_at' => '2024-01-01T00:00:00Z', 'vector_count' => 100,
            'chunk_count' => 10, 'version' => 1, 'collection_id' => 'col-1',
            'tags' => [], 'version_count' => 2,
            'checksum' => 'abc123',
            'metadata' => [
                'document_type' => 'pdf',
                'description'   => 'A test document',
                'tags'          => [],
                'custom_fields' => [],
            ],
            'versions' => [
                ['version' => 1, 'uploaded_at' => '2024-01-01T00:00:00Z', 'vector_count' => 100, 'checksum' => 'abc123', 'is_active' => false],
                ['version' => 2, 'uploaded_at' => '2024-01-02T00:00:00Z', 'vector_count' => 110, 'checksum' => 'def456', 'is_active' => true],
            ],
        ];

        $mock   = new MockHandler([$this->jsonResponse($payload)]);
        $client = $this->makeClient($mock);
        $result = $client->document('doc-1');

        $this->assertInstanceOf(DocumentDetail::class, $result);
        $this->assertSame('abc123', $result->checksum);
        $this->assertSame('pdf', $result->metadata->document_type);
        $this->assertCount(2, $result->versions);
        $this->assertTrue($result->versions[1]->is_active);
    }

    public function testDocumentMarkdownReturnsBinaryString(): void
    {
        $binaryContent = '%PDF-1.4 binary content here';
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'text/plain'], $binaryContent),
        ]);
        $client = $this->makeClient($mock);
        $result = $client->documentMarkdown('doc-1', 1);

        $this->assertSame($binaryContent, $result);
    }

    public function testReprocessDocumentUsesQueryString(): void
    {
        $payload = ['success' => true, 'document_id' => 'doc-1', 'vector_count' => 0, 'version' => 2, 'message' => null];
        $container = [];

        $history = \GuzzleHttp\Middleware::history($container);
        $mock    = new MockHandler([$this->jsonResponse($payload)]);
        $stack   = HandlerStack::create($mock);
        $stack->push($history);
        $guzzle = new GuzzleClient(['handler' => $stack]);
        $client = new Client('http://localhost:8000', 'key', 30.0, $guzzle);

        $result = $client->reprocessDocument('doc-1', ReprocessMode::Replace, 1);

        $this->assertInstanceOf(UploadResponse::class, $result);

        /** @var \GuzzleHttp\Psr7\Request $req */
        $req = $container[0]['request'];
        $this->assertSame('POST', $req->getMethod());
        $this->assertStringContainsString('mode=replace', $req->getUri()->getQuery());
        $this->assertStringContainsString('source_version=1', $req->getUri()->getQuery());
        $this->assertSame('', (string) $req->getBody());
    }

    // ── Search ────────────────────────────────────────────────────────────

    public function testSearchReturnsTypedResults(): void
    {
        $payload = [[
            'id' => 'r-1', 'type' => 'chunk', 'document_name' => 'doc.pdf',
            'collection_id' => 'col-1', 'collection_name' => 'My Col',
            'chunk_index' => 0, 'content' => 'relevant text', 'score' => 0.92,
            'metadata' => [],
        ]];

        $mock   = new MockHandler([$this->jsonResponse($payload)]);
        $client = $this->makeClient($mock);
        $result = $client->search('machine learning', 'col-1');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(SearchResult::class, $result[0]);
        $this->assertEqualsWithDelta(0.92, $result[0]->score, 0.001);
    }

    // ── Tags ──────────────────────────────────────────────────────────────

    public function testTagsReturnsStringArray(): void
    {
        $mock   = new MockHandler([$this->jsonResponse($this->paginated(['tag1', 'tag2']))]);
        $client = $this->makeClient($mock);
        $result = $client->tags();

        $this->assertSame(['tag1', 'tag2'], $result);
    }

    public function testCreateTagReturnsTagDTO(): void
    {
        $mock   = new MockHandler([$this->jsonResponse(['id' => 'tag-uuid', 'name' => 'new-tag'])]);
        $client = $this->makeClient($mock);
        $result = $client->createTag('new-tag');

        $this->assertInstanceOf(Tag::class, $result);
        $this->assertSame('tag-uuid', $result->id);
    }

    // ── Stats ─────────────────────────────────────────────────────────────

    public function testDashboardStatsReturnsTypedDTO(): void
    {
        $payload = [
            'total_collections' => 4,
            'total_documents'   => 20,
            'total_vectors'     => 1000,
            'total_size_mb'     => 50.5,
        ];

        $mock   = new MockHandler([$this->jsonResponse($payload)]);
        $client = $this->makeClient($mock);
        $result = $client->dashboardStats();

        $this->assertInstanceOf(DashboardStats::class, $result);
        $this->assertSame(1000, $result->total_vectors);
        $this->assertEqualsWithDelta(50.5, $result->total_size_mb, 0.001);
    }

    // ── Progress ──────────────────────────────────────────────────────────

    public function testActiveJobsReturnTypedArray(): void
    {
        $payload = [[
            'job_id' => 'job-1', 'document_id' => 'doc-1', 'version' => 1,
            'status' => 'chunking', 'percent' => 45.0,
            'processed_chunks' => 9, 'total_chunks' => 20,
            'started_at' => 1700000000.0, 'updated_at' => 1700000010.0,
            'eta_seconds' => 11.0, 'message' => 'Processing…',
            'document_name' => 'doc.pdf', 'collection_id' => 'col-1', 'error' => '',
        ]];

        $mock   = new MockHandler([$this->jsonResponse($this->paginated($payload))]);
        $client = $this->makeClient($mock);
        $result = $client->activeJobs();

        $this->assertCount(1, $result);
        $this->assertInstanceOf(JobProgress::class, $result[0]);
        $this->assertSame('chunking', $result[0]->status);
        $this->assertEqualsWithDelta(45.0, $result[0]->percent, 0.001);
    }

    public function testCancelIngestionIssuesPostRequest(): void
    {
        $container = [];
        $history   = \GuzzleHttp\Middleware::history($container);
        $mock      = new MockHandler([$this->jsonResponse(['ok' => true])]);
        $stack     = HandlerStack::create($mock);
        $stack->push($history);
        $guzzle = new GuzzleClient(['handler' => $stack]);
        $client = new Client('http://localhost:8000', 'key', 30.0, $guzzle);

        $result = $client->cancelIngestion('doc-1', 1);

        $this->assertTrue((bool) $result['ok']);

        /** @var \GuzzleHttp\Psr7\Request $req */
        $req = $container[0]['request'];
        $this->assertSame('POST', $req->getMethod());
        $this->assertStringContainsString('/api/v1/progress/doc-1/versions/1/cancel', (string) $req->getUri());
    }

    // ── Logs ──────────────────────────────────────────────────────────────

    public function testLogsReturnsLogListDTO(): void
    {
        $payload = [
            'items' => [
                [
                    'id' => 'log-1', 'timestamp' => '2024-01-01T00:00:00Z',
                    'requestId' => null, 'nivel' => 'INFO',
                    'modulo' => 'api', 'acao' => 'search',
                    'detalhes' => [], 'request' => null, 'response' => null,
                    'usuarioId' => null, 'projetoId' => null, 'tempoExecucao' => 42,
                ],
            ],
            'meta' => ['page' => 1, 'pageSize' => 50, 'total' => 1],
        ];

        $mock   = new MockHandler([$this->jsonResponse($payload)]);
        $client = $this->makeClient($mock);
        $result = $client->logs();

        $this->assertInstanceOf(LogList::class, $result);
        $this->assertCount(1, $result->items);
        $this->assertSame(1, $result->meta->total);
        $this->assertSame(42, $result->items[0]->tempoExecucao);
    }

    public function testLogFacetsReturnsTypedDTO(): void
    {
        $payload = [
            'apps'      => ['api', 'worker'],
            'endpoints' => ['/search'],
            'projects'  => [],
            'users'     => [],
        ];

        $mock   = new MockHandler([$this->jsonResponse($payload)]);
        $client = $this->makeClient($mock);
        $result = $client->logFacets();

        $this->assertInstanceOf(LogFacets::class, $result);
        $this->assertContains('api', $result->apps);
    }

    public function testExportLogsReturnsBinaryString(): void
    {
        $csvContent = "id,timestamp,nivel\nlog-1,2024-01-01,INFO\n";
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'text/csv'], $csvContent),
        ]);
        $client = $this->makeClient($mock);
        $result = $client->exportLogs(\IngestaoVetorial\Enums\LogExportFormat::Csv);

        $this->assertSame($csvContent, $result);
    }

    // ── Error handling ────────────────────────────────────────────────────

    public function testThrowsApiExceptionOn404(): void
    {
        $mock = new MockHandler([
            new RequestException(
                'Not found',
                new Request('GET', '/api/v1/collections/bad'),
                new Response(404, [], '{"detail":"Not found"}'),
            ),
        ]);

        $client = $this->makeClient($mock);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(404);

        $client->getCollection('bad');
    }

    public function testApiExceptionExposesStatusCodeAndBody(): void
    {
        $mock = new MockHandler([
            new RequestException(
                'Server error',
                new Request('GET', '/api/v1/stats/dashboard'),
                new Response(500, [], 'Internal Server Error'),
            ),
        ]);

        $client = $this->makeClient($mock);

        try {
            $client->dashboardStats();
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(500, $e->statusCode());
            $this->assertSame('Internal Server Error', $e->responseBody());
        }
    }

    // ── Upload ────────────────────────────────────────────────────────────

    public function testUploadSerializesMetadataAsJsonString(): void
    {
        $payload = ['success' => true, 'document_id' => 'doc-new', 'vector_count' => 0, 'version' => 1, 'message' => null];

        $container = [];
        $history   = \GuzzleHttp\Middleware::history($container);
        $mock      = new MockHandler([$this->jsonResponse($payload)]);
        $stack     = HandlerStack::create($mock);
        $stack->push($history);
        $guzzle  = new GuzzleClient(['handler' => $stack]);
        $client  = new Client('http://localhost:8000', 'key', 30.0, $guzzle);

        // Create a temporary file
        $tmpFile = tempnam(sys_get_temp_dir(), 'sdk_test_') . '.txt';
        file_put_contents($tmpFile, 'hello world');

        $options = new UploadOptions(
            collectionId:  'col-1',
            documentType:  'text',
            tags:          ['demo'],
            overwriteExisting: true,
        );

        $result = $client->upload($tmpFile, $options);

        unlink($tmpFile);

        $this->assertInstanceOf(UploadResponse::class, $result);
        $this->assertTrue($result->success);

        /** @var \GuzzleHttp\Psr7\Request $req */
        $req  = $container[0]['request'];
        $body = (string) $req->getBody();

        // metadata field should be a JSON-serialised string
        $this->assertStringContainsString('"document_type":"text"', $body);
        $this->assertStringContainsString('"tags":["demo"]', $body);
        // overwrite_existing must be the string "true", not a boolean
        $this->assertStringContainsString('overwrite_existing', $body);
        $this->assertStringContainsString('true', $body);
    }
}
