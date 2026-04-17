import { ApiError } from './types';
import type {
  CancelIngestionResponse,
  Collection,
  CollectionListParams,
  CreateCollectionParams,
  DashboardOverview,
  DashboardStats,
  Document,
  DocumentChunk,
  DocumentDetail,
  DocumentListParams,
  EmbeddingModelOption,
  JobProgress,
  LogExportParams,
  LogIngestItem,
  LogIngestResponse,
  LogFacets,
  LogList,
  LogListParams,
  LogSummary,
  RecentActivity,
  ReprocessMode,
  SearchParams,
  SearchResult,
  Tag,
  TagListParams,
  TopCollection,
  UpdateCollectionParams,
  UploadFile,
  UploadOptions,
  UploadResponse,
  UploadsPerDay,
  TokenUsageList,
  TokenUsageParams,
  VectorsPerWeek,
} from './types';

export { ApiError };

// Re-export for consumers who need it from the client module
export type { UploadFile };

type Primitive = string | number | boolean;
type QueryParams = Record<string, Primitive | undefined>;
type PaginatedPayload<T> = { items: T[]; meta?: { has_more?: boolean } };

function toIso(value: string | Date): string {
  return value instanceof Date ? value.toISOString() : value;
}

function extractItems<T>(payload: T[] | PaginatedPayload<T>): T[] {
  return Array.isArray(payload) ? payload : payload.items;
}

export class IngestaoVetorialClient {
  private readonly baseUrl: string;
  private readonly apiKey: string | undefined;
  private readonly timeout: number;

  /**
   * @param baseUrl  Base URL of the API server, e.g. `http://localhost:8000`
   * @param apiKey   API key sent as `X-API-Key` on every request
   * @param timeout  Request timeout in milliseconds (default: 30 000)
   */
  constructor(baseUrl: string, apiKey?: string, timeout = 30_000) {
    this.baseUrl = baseUrl.replace(/\/+$/, '');
    this.apiKey = apiKey;
    this.timeout = timeout;
  }

  // ── Internals ────────────────────────────────────────────────────────────

  private buildHeaders(): Record<string, string> {
    const headers: Record<string, string> = { Accept: 'application/json' };
    if (this.apiKey) headers['X-API-Key'] = this.apiKey;
    return headers;
  }

  private buildUrl(path: string, params?: QueryParams): string {
    const base = `${this.baseUrl}${path}`;
    if (!params) return base;
    const qs = new URLSearchParams();
    for (const [key, val] of Object.entries(params)) {
      if (val !== undefined) qs.set(key, String(val));
    }
    const s = qs.toString();
    return s ? `${base}?${s}` : base;
  }

  private async request<T>(
    method: string,
    path: string,
    options: {
      params?: QueryParams;
      body?: unknown;
      formData?: FormData;
      binary?: true;
      headers?: Record<string, string>;
    } = {},
  ): Promise<T> {
    const url = this.buildUrl(path, options.params);
    const headers = { ...this.buildHeaders(), ...(options.headers ?? {}) };
    let fetchBody: BodyInit | undefined;

    if (options.formData) {
      fetchBody = options.formData;
      // Do NOT set Content-Type — browser/Node/RN sets it with the boundary
    } else if (options.body !== undefined) {
      headers['Content-Type'] = 'application/json';
      fetchBody = JSON.stringify(options.body);
    }

    const controller = new AbortController();
    const timerId = setTimeout(() => controller.abort(), this.timeout);

    try {
      const res = await fetch(url, {
        method,
        headers,
        body: fetchBody,
        signal: controller.signal,
      });

      if (!res.ok) {
        const text = await res.text();
        throw new ApiError(res.status, text);
      }

      if (options.binary === true) {
        return res.arrayBuffer() as unknown as T;
      }
      return res.json() as Promise<T>;
    } finally {
      clearTimeout(timerId);
    }
  }

  private get<T>(path: string, params?: QueryParams): Promise<T> {
    return this.request<T>('GET', path, { params });
  }

  private async getItems<T>(path: string, params?: QueryParams): Promise<T[]> {
    const payload = await this.get<T[] | PaginatedPayload<T>>(path, params);
    return extractItems(payload);
  }

  private async getAllItems<T>(path: string, params?: QueryParams): Promise<T[]> {
    const limit = typeof params?.limit === 'number' ? params.limit : 100;
    let skip = typeof params?.skip === 'number' ? params.skip : 0;
    const items: T[] = [];

    while (true) {
      const payload = await this.get<T[] | PaginatedPayload<T>>(path, {
        ...params,
        skip,
        limit,
      });
      const pageItems = extractItems(payload);
      items.push(...pageItems);

      if (Array.isArray(payload)) {
        if (pageItems.length < limit) break;
      } else if (!payload.meta?.has_more) {
        break;
      }

      if (pageItems.length === 0) break;
      skip += pageItems.length;
    }

    return items;
  }

