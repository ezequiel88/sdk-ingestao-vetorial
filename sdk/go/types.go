package ivector

import "fmt"

// ── Core response types ──────────────────────────────────────────────────────

// EmbeddingModel describes an embedding model available for collections.
type EmbeddingModel struct {
	ID         string `json:"id"`
	Name       string `json:"name"`
	Provider   string `json:"provider"`
	Dimensions []int  `json:"dimensions"`
}

// Collection is a named vector collection in the system.
type Collection struct {
	ID             string  `json:"id"`
	Name           string  `json:"name"`
	Alias          string  `json:"alias"`
	Description    *string `json:"description"`
	IsPublic       bool    `json:"is_public"`
	EmbeddingModel string  `json:"embedding_model"`
	Dimension      int     `json:"dimension"`
	ChunkSize      int     `json:"chunk_size"`
	ChunkOverlap   int     `json:"chunk_overlap"`
	CreatedAt      string  `json:"created_at"`
	DocumentCount  int     `json:"document_count"`
	UserID         *string `json:"user_id"`
	ProjectID      *string `json:"project_id"`
}

// Document is a document stored in a collection.
type Document struct {
	ID           string   `json:"id"`
	Name         string   `json:"name"`
	Size         string   `json:"size"`
	UploadedAt   string   `json:"uploaded_at"`
	VectorCount  int      `json:"vector_count"`
	ChunkCount   int      `json:"chunk_count"`
	Version      int      `json:"version"`
	CollectionID string   `json:"collection_id"`
	Tags         []string `json:"tags"`
	VersionCount int      `json:"version_count"`
}

type DocumentMetadata struct {
	DocumentType string           `json:"document_type"`
	Description  *string          `json:"description"`
	Tags         []string         `json:"tags"`
	CustomFields []map[string]any `json:"custom_fields"`
}

type DocumentVersion struct {
	Version      int     `json:"version"`
	UploadedAt   string  `json:"uploaded_at"`
	VectorCount  int     `json:"vector_count"`
	Checksum     string  `json:"checksum"`
	FilePath     *string `json:"file_path"`
	MarkdownPath *string `json:"markdown_path"`
	IsActive     bool    `json:"is_active"`
}

// DocumentDetail extends Document with version and metadata information.
type DocumentDetail struct {
	Document
	Checksum string           `json:"checksum"`
	Metadata DocumentMetadata `json:"metadata"`
	Versions []DocumentVersion `json:"versions"`
}

type ChunkMetadata struct {
	DocumentPath string `json:"document_path"`
	PageNumber   int    `json:"page_number"`
	Section      string `json:"section"`
	StartChar    int    `json:"start_char"`
	EndChar      int    `json:"end_char"`
	ChunkID      string `json:"chunk_id"`
	CollectionID string `json:"collection_id"`
	CreatedAt    string `json:"created_at"`
	Model        string `json:"model"`
	Dimension    int    `json:"dimension"`
}

// DocumentChunk is a processed text chunk with its index and token count.
type DocumentChunk struct {
	Index     int           `json:"index"`
	Content   string        `json:"content"`
	Tokens    int           `json:"tokens"`
	Embedding []float64     `json:"embedding"`
	Metadata  ChunkMetadata `json:"metadata"`
}

// SearchResult is a ranked semantic search hit.
type SearchResult struct {
	ID             string         `json:"id"`
	Score          float64        `json:"score"`
	Content        string         `json:"content"`
	DocumentID     string         `json:"document_id"`
	DocumentName   string         `json:"document_name"`
	CollectionID   string         `json:"collection_id"`
	CollectionName string         `json:"collection_name"`
	ChunkIndex     *int           `json:"chunk_index"`
	Metadata       map[string]any `json:"metadata"`
}

// Tag represents a label that can be attached to documents.
type Tag struct {
	ID   string `json:"id"`
	Name string `json:"name"`
}

// UploadResponse is the response from the upload endpoint.
type UploadResponse struct {
	Success     bool    `json:"success"`
	DocumentID  string  `json:"document_id"`
	VectorCount int     `json:"vector_count"`
	Version     int     `json:"version"`
	Message     *string `json:"message"`
}

// DashboardStats holds aggregate system metrics.
type DashboardStats struct {
	TotalCollections int     `json:"total_collections"`
	TotalDocuments   int     `json:"total_documents"`
	TotalVectors     int     `json:"total_vectors"`
	TotalSizeMB      float64 `json:"total_size_mb"`
}

// RecentActivity represents a recent ingestion or upload event.
type RecentActivity struct {
	ID        string         `json:"id"`
	Action    string         `json:"action"`
	Entity    string         `json:"entity"`
	Timestamp string         `json:"timestamp"`
	Details   map[string]any `json:"details"`
}

// TopCollection holds ranking data for a collection by document/vector count.
type TopCollection struct {
	ID            string `json:"id"`
	Name          string `json:"name"`
	DocumentCount int    `json:"document_count"`
	VectorCount   int    `json:"vector_count"`
}

