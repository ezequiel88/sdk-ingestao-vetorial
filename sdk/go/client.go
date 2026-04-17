// Package ivector is the official Go SDK for the Ingestão Vetorial API.
//
// Use [New] to create a client, then call methods on it.
//
//	c := ivector.New("http://localhost:8000", "your-api-key")
//	results, err := c.Search(ctx, "machine learning", &ivector.SearchOptions{Limit: 5})
package ivector

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"mime/multipart"
	"net/http"
	"net/url"
	"strconv"
	"strings"
	"time"
)

// Client is the Ingestão Vetorial API client.
// It is safe for concurrent use.
type Client struct {
	baseURL string
	apiKey  string
	http    *http.Client
}

// New creates a new Client with the given base URL and API key.
// Apply optional functional options (e.g. [WithTimeout]) to customise behaviour.
func New(baseURL, apiKey string, opts ...func(*Client)) *Client {
	c := &Client{
		baseURL: strings.TrimRight(baseURL, "/"),
		apiKey:  apiKey,
		http:    &http.Client{Timeout: 30 * time.Second},
	}
	for _, opt := range opts {
		opt(c)
	}
	return c
}

// WithTimeout overrides the default 30-second HTTP timeout.
func WithTimeout(d time.Duration) func(*Client) {
	return func(c *Client) { c.http.Timeout = d }
}

// WithHTTPClient replaces the underlying *http.Client.
// Use this in tests to inject a mock transport.
func WithHTTPClient(hc *http.Client) func(*Client) {
	return func(c *Client) { c.http = hc }
}

// ── Internal helpers ─────────────────────────────────────────────────────────

func (c *Client) newReq(ctx context.Context, method, path string, body io.Reader) (*http.Request, error) {
	req, err := http.NewRequestWithContext(ctx, method, c.baseURL+path, body)
	if err != nil {
		return nil, err
	}
	req.Header.Set("Accept", "application/json")
	if c.apiKey != "" {
		req.Header.Set("X-API-Key", c.apiKey)
	}
	return req, nil
}

func (c *Client) do(req *http.Request, out any) error {
	resp, err := c.http.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return fmt.Errorf("ivector: reading response body: %w", err)
	}
	if resp.StatusCode >= 400 {
		return &ApiError{StatusCode: resp.StatusCode, Body: string(body)}
	}
	if out == nil || resp.StatusCode == http.StatusNoContent {
		return nil
	}
	return json.Unmarshal(body, out)
}

func (c *Client) doBytes(req *http.Request) ([]byte, error) {
	resp, err := c.http.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()
	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return nil, fmt.Errorf("ivector: reading response body: %w", err)
	}
	if resp.StatusCode >= 400 {
		return nil, &ApiError{StatusCode: resp.StatusCode, Body: string(body)}
	}
	return body, nil
}

type paginatedItems[T any] struct {
	Items []T `json:"items"`
	Meta  struct {
		HasMore bool `json:"has_more"`
	} `json:"meta"`
}

func (c *Client) getJSON(ctx context.Context, path string, p url.Values, out any) error {
	if len(p) > 0 {
		path += "?" + p.Encode()
	}
	req, err := c.newReq(ctx, http.MethodGet, path, nil)
	if err != nil {
		return err
	}
	return c.do(req, out)
}

func getJSONItems[T any](c *Client, ctx context.Context, path string, p url.Values) ([]T, error) {
	if len(p) > 0 {
		path += "?" + p.Encode()
	}
	req, err := c.newReq(ctx, http.MethodGet, path, nil)
	if err != nil {
		return nil, err
	}
	body, err := c.doBytes(req)
	if err != nil {
		return nil, err
	}
	var wrapped paginatedItems[T]
	if err := json.Unmarshal(body, &wrapped); err == nil && wrapped.Items != nil {
		return wrapped.Items, nil
	}
	var out []T
	if err := json.Unmarshal(body, &out); err != nil {
		return nil, err
	}
	return out, nil
}

