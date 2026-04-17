export { IngestaoVetorialClient, ApiError } from './client';

export type {
  // Primitives / enums
  JobStatus,
  LogExportFormat,
  ReprocessMode,
  UploadFile,

  // Collections
  Collection,
  CollectionListParams,
  CreateCollectionParams,
  UpdateCollectionParams,

  // Documents
  Document,
  DocumentDetail,
  DocumentMetadata,
  DocumentVersion,
  DocumentListParams,

  // Chunks
  DocumentChunk,
  ChunkMetadata,

  // Upload
  UploadOptions,
  UploadMetadata,
  UploadResponse,

  // Search
  SearchResult,
  SearchParams,

  // Tags
  Tag,
  TagListParams,

  // Stats
  DashboardStats,
  DashboardOverview,
  LogsOverview,
  RecentActivity,
  TopCollection,
  UploadsPerDay,
  VectorsPerWeek,

  // Progress
  JobProgress,
  CancelIngestionResponse,

  // Embedding models
  EmbeddingModelOption,

  // Logs
  LogEntry,
  PageMeta,
  LogList,
  LogFacets,
  LogSummary,
  LogListParams,
  LogExportParams,
  LogIngestItem,
  LogIngestResponse,
  TokenUsageRecord,
  TokenUsageSummary,
  TokenUsageList,
  TokenUsageParams,
} from './types';
