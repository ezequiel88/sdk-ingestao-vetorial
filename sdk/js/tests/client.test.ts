import { describe, it, expect, vi, afterEach } from 'vitest';
import { IngestaoVetorialClient, ApiError } from '../src/index';
import type {
  Collection,
  Document,
  DocumentDetail,
  SearchResult,
  Tag,
  DashboardStats,
  DashboardOverview,
  JobProgress,
  LogList,
  LogFacets,
  LogIngestResponse,
  TokenUsageList,
  UploadResponse,
} from '../src/index';

const BASE = 'http://localhost:8000';
const KEY = 'test-key';

function paginated<T>(items: T[], extraMeta?: Record<string, unknown>) {
  return {
    items,
    meta: {
      skip: 0,
      limit: items.length || 100,
      total: items.length,
      has_more: false,
      ...extraMeta,
    },
  };
}

function stubFetch(data: unknown, status = 200): ReturnType<typeof vi.fn> {
  const body = data instanceof ArrayBuffer ? data : JSON.stringify(data);
  const headers: Record<string, string> =
    data instanceof ArrayBuffer
      ? { 'Content-Type': 'application/octet-stream' }
      : { 'Content-Type': 'application/json' };

  return vi.fn().mockResolvedValue(new Response(body, { status, headers }));
}

function capturedUrl(): string {
  const calls = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls;
  const first = calls[0];
  if (!first) throw new Error('fetch was not called');
  return first[0] as string;
}

function capturedInit(): RequestInit {
  const calls = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls;
  const first = calls[0];
  if (!first) throw new Error('fetch was not called');
  return (first[1] ?? {}) as RequestInit;
}

afterEach(() => vi.unstubAllGlobals());

// ─────────────────────────────────────────────────────────────────────────────
describe('IngestaoVetorialClient — Collections', () => {
  it('lists collections and forwards query params', async () => {
    const payload: Collection[] = [
      {
        id: 'col-1',
        name: 'My Collection',
        alias: 'my-col',
        description: null,
        is_public: false,
        embedding_model: 'text-embedding-3-small',
        dimension: 1536,
        chunk_size: 1400,
        chunk_overlap: 250,
        created_at: '2024-01-01T00:00:00Z',
        document_count: 3,
        user_id: null,
        project_id: null,
      },
    ];
    vi.stubGlobal('fetch', stubFetch(paginated(payload)));

    const client = new IngestaoVetorialClient(BASE, KEY);
    const result = await client.collections({ skip: 0, limit: 10 });

    expect(result).toEqual(payload);
    expect(capturedUrl()).toContain('/api/v1/collections');
    expect(capturedUrl()).toContain('skip=0');
    expect(capturedUrl()).toContain('limit=10');
  });

  it('sets X-API-Key header on every request', async () => {
    vi.stubGlobal('fetch', stubFetch(paginated([])));
    const client = new IngestaoVetorialClient(BASE, KEY);
    await client.collections();

    const headers = capturedInit().headers as Record<string, string>;
    expect(headers['X-API-Key']).toBe(KEY);
  });

  it('creates a collection via POST with JSON body', async () => {
    const col: Collection = {
      id: 'new-col',
      name: 'Test',
      alias: 'test',
      description: 'desc',
      is_public: false,
      embedding_model: 'text-embedding-3-small',
      dimension: 1536,
      chunk_size: 1400,
      chunk_overlap: 250,
      created_at: '2024-01-01T00:00:00Z',
      document_count: 0,
      user_id: null,
      project_id: null,
    };
    vi.stubGlobal('fetch', stubFetch(col));

    const client = new IngestaoVetorialClient(BASE, KEY);
    const result = await client.createCollection({
      name: 'Test',
      embedding_model: 'text-embedding-3-small',
      dimension: 1536,
    });

    expect(result.id).toBe('new-col');
    expect(capturedInit().method).toBe('POST');
  });

  it('deleteCollection issues a DELETE request', async () => {
    vi.stubGlobal('fetch', stubFetch({}, 200));
    const client = new IngestaoVetorialClient(BASE, KEY);
    await client.deleteCollection('col-1');

    expect(capturedInit().method).toBe('DELETE');
    expect(capturedUrl()).toContain('/api/v1/collections/col-1');
  });
});