func getAllJSONItems[T any](c *Client, ctx context.Context, path string, p url.Values, limit int) ([]T, error) {
	if limit <= 0 {
		limit = 100
	}
	skip := 0
	items := []T{}
	for {
		pageParams := url.Values{}
		for key, values := range p {
			for _, value := range values {
				pageParams.Add(key, value)
			}
		}
		pageParams.Set("skip", strconv.Itoa(skip))
		pageParams.Set("limit", strconv.Itoa(limit))

		if len(pageParams) > 0 {
			path = strings.Split(path, "?")[0]
		}
		req, err := c.newReq(ctx, http.MethodGet, path+"?"+pageParams.Encode(), nil)
		if err != nil {
			return nil, err
		}
		body, err := c.doBytes(req)
		if err != nil {
			return nil, err
		}
		var wrapped paginatedItems[T]
		if err := json.Unmarshal(body, &wrapped); err == nil && wrapped.Items != nil {
			items = append(items, wrapped.Items...)
			if !wrapped.Meta.HasMore || len(wrapped.Items) == 0 {
				break
			}
			skip += len(wrapped.Items)
			continue
		}
		var out []T
		if err := json.Unmarshal(body, &out); err != nil {
			return nil, err
		}
		items = append(items, out...)
		if len(out) < limit || len(out) == 0 {
			break
		}
		skip += len(out)
	}
	return items, nil
}

func (c *Client) getBytes(ctx context.Context, path string, p url.Values) ([]byte, error) {
	if len(p) > 0 {
		path += "?" + p.Encode()
	}
	req, err := c.newReq(ctx, http.MethodGet, path, nil)
	if err != nil {
		return nil, err
	}
	return c.doBytes(req)
}

func (c *Client) postJSON(ctx context.Context, path string, payload any, out any) error {
	b, err := json.Marshal(payload)
	if err != nil {
		return err
	}
	req, err := c.newReq(ctx, http.MethodPost, path, bytes.NewReader(b))
	if err != nil {
		return err
	}
	req.Header.Set("Content-Type", "application/json")
	return c.do(req, out)
}

func (c *Client) patchJSON(ctx context.Context, path string, payload any, out any) error {
	b, err := json.Marshal(payload)
	if err != nil {
		return err
	}
	req, err := c.newReq(ctx, http.MethodPatch, path, bytes.NewReader(b))
	if err != nil {
		return err
	}
	req.Header.Set("Content-Type", "application/json")
	return c.do(req, out)
}

func (c *Client) deleteReq(ctx context.Context, path string) error {
	req, err := c.newReq(ctx, http.MethodDelete, path, nil)
	if err != nil {
		return err
	}
	return c.do(req, nil)
}

// ── Collections ──────────────────────────────────────────────────────────────

// EmbeddingModels returns the list of embedding models available for new collections.
func (c *Client) EmbeddingModels(ctx context.Context) ([]EmbeddingModel, error) {
	return getJSONItems[EmbeddingModel](c, ctx, "/api/v1/collections/embedding-models", nil)
}

// Collections lists collections with optional filters.
// Pass nil to use defaults (skip=0, limit=100, logic=and).
func (c *Client) Collections(ctx context.Context, opts *CollectionsOptions) ([]Collection, error) {
	p := url.Values{}
	skip, limit, logic := 0, 100, "and"
	if opts != nil {
		skip = opts.Skip
		if opts.Limit > 0 {
			limit = opts.Limit
		}
		if opts.Logic != "" {
			logic = opts.Logic
		}
		if opts.UserID != "" {
			p.Set("user_id", opts.UserID)
		}
		if opts.ProjectID != "" {
			p.Set("project_id", opts.ProjectID)
		}
		if opts.Alias != "" {
			p.Set("alias", opts.Alias)
		}
		if opts.Query != "" {
			p.Set("query", opts.Query)
		}
	}
	p.Set("skip", strconv.Itoa(skip))
	p.Set("limit", strconv.Itoa(limit))
	p.Set("logic", logic)
	return getJSONItems[Collection](c, ctx, "/api/v1/collections", p)
}