  private post<T>(path: string, body?: unknown): Promise<T> {
    return this.request<T>('POST', path, { body });
  }

  private patch<T>(path: string, body: unknown): Promise<T> {
    return this.request<T>('PATCH', path, { body });
  }

  private del(path: string): Promise<void> {
    return this.request<void>('DELETE', path);
  }

  // ── Collections ──────────────────────────────────────────────────────────

  /** List available embedding models. */
  embeddingModels(): Promise<EmbeddingModelOption[]> {
    return this.getItems('/api/v1/collections/embedding-models');
  }

  /** List collections with optional filters. */
  collections(params?: CollectionListParams): Promise<Collection[]> {
    return this.getItems('/api/v1/collections', params as QueryParams);
  }

  /** Create a new collection. */
  createCollection(params: CreateCollectionParams): Promise<Collection> {
    return this.post('/api/v1/collections', params);
  }

  /** Fetch a single collection by ID. */
  getCollection(collectionId: string): Promise<Collection> {
    return this.get(`/api/v1/collections/${collectionId}`);
  }

  /** Update mutable fields of a collection. */
  updateCollection(
    collectionId: string,
    params: UpdateCollectionParams,
  ): Promise<Collection> {
    return this.patch(`/api/v1/collections/${collectionId}`, params);
  }

  /** Permanently delete a collection and all its documents. */
  deleteCollection(collectionId: string): Promise<void> {
    return this.del(`/api/v1/collections/${collectionId}`);
  }

  /** Return the raw Qdrant collection info. */
  collectionRaw(collectionId: string): Promise<unknown> {
    return this.get(`/api/v1/collections/${collectionId}/raw`);
  }

  /** List documents belonging to a specific collection. */
  collectionDocuments(
    collectionId: string,
    params?: { skip?: number; limit?: number },
  ): Promise<Document[]> {
    return this.getItems(
      `/api/v1/collections/${collectionId}/documents`,
      params as QueryParams,
    );
  }

  // ── Documents ────────────────────────────────────────────────────────────

  /** List all documents, optionally filtered by collection. */
  documents(params?: DocumentListParams): Promise<Document[]> {
    return this.getItems('/api/v1/documents', params as QueryParams);
  }

  /** Fetch full document details including versions and metadata. */
  document(documentId: string): Promise<DocumentDetail> {
    return this.get(`/api/v1/documents/${documentId}`);
  }

  /** Return all chunks (with embeddings) for a document version. */
  documentChunks(
    documentId: string,
    version?: number,
    q?: string,
  ): Promise<DocumentChunk[]> {
    return this.getAllItems(
      `/api/v1/documents/${documentId}/chunks`,
      {
        version,
        q,
      },
    );
  }

  /** Download the extracted markdown for a document version as `ArrayBuffer`. */
  documentMarkdown(
    documentId: string,
    version?: number,
  ): Promise<ArrayBuffer> {
    return this.request<ArrayBuffer>(
      'GET',
      `/api/v1/documents/${documentId}/markdown`,
      {
        params: version !== undefined ? { version } : undefined,
        binary: true,
      },
    );
  }

  /** Delete a document and all its versions. */
  deleteDocument(documentId: string): Promise<void> {
    return this.del(`/api/v1/documents/${documentId}`);
  }

  /**
   * Re-run the ingestion pipeline for an existing document.
   * Query params are sent on the query string (not the body).
   */
  reprocessDocument(
    documentId: string,
    params: {
      source_version?: number;
      mode?: ReprocessMode;
      extraction_tool?: string;
    } = {},
  ): Promise<UploadResponse> {
    return this.request<UploadResponse>(
      'POST',
      `/api/v1/documents/${documentId}/reprocess`,
      {
        params: {
          mode: params.mode ?? 'replace',
          source_version: params.source_version,
          extraction_tool: params.extraction_tool,
        },
      },
    );
  }

  /** Delete a specific version of a document. */
  deleteDocumentVersion(documentId: string, version: number): Promise<void> {
    return this.del(`/api/v1/documents/${documentId}/versions/${version}`);
  }

  /** Activate or deactivate a document version. */
  setVersionActive(
    documentId: string,
    version: number,
    isActive: boolean,
  ): Promise<DocumentDetail> {
    return this.patch(
      `/api/v1/documents/${documentId}/versions/${version}`,
      { is_active: isActive },
    );
  }

  // ── Upload ───────────────────────────────────────────────────────────────