// ─────────────────────────────────────────────────────────────────────────────
describe('IngestaoVetorialClient — Documents', () => {
  it('fetches document list', async () => {
    const docs: Document[] = [
      {
        id: 'doc-1',
        name: 'file.pdf',
        size: '1.2 MB',
        uploaded_at: '2024-01-01T00:00:00Z',
        vector_count: 100,
        chunk_count: 10,
        version: 1,
        collection_id: 'col-1',
        tags: ['pdf'],
        version_count: 1,
      },
    ];
    vi.stubGlobal('fetch', stubFetch(paginated(docs)));

    const client = new IngestaoVetorialClient(BASE, KEY);
    const result = await client.documents({ collection_id: 'col-1' });

    expect(result).toHaveLength(1);
    expect(result[0]?.id).toBe('doc-1');
    expect(capturedUrl()).toContain('collection_id=col-1');
  });

  it('fetches a single document detail', async () => {
    const detail: DocumentDetail = {
      id: 'doc-1',
      name: 'file.pdf',
      size: '1.2 MB',
      uploaded_at: '2024-01-01T00:00:00Z',
      vector_count: 100,
      chunk_count: 10,
      version: 1,
      collection_id: 'col-1',
      tags: [],
      version_count: 1,
      checksum: 'abc123',
      metadata: {
        document_type: 'pdf',
        description: null,
        tags: [],
        custom_fields: [],
      },
      versions: [],
    };
    vi.stubGlobal('fetch', stubFetch(detail));

    const client = new IngestaoVetorialClient(BASE, KEY);
    const result = await client.document('doc-1');

    expect(result.checksum).toBe('abc123');
    expect(result.metadata.document_type).toBe('pdf');
  });

  it('downloads document markdown as ArrayBuffer', async () => {
    const buffer = new ArrayBuffer(8);
    vi.stubGlobal('fetch', stubFetch(buffer));

    const client = new IngestaoVetorialClient(BASE, KEY);
    const result = await client.documentMarkdown('doc-1', 2);

    expect(result).toBeInstanceOf(ArrayBuffer);
    expect(capturedUrl()).toContain('/api/v1/documents/doc-1/markdown');
    expect(capturedUrl()).toContain('version=2');
  });

  it('reprocess sends params on query string via POST', async () => {
    const resp: UploadResponse = {
      success: true,
      document_id: 'doc-1',
      vector_count: 0,
      version: 2,
      message: null,
    };
    vi.stubGlobal('fetch', stubFetch(resp));

    const client = new IngestaoVetorialClient(BASE, KEY);
    await client.reprocessDocument('doc-1', { mode: 'replace', source_version: 1 });

    expect(capturedInit().method).toBe('POST');
    expect(capturedUrl()).toContain('mode=replace');
    expect(capturedUrl()).toContain('source_version=1');
    // Body should be empty (params on query string)
    expect(capturedInit().body).toBeUndefined();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
describe('IngestaoVetorialClient — Upload', () => {
  it('serialises metadata as JSON string in FormData', async () => {
    const resp: UploadResponse = {
      success: true,
      document_id: 'doc-new',
      vector_count: 0,
      version: 1,
      message: null,
    };
    vi.stubGlobal('fetch', stubFetch(resp));

    const client = new IngestaoVetorialClient(BASE, KEY);
    const blob = new Blob(['hello'], { type: 'text/plain' });
    await client.upload(
      { blob, name: 'hello.txt' },
      {
        collection_id: 'col-1',
        metadata: { document_type: 'text', tags: ['demo'] },
        overwrite_existing: true,
      },
    );

    const body = capturedInit().body as FormData;
    expect(body).toBeInstanceOf(FormData);

    const metadata = JSON.parse(body.get('metadata') as string) as {
      document_type: string;
      tags: string[];
    };
    expect(metadata.document_type).toBe('text');
    expect(metadata.tags).toContain('demo');
    expect(body.get('overwrite_existing')).toBe('true');
    expect(body.get('collection_id')).toBe('col-1');
  });
});

// ─────────────────────────────────────────────────────────────────────────────
describe('IngestaoVetorialClient — Search', () => {
  it('passes query and params correctly', async () => {
    const results: SearchResult[] = [
      {
        id: 'r-1',
        type: 'chunk',
        document_name: 'doc.pdf',
        collection_id: 'col-1',
        collection_name: 'My Col',
        chunk_index: 0,
        content: 'relevant text',
        score: 0.92,
        metadata: {},
      },
    ];
    vi.stubGlobal('fetch', stubFetch(paginated(results)));

    const client = new IngestaoVetorialClient(BASE, KEY);
    const res = await client.search('machine learning', {
      collection_id: 'col-1',
      limit: 5,
      min_score: 0.8,
    });

    expect(res[0]?.score).toBe(0.92);
    expect(capturedUrl()).toContain('query=machine+learning');
    expect(capturedUrl()).toContain('collection_id=col-1');
    expect(capturedUrl()).toContain('min_score=0.8');
  });
});

// ─────────────────────────────────────────────────────────────────────────────
describe('IngestaoVetorialClient — Tags', () => {
  it('lists tags as string array', async () => {
    vi.stubGlobal('fetch', stubFetch(paginated(['tag1', 'tag2'])));
    const client = new IngestaoVetorialClient(BASE, KEY);
    const result = await client.tags();
    expect(result).toEqual(['tag1', 'tag2']);
  });

  it('creates a tag and returns Tag object', async () => {
    const tag: Tag = { id: 'tag-uuid', name: 'new-tag' };
    vi.stubGlobal('fetch', stubFetch(tag));
    const client = new IngestaoVetorialClient(BASE, KEY);
    const result = await client.createTag('new-tag');
    expect(result.id).toBe('tag-uuid');
    expect(capturedInit().method).toBe('POST');
  });
});

// ─────────────────────────────────────────────────────────────────────────────
describe('IngestaoVetorialClient — Stats', () => {
  it('returns dashboard overview', async () => {
    const overview: DashboardOverview = {
      summary: {
        total_collections: 4,
        total_documents: 20,
        total_vectors: 1000,
        total_size_mb: 50.5,
      },
      recent_activity: [],
      top_collections: [],
      uploads_per_day: [],
      vectors_per_week: [],
      logs_overview: {
        total: 10,
        by_level: { INFO: 8 },
        by_app: { api: 10 },
        top_endpoints: [],
      },
    };
    vi.stubGlobal('fetch', stubFetch(overview));
    const client = new IngestaoVetorialClient(BASE, KEY);
    const result = await client.dashboardOverview();
    expect(result.summary.total_vectors).toBe(1000);
  });

  it('returns typed dashboard stats', async () => {
    const stats: DashboardStats = {
      total_collections: 4,
      total_documents: 20,
      total_vectors: 1000,
      total_size_mb: 50.5,
    };
    vi.stubGlobal('fetch', stubFetch(stats));
    const client = new IngestaoVetorialClient(BASE, KEY);
    const result = await client.dashboardStats();
    expect(result.total_vectors).toBe(1000);
  });
});

// ─────────────────────────────────────────────────────────────────────────────
describe('IngestaoVetorialClient — Progress', () => {
  it('returns active jobs array', async () => {
    const jobs: JobProgress[] = [
      {
        job_id: 'job-1',
        document_id: 'doc-1',
        version: 1,
        status: 'chunking',
        percent: 45,
        processed_chunks: 9,
        total_chunks: 20,
        started_at: 1700000000,
        updated_at: 1700000010,
        eta_seconds: 11,
        message: 'Processing…',
        document_name: 'doc.pdf',
        collection_id: 'col-1',
        error: '',
      },
    ];
    vi.stubGlobal('fetch', stubFetch(paginated(jobs)));
    const client = new IngestaoVetorialClient(BASE, KEY);
    const result = await client.activeJobs();
    expect(result[0]?.status).toBe('chunking');
  });

  it('cancelIngestion posts to the correct URL with no body', async () => {
    vi.stubGlobal('fetch', stubFetch({ ok: true }));
    const client = new IngestaoVetorialClient(BASE, KEY);
    const result = await client.cancelIngestion('doc-1', 1);

    expect(result.ok).toBe(true);
    expect(capturedInit().method).toBe('POST');
    expect(capturedUrl()).toContain('/api/v1/progress/doc-1/versions/1/cancel');
  });

  it('streamProgress returns the raw response body stream', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        new Response('data: ping\n\n', { status: 200 }),
      ),
    );
    const client = new IngestaoVetorialClient(BASE, KEY);
    const result = await client.streamProgress();
    expect(result).not.toBeNull();
    expect(capturedUrl()).toContain('/api/v1/progress/stream');
  });
});

// ─────────────────────────────────────────────────────────────────────────────
describe('IngestaoVetorialClient — Logs', () => {
  it('converts Date to ISO string in params', async () => {
    const logList: LogList = {
      items: [],
      meta: { page: 1, pageSize: 50, total: 0 },
    };
    vi.stubGlobal('fetch', stubFetch(logList));
    const client = new IngestaoVetorialClient(BASE, KEY);
    const date = new Date('2024-01-15T10:00:00Z');
    await client.logs({ from_ts: date });

    expect(capturedUrl()).toContain('from_ts=2024-01-15T10%3A00%3A00.000Z');
  });

  it('returns log facets', async () => {
    const facets: LogFacets = {
      apps: ['api', 'worker'],
      endpoints: ['/search'],
      projects: [],
      users: [],
    };
    vi.stubGlobal('fetch', stubFetch(facets));
    const client = new IngestaoVetorialClient(BASE, KEY);
    const result = await client.logFacets();
    expect(result.apps).toContain('api');
  });

  it('exportLogs returns ArrayBuffer with format param', async () => {
    const buffer = new ArrayBuffer(16);
    vi.stubGlobal('fetch', stubFetch(buffer));
    const client = new IngestaoVetorialClient(BASE, KEY);
    const result = await client.exportLogs({ format: 'csv' });

    expect(result).toBeInstanceOf(ArrayBuffer);
    expect(capturedUrl()).toContain('format=csv');
    expect(capturedUrl()).toContain('limit=10000');
  });

  it('ingestLogs posts payload and optional sink token', async () => {
    const resultPayload: LogIngestResponse = { accepted: 1 };
    vi.stubGlobal('fetch', stubFetch(resultPayload));
    const client = new IngestaoVetorialClient(BASE, KEY);
    const result = await client.ingestLogs(
      [{ nivel: 'INFO', modulo: 'sdk', acao: 'startup', detalhes: { app: 'external' } }],
      'sink-token',
    );
    expect(result.accepted).toBe(1);
    expect(capturedInit().method).toBe('POST');
    const headers = capturedInit().headers as Record<string, string>;
    expect(headers['X-Log-Sink-Token']).toBe('sink-token');
  });
});

// ─────────────────────────────────────────────────────────────────────────────
describe('IngestaoVetorialClient — Token usage', () => {
  it('returns usage summary and forwards filters', async () => {
    const payload: TokenUsageList = {
      items: [],
      meta: { page: 1, pageSize: 50, total: 0 },
      summary: {
        totalRecords: 0,
        totalInputTokens: 0,
        totalOutputTokens: 0,
        totalTokens: 0,
        providers: [],
      },
    };
    vi.stubGlobal('fetch', stubFetch(payload));
    const client = new IngestaoVetorialClient(BASE, KEY);
    const result = await client.tokenUsage({ provider: 'openai', status: 'success' });
    expect(result.summary.totalTokens).toBe(0);
    expect(capturedUrl()).toContain('provider=openai');
    expect(capturedUrl()).toContain('status=success');
  });
});

// ─────────────────────────────────────────────────────────────────────────────
describe('IngestaoVetorialClient — Error handling', () => {
  it('throws ApiError with statusCode and body on 4xx', async () => {
    const client = new IngestaoVetorialClient(BASE, KEY);

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        new Response('{"detail":"Not found"}', { status: 404 }),
      ),
    );
    try {
      await client.getCollection('bad-id');
      expect.fail('should have thrown');
    } catch (err) {
      expect(err).toBeInstanceOf(ApiError);
      expect((err as ApiError).statusCode).toBe(404);
    }
  });

  it('throws ApiError on 500', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        new Response('Internal Server Error', { status: 500 }),
      ),
    );
    const client = new IngestaoVetorialClient(BASE, KEY);
    await expect(client.dashboardStats()).rejects.toThrow(ApiError);
  });
});