// CreateCollection creates a new collection.
func (c *Client) CreateCollection(ctx context.Context, params CreateCollectionParams) (*Collection, error) {
	payload := map[string]any{
		"name":            params.Name,
		"embedding_model": params.EmbeddingModel,
		"dimension":       params.Dimension,
		"is_public":       params.IsPublic,
	}
	if params.ChunkSize > 0 {
		payload["chunk_size"] = params.ChunkSize
	}
	if params.ChunkOverlap > 0 {
		payload["chunk_overlap"] = params.ChunkOverlap
	}
	if params.Description != "" {
		payload["description"] = params.Description
	}
	if params.Alias != "" {
		payload["alias"] = params.Alias
	}
	if params.UserID != "" {
		payload["user_id"] = params.UserID
	}
	if params.ProjectID != "" {
		payload["project_id"] = params.ProjectID
	}
	var out Collection
	return &out, c.postJSON(ctx, "/api/v1/collections", payload, &out)
}

// GetCollection fetches a single collection by ID.
func (c *Client) GetCollection(ctx context.Context, collectionID string) (*Collection, error) {
	var out Collection
	return &out, c.getJSON(ctx, "/api/v1/collections/"+collectionID, nil, &out)
}

// UpdateCollection updates a collection's mutable fields.
func (c *Client) UpdateCollection(ctx context.Context, collectionID string, params UpdateCollectionParams) (*Collection, error) {
	payload := map[string]any{}
	if params.Name != nil {
		payload["name"] = *params.Name
	}
	if params.Description != nil {
		payload["description"] = *params.Description
	}
	if params.IsPublic != nil {
		payload["is_public"] = *params.IsPublic
	}
	var out Collection
	return &out, c.patchJSON(ctx, "/api/v1/collections/"+collectionID, payload, &out)
}

// DeleteCollection permanently deletes a collection and all its documents.
func (c *Client) DeleteCollection(ctx context.Context, collectionID string) error {
	return c.deleteReq(ctx, "/api/v1/collections/"+collectionID)
}

// CollectionRaw returns the raw Qdrant collection info.
func (c *Client) CollectionRaw(ctx context.Context, collectionID string) (map[string]any, error) {
	var out map[string]any
	return out, c.getJSON(ctx, "/api/v1/collections/"+collectionID+"/raw", nil, &out)
}

// CollectionDocuments lists documents belonging to a collection.
func (c *Client) CollectionDocuments(ctx context.Context, collectionID string, skip, limit int) ([]Document, error) {
	p := url.Values{
		"skip":  {strconv.Itoa(skip)},
		"limit": {strconv.Itoa(limit)},
	}
	return getJSONItems[Document](c, ctx, "/api/v1/collections/"+collectionID+"/documents", p)
}

// ── Documents ────────────────────────────────────────────────────────────────

// Documents lists all documents, optionally filtered by collection ID.
func (c *Client) Documents(ctx context.Context, opts *DocumentsOptions) ([]Document, error) {
	p := url.Values{"skip": {"0"}, "limit": {"100"}}
	if opts != nil {
		p.Set("skip", strconv.Itoa(opts.Skip))
		if opts.Limit > 0 {
			p.Set("limit", strconv.Itoa(opts.Limit))
		}
		if opts.CollectionID != "" {
			p.Set("collection_id", opts.CollectionID)
		}
	}
	return getJSONItems[Document](c, ctx, "/api/v1/documents", p)
}

// Document fetches full details for a document, including versions and metadata.
func (c *Client) Document(ctx context.Context, documentID string) (*DocumentDetail, error) {
	var out DocumentDetail
	return &out, c.getJSON(ctx, "/api/v1/documents/"+documentID, nil, &out)
}

// DocumentChunks returns all chunks for a document, optionally for a specific version.
// Pass version=nil to use the active version.
func (c *Client) DocumentChunks(ctx context.Context, documentID string, version *int) ([]DocumentChunk, error) {
	return c.DocumentChunksSearch(ctx, documentID, version, "")
}

// DocumentChunksSearch returns all chunks for a document, optionally filtered by content query.
func (c *Client) DocumentChunksSearch(ctx context.Context, documentID string, version *int, query string) ([]DocumentChunk, error) {
	p := url.Values{}
	if version != nil {
		p.Set("version", strconv.Itoa(*version))
	}
	if query != "" {
		p.Set("q", query)
	}
	return getAllJSONItems[DocumentChunk](c, ctx, "/api/v1/documents/"+documentID+"/chunks", p, 100)
}