  /**
   * Upload a file and start the ingestion pipeline.
   *
   * `metadata` is a typed object — the SDK serialises it internally to the
   * JSON string the API expects.
   *
   * React Native example:
   * ```ts
   * await client.upload(
   *   { uri: fileUri, name: 'doc.pdf', type: 'application/pdf' },
   *   { collection_id: 'abc' },
   * );
   * ```
   */
  upload(file: UploadFile, options: UploadOptions): Promise<UploadResponse> {
    const formData = new FormData();

    if (file instanceof File) {
      formData.append('file', file, file.name);
    } else if (file instanceof Blob) {
      formData.append('file', file, 'upload.bin');
    } else if ('blob' in file) {
      formData.append('file', file.blob, file.name);
    } else {
      // React Native: { uri, name, type } — RN FormData handles this shape
      formData.append('file', file as unknown as Blob, file.name);
    }

    const metadata = {
      document_type: options.metadata?.document_type ?? 'document',
      description: options.metadata?.description ?? '',
      tags: options.metadata?.tags ?? [],
      custom_fields: options.metadata?.custom_fields ?? [],
    };

    formData.append('collection_id', options.collection_id);
    formData.append('metadata', JSON.stringify(metadata));
    formData.append('overwrite_existing', String(options.overwrite_existing ?? false));

    if (options.embedding_model) {
      formData.append('embedding_model', options.embedding_model);
    }
    if (options.dimension !== undefined) {
      formData.append('dimension', String(options.dimension));
    }
    if (options.extraction_tool) {
      formData.append('extraction_tool', options.extraction_tool);
    }

    return this.request<UploadResponse>('POST', '/api/v1/upload', { formData });
  }

  // ── Search ───────────────────────────────────────────────────────────────

  /** Run a semantic search query. */
  search(query: string, params?: SearchParams): Promise<SearchResult[]> {
    return this.getItems('/api/v1/search', {
      query,
      ...(params as QueryParams),
    });
  }

  // ── Tags ─────────────────────────────────────────────────────────────────

  /** List all tags (returns plain name strings). */
  tags(params?: TagListParams): Promise<string[]> {
    return this.getItems('/api/v1/tags', params as QueryParams);
  }

  /** Search tags by partial name. */
  searchTags(q: string): Promise<string[]> {
    return this.getItems('/api/v1/tags/search', { q });
  }

  /** Create a new tag. */
  createTag(name: string): Promise<Tag> {
    return this.post('/api/v1/tags', { name });
  }

  // ── Stats ────────────────────────────────────────────────────────────────

  /** Return aggregate counts: collections, documents, vectors, size. */
  dashboardStats(): Promise<DashboardStats> {
    return this.get('/api/v1/stats/dashboard');
  }

  /** Return the full dashboard payload in a single request. */
  dashboardOverview(): Promise<DashboardOverview> {
    return this.get('/api/v1/stats/overview');
  }

  /** Return the most recent ingestion/upload activity entries. */
  recentActivity(limit = 5): Promise<RecentActivity[]> {
    return this.getItems('/api/v1/stats/activity', { limit });
  }

  /** Return the collections with the highest document/vector counts. */
  topCollections(limit = 5): Promise<TopCollection[]> {
    return this.getItems('/api/v1/stats/top-collections', { limit });
  }

  /** Return upload counts grouped by day for the last `days` days. */
  uploadsPerDay(days = 7): Promise<UploadsPerDay[]> {
    return this.getItems('/api/v1/stats/uploads-per-day', { days });
  }

  /** Return vector counts grouped by week for the last `weeks` weeks. */
  vectorsPerWeek(weeks = 6): Promise<VectorsPerWeek[]> {
    return this.getItems('/api/v1/stats/vectors-per-week', { weeks });
  }

  // ── Progress ─────────────────────────────────────────────────────────────

  /** Return all currently active ingestion jobs. */
  activeJobs(): Promise<JobProgress[]> {
    return this.getAllItems('/api/v1/progress/active');
  }

  /** Return the ingestion progress for a specific document version. */
  jobProgress(documentId: string, version: number): Promise<JobProgress> {
    return this.get(`/api/v1/progress/${documentId}/versions/${version}`);
  }

  /** Request cancellation of an in-progress ingestion job. */
  cancelIngestion(
    documentId: string,
    version: number,
  ): Promise<CancelIngestionResponse> {
    return this.post(
      `/api/v1/progress/${documentId}/versions/${version}/cancel`,
    );
  }

  /** Return the raw SSE stream for ingestion progress updates. */
  async streamProgress(): Promise<ReadableStream<Uint8Array> | null> {
    const res = await fetch(`${this.baseUrl}/api/v1/progress/stream`, {
      method: 'GET',
      headers: this.buildHeaders(),
    });
    if (!res.ok) {
      throw new ApiError(res.status, await res.text());
    }
    return res.body;
  }

  // ── Logs ─────────────────────────────────────────────────────────────────

