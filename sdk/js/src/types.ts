// ─────────────────────────────────────────────────────────────────────────────
// Shared primitives
// ─────────────────────────────────────────────────────────────────────────────

/** Known job status values returned by the ingestion pipeline. */
export type JobStatus =
  | 'extracting'
  | 'chunking'
  | 'upserting'
  | 'completed'
  | 'error'
  | 'cancelled';

/** Export format for log downloads. */
export type LogExportFormat = 'json' | 'csv';

/** Reprocessing strategy for an existing document. */
export type ReprocessMode = 'replace' | 'append';

// ─────────────────────────────────────────────────────────────────────────────
// Error
// ─────────────────────────────────────────────────────────────────────────────

/** Thrown for any non-2xx response from the API. */
export class ApiError extends Error {
  readonly statusCode: number;
  readonly body: string;

  constructor(statusCode: number, body: string) {
    super(`API error ${statusCode}: ${body}`);
    this.name = 'ApiError';
    this.statusCode = statusCode;
    this.body = body;
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Embedding models
// ─────────────────────────────────────────────────────────────────────────────

export interface EmbeddingModelOption {
  id: string;
  name: string;
  provider: string;
  dimensions: number[];
  defaultDimension: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Collections
// ─────────────────────────────────────────────────────────────────────────────

export interface Collection {
  id: string;
  name: string;
  alias: string;
  description: string | null;
  is_public: boolean;
  embedding_model: string;
  dimension: number;
  chunk_size: number;
  chunk_overlap: number;
  created_at: string;
  document_count: number;
  user_id: string | null;
  project_id: string | null;
}

export interface CollectionListParams {
  skip?: number;
  limit?: number;
  logic?: string;
  user_id?: string;
  project_id?: string;
  alias?: string;
  query?: string;
}

export interface CreateCollectionParams {
  name: string;
  embedding_model: string;
  dimension: number;
  chunk_size?: number;
  chunk_overlap?: number;
  description?: string;
  alias?: string;
  is_public?: boolean;
  user_id?: string;
  project_id?: string;
}

export interface UpdateCollectionParams {
  name?: string;
  description?: string;
  is_public?: boolean;
}

// ─────────────────────────────────────────────────────────────────────────────
// Documents
// ─────────────────────────────────────────────────────────────────────────────

export interface DocumentMetadata {
  document_type: string;
  description: string | null;
  tags: string[];
  custom_fields: Record<string, unknown>[];
}

export interface DocumentVersion {
  version: number;
  uploaded_at: string;
  vector_count: number;
  checksum: string;
  is_active: boolean;
}

/** Lightweight document shape returned in list endpoints. */
export interface Document {
  id: string;
  name: string;
  size: string;
  uploaded_at: string;
  vector_count: number;
  chunk_count: number;
  version: number;
  collection_id: string;
  tags: string[];
  version_count: number;
}

/** Full document shape returned by the single-document endpoint. */
export interface DocumentDetail extends Document {
  checksum: string;
  metadata: DocumentMetadata;
  versions: DocumentVersion[];
}

export interface DocumentListParams {
  skip?: number;
  limit?: number;
  collection_id?: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Chunks
// ─────────────────────────────────────────────────────────────────────────────

export interface ChunkMetadata {
  document_path: string;
  page_number: number;
  section: string;
  start_char: number;
  end_char: number;
  chunk_id: string;
  collection_id: string;
  created_at: string;
  model: string;
  dimension: number;
}

export interface DocumentChunk {
  index: number;
  content: string;
  tokens: number;
  embedding: number[];
  metadata: ChunkMetadata;
}

// ─────────────────────────────────────────────────────────────────────────────
// Upload
// ─────────────────────────────────────────────────────────────────────────────

export interface UploadMetadata {
  document_type?: string;
  description?: string;
  tags?: string[];
  custom_fields?: Record<string, unknown>[];
}

export interface UploadOptions {
  collection_id: string;
  metadata?: UploadMetadata;
  overwrite_existing?: boolean;
  embedding_model?: string;
  dimension?: number;
  extraction_tool?: string;
}

export interface UploadResponse {
  success: boolean;
  document_id: string;
  vector_count: number;
  version: number;
  message: string | null;
}

/**
 * Accepted file types for `upload()`.
 *
 * - `File` — browser / Node 18+
 * - `Blob` — browser / Node 18+ (filename defaults to `"upload.bin"`)
 * - `{ blob: Blob; name: string }` — named blob
 * - `{ uri: string; name: string; type?: string }` — React Native file descriptor
 */
export type UploadFile =
  | File
  | Blob
  | { blob: Blob; name: string }
  | { uri: string; name: string; type?: string };

// ─────────────────────────────────────────────────────────────────────────────
// Search
// ─────────────────────────────────────────────────────────────────────────────

export interface SearchResult {
  id: string;
  type: string;
  document_name: string;
  collection_id: string;
  collection_name: string;
  chunk_index: number | null;
  content: string;
  score: number;
  metadata: Record<string, unknown>;
}

export interface SearchParams {
  collection_id?: string;
  limit?: number;
  offset?: number;
  min_score?: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Tags
// ─────────────────────────────────────────────────────────────────────────────

export interface Tag {
  id: string;
  name: string;
}

export interface TagListParams {
  skip?: number;
  limit?: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Stats
// ─────────────────────────────────────────────────────────────────────────────

export interface DashboardStats {
  total_collections: number;
  total_documents: number;
  total_vectors: number;
  total_size_mb: number;
}

export interface LogsOverview {
  total: number;
  by_level: Record<string, number>;
  by_app: Record<string, number>;
  top_endpoints: Array<Record<string, unknown>>;
}

export interface DashboardOverview {
  summary: DashboardStats;
  recent_activity: RecentActivity[];
  top_collections: TopCollection[];
  uploads_per_day: UploadsPerDay[];
  vectors_per_week: VectorsPerWeek[];
  logs_overview: LogsOverview;
}

export interface RecentActivity {
  id: string;
  action: string;
  entity: string;
  timestamp: string;
  details: Record<string, unknown>;
}

export interface TopCollection {
  id: string;
  name: string;
  document_count: number;
  vector_count: number;
}

export interface UploadsPerDay {
  date: string;
  count: number;
}

export interface VectorsPerWeek {
  week_start: string;
  count: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Ingestion progress
// ─────────────────────────────────────────────────────────────────────────────

export interface JobProgress {
  job_id: string;
  document_id: string;
  version: number;
  /** See {@link JobStatus} for known values. */
  status: string;
  percent: number;
  processed_chunks: number;
  total_chunks: number;
  /** Unix timestamp (seconds). */
  started_at: number;
  /** Unix timestamp (seconds). */
  updated_at: number;
  eta_seconds: number | null;
  message: string;
  document_name: string;
  collection_id: string;
  error: string;
}

export interface CancelIngestionResponse {
  ok: boolean;
}

// ─────────────────────────────────────────────────────────────────────────────
// Logs
// ─────────────────────────────────────────────────────────────────────────────

export interface LogEntry {
  id: string;
  timestamp: string;
  requestId: string | null;
  nivel: string;
  modulo: string;
  acao: string;
  detalhes: Record<string, unknown>;
  request: Record<string, unknown> | null;
  response: Record<string, unknown> | null;
  usuarioId: string | null;
  projetoId: string | null;
  /** Execution time in milliseconds. */
  tempoExecucao: number | null;
}

export interface PageMeta {
  page: number;
  pageSize: number;
  total: number;
}

export interface LogList {
  items: LogEntry[];
  meta: PageMeta;
}

export interface LogFacets {
  apps: string[];
  endpoints: string[];
  projects: string[];
  users: string[];
}

export interface LogSummary {
  total: number;
  byLevel: Record<string, number>;
  byApp: Record<string, number>;
  topEndpoints: Array<{ endpoint: string; c: number }>;
}

export interface LogListParams {
  page?: number;
  page_size?: number;
  order_by?: string;
  order_dir?: 'asc' | 'desc';
  from_ts?: string | Date;
  to_ts?: string | Date;
  nivel?: string;
  app?: string;
  endpoint?: string;
  status_code?: number;
  q?: string;
  user_id?: string;
  session_id?: string;
  project_ids?: string;
}

export interface LogExportParams {
  format?: LogExportFormat;
  limit?: number;
  from_ts?: string | Date;
  to_ts?: string | Date;
  nivel?: string;
  app?: string;
  endpoint?: string;
  status_code?: number;
  q?: string;
  user_id?: string;
  session_id?: string;
  project_ids?: string;
}

export interface LogIngestItem {
  id?: string;
  timestamp?: string | Date;
  request_id?: string;
  nivel: string;
  modulo: string;
  acao: string;
  detalhes?: Record<string, unknown>;
  request?: Record<string, unknown> | null;
  response?: Record<string, unknown> | null;
  usuario_id?: string;
  projeto_id?: string;
  tempo_execucao?: number;
}

export interface LogIngestResponse {
  accepted: number;
}

export interface TokenUsageRecord {
  id: string;
  timestamp: string;
  provider: string;
  modelId: string;
  callType: string;
  inputTokens: number;
  outputTokens: number | null;
  totalTokens: number;
  latencyMs: number;
  status: string;
  errorCode: string | null;
  userId: string | null;
  collectionId: string | null;
  operation: string;
  extra: Record<string, unknown>;
}

export interface TokenUsageSummary {
  totalRecords: number;
  totalInputTokens: number;
  totalOutputTokens: number;
  totalTokens: number;
  providers: Array<Record<string, unknown>>;
}

export interface TokenUsageList {
  items: TokenUsageRecord[];
  meta: PageMeta;
  summary: TokenUsageSummary;
}

export interface TokenUsageParams {
  page?: number;
  page_size?: number;
  order_by?: string;
  order_dir?: 'asc' | 'desc';
  provider?: string;
  call_type?: string;
  operation?: string;
  user_id?: string;
  collection_id?: string;
  status?: string;
  from_ts?: string | Date;
  to_ts?: string | Date;
}