// DocumentMarkdown downloads the extracted markdown for a document version as bytes.
func (c *Client) DocumentMarkdown(ctx context.Context, documentID string, version *int) ([]byte, error) {
	p := url.Values{}
	if version != nil {
		p.Set("version", strconv.Itoa(*version))
	}
	return c.getBytes(ctx, "/api/v1/documents/"+documentID+"/markdown", p)
}

// DeleteDocument deletes a document and all its versions.
func (c *Client) DeleteDocument(ctx context.Context, documentID string) error {
	return c.deleteReq(ctx, "/api/v1/documents/"+documentID)
}

// ReprocessDocument re-runs the ingestion pipeline for an existing document.
// Query params are sent on the URL (not in the body).
func (c *Client) ReprocessDocument(ctx context.Context, documentID string, opts *ReprocessOptions) (map[string]any, error) {
	p := url.Values{"mode": {"replace"}}
	if opts != nil {
		if opts.Mode != "" {
			p.Set("mode", opts.Mode)
		}
		if opts.SourceVersion != nil {
			p.Set("source_version", strconv.Itoa(*opts.SourceVersion))
		}
		if opts.ExtractionTool != "" {
			p.Set("extraction_tool", opts.ExtractionTool)
		}
	}
	path := "/api/v1/documents/" + documentID + "/reprocess?" + p.Encode()
	req, err := c.newReq(ctx, http.MethodPost, path, nil)
	if err != nil {
		return nil, err
	}
	var out map[string]any
	return out, c.do(req, &out)
}

// DeleteDocumentVersion deletes a specific version of a document.
func (c *Client) DeleteDocumentVersion(ctx context.Context, documentID string, version int) error {
	return c.deleteReq(ctx, fmt.Sprintf("/api/v1/documents/%s/versions/%d", documentID, version))
}

// SetVersionActive activates or deactivates a specific document version.
func (c *Client) SetVersionActive(ctx context.Context, documentID string, version int, isActive bool) (map[string]any, error) {
	var out map[string]any
	return out, c.patchJSON(ctx,
		fmt.Sprintf("/api/v1/documents/%s/versions/%d", documentID, version),
		map[string]any{"is_active": isActive},
		&out,
	)
}

// ── Upload ───────────────────────────────────────────────────────────────────

// Upload uploads a file and starts the ingestion pipeline.
// filename is the name that will be stored; r is the file content.
func (c *Client) Upload(ctx context.Context, filename string, r io.Reader, opts UploadOptions) (*UploadResponse, error) {
	var buf bytes.Buffer
	mw := multipart.NewWriter(&buf)

	// File field
	part, err := mw.CreateFormFile("file", filename)
	if err != nil {
		return nil, err
	}
	if _, err = io.Copy(part, r); err != nil {
		return nil, err
	}

	// Metadata as JSON string (API quirk)
	tags := opts.Tags
	if tags == nil {
		tags = []string{}
	}
	customFields := opts.CustomFields
	if customFields == nil {
		customFields = []map[string]string{}
	}
	docType := opts.DocumentType
	if docType == "" {
		docType = "document"
	}
	meta, _ := json.Marshal(map[string]any{
		"document_type": docType,
		"description":   opts.Description,
		"tags":          tags,
		"custom_fields": customFields,
	})

	_ = mw.WriteField("collection_id", opts.CollectionID)
	_ = mw.WriteField("metadata", string(meta))
	_ = mw.WriteField("overwrite_existing", strconv.FormatBool(opts.OverwriteExisting))
	if opts.EmbeddingModel != "" {
		_ = mw.WriteField("embedding_model", opts.EmbeddingModel)
	}
	if opts.Dimension != nil {
		_ = mw.WriteField("dimension", strconv.Itoa(*opts.Dimension))
	}
	if opts.ExtractionTool != "" {
		_ = mw.WriteField("extraction_tool", opts.ExtractionTool)
	}
	mw.Close()

	req, err := c.newReq(ctx, http.MethodPost, "/api/v1/upload", &buf)
	if err != nil {
		return nil, err
	}
	req.Header.Set("Content-Type", mw.FormDataContentType())

	var out UploadResponse
	return &out, c.do(req, &out)
}