  /**
   * Query the application log store with filters and pagination.
   * `from_ts` / `to_ts` accept ISO-8601 strings or `Date` objects.
   */
  logs(params?: LogListParams): Promise<LogList> {
    const p: QueryParams = {};
    if (params) {
      if (params.page !== undefined) p['page'] = params.page;
      if (params.page_size !== undefined) p['page_size'] = params.page_size;
      if (params.order_by !== undefined) p['order_by'] = params.order_by;
      if (params.order_dir !== undefined) p['order_dir'] = params.order_dir;
      if (params.from_ts !== undefined) p['from_ts'] = toIso(params.from_ts);
      if (params.to_ts !== undefined) p['to_ts'] = toIso(params.to_ts);
      if (params.nivel !== undefined) p['nivel'] = params.nivel;
      if (params.app !== undefined) p['app'] = params.app;
      if (params.endpoint !== undefined) p['endpoint'] = params.endpoint;
      if (params.status_code !== undefined) p['status_code'] = params.status_code;
      if (params.q !== undefined) p['q'] = params.q;
      if (params.user_id !== undefined) p['user_id'] = params.user_id;
      if (params.session_id !== undefined) p['session_id'] = params.session_id;
      if (params.project_ids !== undefined) p['project_ids'] = params.project_ids;
    }
    return this.get('/api/v1/logs', p);
  }

  /** Return distinct values for log filter fields. */
  logFacets(): Promise<LogFacets> {
    return this.get('/api/v1/logs/facets');
  }

  /** Return aggregated log statistics for a time window. */
  logSummary(params?: {
    from_ts?: string | Date;
    to_ts?: string | Date;
  }): Promise<LogSummary> {
    const p: QueryParams = {};
    if (params?.from_ts !== undefined) p['from_ts'] = toIso(params.from_ts);
    if (params?.to_ts !== undefined) p['to_ts'] = toIso(params.to_ts);
    return this.get(
      '/api/v1/logs/summary',
      Object.keys(p).length > 0 ? p : undefined,
    );
  }

  /**
   * Export logs as raw bytes.
   * Returns an `ArrayBuffer` — decode or write to a file directly.
   */
  exportLogs(params?: LogExportParams): Promise<ArrayBuffer> {
    const p: QueryParams = {
      format: params?.format ?? 'json',
      limit: params?.limit ?? 10000,
    };
    if (params) {
      if (params.from_ts !== undefined) p['from_ts'] = toIso(params.from_ts);
      if (params.to_ts !== undefined) p['to_ts'] = toIso(params.to_ts);
      if (params.nivel !== undefined) p['nivel'] = params.nivel;
      if (params.app !== undefined) p['app'] = params.app;
      if (params.endpoint !== undefined) p['endpoint'] = params.endpoint;
      if (params.status_code !== undefined) p['status_code'] = params.status_code;
      if (params.q !== undefined) p['q'] = params.q;
      if (params.user_id !== undefined) p['user_id'] = params.user_id;
      if (params.session_id !== undefined) p['session_id'] = params.session_id;
      if (params.project_ids !== undefined) p['project_ids'] = params.project_ids;
    }
    return this.request<ArrayBuffer>('GET', '/api/v1/logs/export', {
      params: p,
      binary: true,
    });
  }

  /** Ingest external logs into the backend log store. */
  ingestLogs(
    payload: LogIngestItem[],
    logSinkToken?: string,
  ): Promise<LogIngestResponse> {
    const normalizedPayload = payload.map((item) => ({
      ...item,
      timestamp:
        item.timestamp instanceof Date ? item.timestamp.toISOString() : item.timestamp,
    }));
    return this.request<LogIngestResponse>('POST', '/api/v1/logs/ingest', {
      body: normalizedPayload,
      headers: logSinkToken ? { 'X-Log-Sink-Token': logSinkToken } : undefined,
    });
  }

  /** List AI token usage entries and summary metrics. */
  tokenUsage(params?: TokenUsageParams): Promise<TokenUsageList> {
    const p: QueryParams = {};
    if (params) {
      if (params.page !== undefined) p['page'] = params.page;
      if (params.page_size !== undefined) p['page_size'] = params.page_size;
      if (params.order_by !== undefined) p['order_by'] = params.order_by;
      if (params.order_dir !== undefined) p['order_dir'] = params.order_dir;
      if (params.provider !== undefined) p['provider'] = params.provider;
      if (params.call_type !== undefined) p['call_type'] = params.call_type;
      if (params.operation !== undefined) p['operation'] = params.operation;
      if (params.user_id !== undefined) p['user_id'] = params.user_id;
      if (params.collection_id !== undefined) p['collection_id'] = params.collection_id;
      if (params.status !== undefined) p['status'] = params.status;
      if (params.from_ts !== undefined) p['from_ts'] = toIso(params.from_ts);
      if (params.to_ts !== undefined) p['to_ts'] = toIso(params.to_ts);
    }
    return this.get('/api/v1/token-usage', p);
  }
}