// UploadsPerDay holds the upload count for a single day.
type UploadsPerDay struct {
	Date  string `json:"date"`
	Count int    `json:"count"`
}

// VectorsPerWeek holds the vector count for a single week.
type VectorsPerWeek struct {
	WeekStart string `json:"week_start"`
	Count     int    `json:"count"`
}

// JobProgress reports on an active or finished ingestion job.
type JobProgress struct {
	DocumentID   string  `json:"document_id"`
	DocumentName string  `json:"document_name"`
	Version      int     `json:"version"`
	Status       string  `json:"status"`
	Percent      float64 `json:"percent"`
	Error        *string `json:"error"`
}

// LogEntry is a single application log record.
type LogEntry struct {
	ID         string         `json:"id"`
	Timestamp  string         `json:"timestamp"`
	Nivel      string         `json:"nivel"`
	App        string         `json:"app"`
	Endpoint   string         `json:"endpoint"`
	StatusCode int            `json:"status_code"`
	Acao       string         `json:"acao"`
	UserID     *string        `json:"user_id"`
	ProjectID  *string        `json:"project_id"`
	Extra      map[string]any `json:"extra"`
}

// PageMeta carries pagination metadata for a list response.
type PageMeta struct {
	Page     int  `json:"page"`
	PageSize int  `json:"page_size"`
	Total    int  `json:"total"`
	Pages    int  `json:"pages"`
	Skip     int  `json:"skip"`
	Limit    int  `json:"limit"`
	HasMore  bool `json:"has_more"`
}

// LogList is a paginated list of log entries.
type LogList struct {
	Items []LogEntry `json:"items"`
	Meta  PageMeta   `json:"meta"`
}

// LogFacets holds the distinct filter values for the log viewer.
type LogFacets struct {
	Levels    []string `json:"levels"`
	Apps      []string `json:"apps"`
	Endpoints []string `json:"endpoints"`
}

// LogSummary holds aggregated log statistics for a time window.
type LogSummary struct {
	Total   int            `json:"total"`
	ByLevel map[string]int `json:"byLevel"`
}

// ── Error type ───────────────────────────────────────────────────────────────

// ApiError represents an HTTP error response from the API.
type ApiError struct {
	StatusCode int
	Body       string
}

func (e *ApiError) Error() string {
	return fmt.Sprintf("ivector: API error %d: %s", e.StatusCode, e.Body)
}

// ── Option / param structs ───────────────────────────────────────────────────

// CollectionsOptions filters a list-collections request.
// Zero values are treated as "not set" for string fields.
// Limit defaults to 100 and Logic defaults to "and" when zero.
type CollectionsOptions struct {
	Skip      int
	Limit     int
	Logic     string
	UserID    string
	ProjectID string
	Alias     string
	Query     string
}

// CreateCollectionParams holds fields for creating a new collection.
type CreateCollectionParams struct {
	Name           string
	EmbeddingModel string
	Dimension      int
	ChunkSize      int    // 0 → server default (1400)
	ChunkOverlap   int    // 0 → server default (250)
	Description    string // optional
	Alias          string // optional
	IsPublic       bool
	UserID         string // optional
	ProjectID      string // optional
}

// UpdateCollectionParams holds the mutable collection fields.
// Use pointer fields so unset values are omitted from the PATCH body.
type UpdateCollectionParams struct {
	Name        *string
	Description *string
	IsPublic    *bool
}

// DocumentsOptions filters a list-documents request.
type DocumentsOptions struct {
	Skip         int
	Limit        int
	CollectionID string
}

// ReprocessOptions configures a document reprocess request.
type ReprocessOptions struct {
	SourceVersion  *int
	Mode           string // "replace" (default) | "append"
	ExtractionTool string
}

// UploadOptions configures an upload request.
type UploadOptions struct {
	CollectionID      string
	DocumentType      string // default "document"
	Description       string
	Tags              []string
	CustomFields      []map[string]string
	OverwriteExisting bool
	EmbeddingModel    string
	Dimension         *int
	ExtractionTool    string
}

// SearchOptions configures a semantic search.
type SearchOptions struct {
	CollectionID string
	Limit        int     // default 10
	Offset       int
	MinScore     float64
}

// LogsOptions configures a paginated log query.
type LogsOptions struct {
	Page       int
	PageSize   int
	OrderBy    string
	OrderDir   string
	FromTS     string
	ToTS       string
	Nivel      string
	App        string
	Endpoint   string
	StatusCode *int
	Q          string
	UserID     string
	SessionID  string
	ProjectIDs string
}

// ExportLogsOptions configures a log export.
type ExportLogsOptions struct {
	Format     string // "json" (default) | "csv"
	Limit      int    // default 10000
	FromTS     string
	ToTS       string
	Nivel      string
	App        string
	Endpoint   string
	StatusCode *int
	Q          string
	UserID     string
	SessionID  string
	ProjectIDs string
}