// ── Search ───────────────────────────────────────────────────────────────────

// Search runs a semantic search query against the API.
// Pass nil opts to use defaults (limit=10, min_score=0).
func (c *Client) Search(ctx context.Context, query string, opts *SearchOptions) ([]SearchResult, error) {
	p := url.Values{
		"query":     {query},
		"limit":     {"10"},
		"offset":    {"0"},
		"min_score": {"0"},
	}
	if opts != nil {
		if opts.CollectionID != "" {
			p.Set("collection_id", opts.CollectionID)
		}
		if opts.Limit > 0 {
			p.Set("limit", strconv.Itoa(opts.Limit))
		}
		if opts.Offset > 0 {
			p.Set("offset", strconv.Itoa(opts.Offset))
		}
		if opts.MinScore > 0 {
			p.Set("min_score", strconv.FormatFloat(opts.MinScore, 'f', -1, 64))
		}
	}
	return getJSONItems[SearchResult](c, ctx, "/api/v1/search", p)
}

// ── Tags ─────────────────────────────────────────────────────────────────────

// Tags lists all tags (paginated).
func (c *Client) Tags(ctx context.Context, skip, limit int) ([]string, error) {
	p := url.Values{
		"skip":  {strconv.Itoa(skip)},
		"limit": {strconv.Itoa(limit)},
	}
	return getJSONItems[string](c, ctx, "/api/v1/tags", p)
}

// SearchTags searches tags by partial name.
func (c *Client) SearchTags(ctx context.Context, q string) ([]string, error) {
	return getJSONItems[string](c, ctx, "/api/v1/tags/search", url.Values{"q": {q}})
}

// CreateTag creates a new tag.
func (c *Client) CreateTag(ctx context.Context, name string) (*Tag, error) {
	var out Tag
	return &out, c.postJSON(ctx, "/api/v1/tags", map[string]string{"name": name}, &out)
}

// ── Stats ────────────────────────────────────────────────────────────────────

// DashboardStats returns aggregate counts: collections, documents, vectors, size.
func (c *Client) DashboardStats(ctx context.Context) (*DashboardStats, error) {
	var out DashboardStats
	return &out, c.getJSON(ctx, "/api/v1/stats/dashboard", nil, &out)
}

// RecentActivity returns the most recent ingestion/upload activity entries.
func (c *Client) RecentActivity(ctx context.Context, limit int) ([]RecentActivity, error) {
	return getJSONItems[RecentActivity](c, ctx, "/api/v1/stats/activity", url.Values{"limit": {strconv.Itoa(limit)}})
}

// TopCollections returns the collections ranked by document/vector count.
func (c *Client) TopCollections(ctx context.Context, limit int) ([]TopCollection, error) {
	return getJSONItems[TopCollection](c, ctx, "/api/v1/stats/top-collections", url.Values{"limit": {strconv.Itoa(limit)}})
}

// UploadsPerDay returns upload counts grouped by day for the last n days.
func (c *Client) UploadsPerDay(ctx context.Context, days int) ([]UploadsPerDay, error) {
	return getJSONItems[UploadsPerDay](c, ctx, "/api/v1/stats/uploads-per-day", url.Values{"days": {strconv.Itoa(days)}})
}

// VectorsPerWeek returns vector counts grouped by week for the last n weeks.
func (c *Client) VectorsPerWeek(ctx context.Context, weeks int) ([]VectorsPerWeek, error) {
	return getJSONItems[VectorsPerWeek](c, ctx, "/api/v1/stats/vectors-per-week", url.Values{"weeks": {strconv.Itoa(weeks)}})
}

// ── Progress ─────────────────────────────────────────────────────────────────

// ActiveJobs returns all currently running ingestion jobs.
func (c *Client) ActiveJobs(ctx context.Context) ([]JobProgress, error) {
	return getAllJSONItems[JobProgress](c, ctx, "/api/v1/progress/active", nil, 100)
}

// JobProgress returns the ingestion progress for a specific document version.
func (c *Client) JobProgress(ctx context.Context, documentID string, version int) (*JobProgress, error) {
	var out JobProgress
	return &out, c.getJSON(ctx,
		fmt.Sprintf("/api/v1/progress/%s/versions/%d", documentID, version),
		nil, &out,
	)
}

// CancelIngestion requests cancellation of an in-progress ingestion job.
func (c *Client) CancelIngestion(ctx context.Context, documentID string, version int) (map[string]any, error) {
	path := fmt.Sprintf("/api/v1/progress/%s/versions/%d/cancel", documentID, version)
	req, err := c.newReq(ctx, http.MethodPost, path, nil)
	if err != nil {
		return nil, err
	}
	var out map[string]any
	return out, c.do(req, &out)
}

// ── Logs ─────────────────────────────────────────────────────────────────────

// Logs queries the application log store with filters and pagination.
func (c *Client) Logs(ctx context.Context, opts *LogsOptions) (*LogList, error) {
	p := url.Values{
		"page":      {"1"},
		"page_size": {"50"},
		"order_by":  {"timestamp"},
		"order_dir": {"desc"},
	}
	if opts != nil {
		if opts.Page > 0 {
			p.Set("page", strconv.Itoa(opts.Page))
		}
		if opts.PageSize > 0 {
			p.Set("page_size", strconv.Itoa(opts.PageSize))
		}
		if opts.OrderBy != "" {
			p.Set("order_by", opts.OrderBy)
		}
		if opts.OrderDir != "" {
			p.Set("order_dir", opts.OrderDir)
		}
		for key, val := range map[string]string{
			"from_ts":     opts.FromTS,
			"to_ts":       opts.ToTS,
			"nivel":       opts.Nivel,
			"app":         opts.App,
			"endpoint":    opts.Endpoint,
			"q":           opts.Q,
			"user_id":     opts.UserID,
			"session_id":  opts.SessionID,
			"project_ids": opts.ProjectIDs,
		} {
			if val != "" {
				p.Set(key, val)
			}
		}
		if opts.StatusCode != nil {
			p.Set("status_code", strconv.Itoa(*opts.StatusCode))
		}
	}
	var out LogList
	return &out, c.getJSON(ctx, "/api/v1/logs", p, &out)
}

// LogFacets returns the distinct values for log filter dropdowns.
func (c *Client) LogFacets(ctx context.Context) (*LogFacets, error) {
	var out LogFacets
	return &out, c.getJSON(ctx, "/api/v1/logs/facets", nil, &out)
}

// LogSummary returns aggregated log statistics for an optional time window.
// Use empty strings for fromTS/toTS to omit the filter.
func (c *Client) LogSummary(ctx context.Context, fromTS, toTS string) (*LogSummary, error) {
	p := url.Values{}
	if fromTS != "" {
		p.Set("from_ts", fromTS)
	}
	if toTS != "" {
		p.Set("to_ts", toTS)
	}
	var out LogSummary
	return &out, c.getJSON(ctx, "/api/v1/logs/summary", p, &out)
}

// ExportLogs exports logs as bytes in the requested format (json or csv).
func (c *Client) ExportLogs(ctx context.Context, opts *ExportLogsOptions) ([]byte, error) {
	format := "json"
	limit := 10000
	p := url.Values{}
	if opts != nil {
		if opts.Format != "" {
			format = opts.Format
		}
		if opts.Limit > 0 {
			limit = opts.Limit
		}
		for key, val := range map[string]string{
			"from_ts":     opts.FromTS,
			"to_ts":       opts.ToTS,
			"nivel":       opts.Nivel,
			"app":         opts.App,
			"endpoint":    opts.Endpoint,
			"q":           opts.Q,
			"user_id":     opts.UserID,
			"session_id":  opts.SessionID,
			"project_ids": opts.ProjectIDs,
		} {
			if val != "" {
				p.Set(key, val)
			}
		}
		if opts.StatusCode != nil {
			p.Set("status_code", strconv.Itoa(*opts.StatusCode))
		}
	}
	p.Set("format", format)
	p.Set("limit", strconv.Itoa(limit))
	return c.getBytes(ctx, "/api/v1/logs/export", p)
}
