package ivector_test

import (
	"context"
	"encoding/json"
	"io"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	ivector "github.com/ezequiel88/sdk-ingestao-vetorial/sdk/go"
)

// ── helpers ──────────────────────────────────────────────────────────────────

func newTestServer(t *testing.T, handler http.HandlerFunc) (*httptest.Server, *ivector.Client) {
	t.Helper()
	srv := httptest.NewServer(handler)
	t.Cleanup(srv.Close)
	return srv, ivector.New(srv.URL, "test-key")
}

func writeJSON(t *testing.T, w http.ResponseWriter, v any) {
	t.Helper()
	w.Header().Set("Content-Type", "application/json")
	if err := json.NewEncoder(w).Encode(v); err != nil {
		t.Errorf("writeJSON: %v", err)
	}
}

func assertMethod(t *testing.T, r *http.Request, method string) {
	t.Helper()
	if r.Method != method {
		t.Errorf("method = %s, want %s", r.Method, method)
	}
}

func assertPath(t *testing.T, r *http.Request, path string) {
	t.Helper()
	if r.URL.Path != path {
		t.Errorf("path = %s, want %s", r.URL.Path, path)
	}
}

func assertAPIKey(t *testing.T, r *http.Request) {
	t.Helper()
	if got := r.Header.Get("X-API-Key"); got != "test-key" {
		t.Errorf("X-API-Key = %q, want %q", got, "test-key")
	}
}

var bg = context.Background()

func paginated[T any](items []T) map[string]any {
	return map[string]any{
		"items": items,
		"meta": map[string]any{
			"skip": 0,
			"limit": len(items),
			"total": len(items),
			"has_more": false,
		},
	}
}

// ── Collections ──────────────────────────────────────────────────────────────

func TestEmbeddingModels(t *testing.T) {
	_, c := newTestServer(t, func(w http.ResponseWriter, r *http.Request) {
		assertMethod(t, r, http.MethodGet)
		assertPath(t, r, "/api/v1/collections/embedding-models")
		assertAPIKey(t, r)
		writeJSON(t, w, paginated([]ivector.EmbeddingModel{{ID: "m1", Provider: "openai", Dimensions: []int{1536}}}))
	})

	got, err := c.EmbeddingModels(bg)
	if err != nil {
		t.Fatal(err)
	}
	if len(got) != 1 || got[0].ID != "m1" {
		t.Fatalf("unexpected: %v", got)
	}
}

func TestCollections(t *testing.T) {
	_, c := newTestServer(t, func(w http.ResponseWriter, r *http.Request) {
		assertMethod(t, r, http.MethodGet)
		assertPath(t, r, "/api/v1/collections")
		if q := r.URL.Query().Get("query"); q != "test" {
			t.Errorf("query param = %q, want %q", q, "test")
		}
		writeJSON(t, w, paginated([]ivector.Collection{{ID: "c1", Name: "My Collection"}}))
	})

	got, err := c.Collections(bg, &ivector.CollectionsOptions{Query: "test"})
	if err != nil {
		t.Fatal(err)
	}
	if len(got) != 1 || got[0].ID != "c1" {
		t.Fatalf("unexpected: %v", got)
	}
}

func TestCreateCollection(t *testing.T) {
	_, c := newTestServer(t, func(w http.ResponseWriter, r *http.Request) {
		assertMethod(t, r, http.MethodPost)
		assertPath(t, r, "/api/v1/collections")
		var body map[string]any
		json.NewDecoder(r.Body).Decode(&body)
		if body["name"] != "Test" {
			t.Errorf("name = %v, want Test", body["name"])
		}
		w.WriteHeader(http.StatusCreated)
		writeJSON(t, w, ivector.Collection{ID: "c1", Name: "Test"})
	})

	got, err := c.CreateCollection(bg, ivector.CreateCollectionParams{
		Name:           "Test",
		EmbeddingModel: "text-embedding-3-small",
		Dimension:      1536,
	})
	if err != nil {
		t.Fatal(err)
	}
	if got.ID != "c1" {
		t.Fatalf("ID = %q, want c1", got.ID)
	}
}

func TestGetCollection(t *testing.T) {
	_, c := newTestServer(t, func(w http.ResponseWriter, r *http.Request) {
		assertMethod(t, r, http.MethodGet)
		assertPath(t, r, "/api/v1/collections/abc")
		writeJSON(t, w, ivector.Collection{ID: "abc", Name: "Coll"})
	})

	got, err := c.GetCollection(bg, "abc")
	if err != nil {
		t.Fatal(err)
	}
	if got.ID != "abc" {
		t.Fatalf("ID = %q, want abc", got.ID)
	}
}

func TestUpdateCollection(t *testing.T) {
	name := "Renamed"
	_, c := newTestServer(t, func(w http.ResponseWriter, r *http.Request) {
		assertMethod(t, r, http.MethodPatch)
		assertPath(t, r, "/api/v1/collections/abc")
		writeJSON(t, w, ivector.Collection{ID: "abc", Name: name})
	})

	got, err := c.UpdateCollection(bg, "abc", ivector.UpdateCollectionParams{Name: &name})
	if err != nil {
		t.Fatal(err)
	}
	if got.Name != name {
		t.Fatalf("Name = %q, want %q", got.Name, name)
	}
}

func TestDeleteCollection(t *testing.T) {
	_, c := newTestServer(t, func(w http.ResponseWriter, r *http.Request) {
		assertMethod(t, r, http.MethodDelete)
		assertPath(t, r, "/api/v1/collections/abc")
		w.WriteHeader(http.StatusNoContent)
	})

	if err := c.DeleteCollection(bg, "abc"); err != nil {
		t.Fatal(err)
	}
}

// ── Documents ────────────────────────────────────────────────────────────────

func TestDocuments(t *testing.T) {
	_, c := newTestServer(t, func(w http.ResponseWriter, r *http.Request) {
		assertMethod(t, r, http.MethodGet)
		assertPath(t, r, "/api/v1/documents")
		writeJSON(t, w, paginated([]ivector.Document{{ID: "d1", Name: "doc.pdf"}}))
	})

	got, err := c.Documents(bg, nil)
	if err != nil {
		t.Fatal(err)
	}
	if len(got) != 1 || got[0].ID != "d1" {
		t.Fatalf("unexpected: %v", got)
	}
}

func TestDocument(t *testing.T) {
	_, c := newTestServer(t, func(w http.ResponseWriter, r *http.Request) {
		assertPath(t, r, "/api/v1/documents/d1")
		writeJSON(t, w, ivector.DocumentDetail{Document: ivector.Document{ID: "d1"}})
	})

	got, err := c.Document(bg, "d1")
	if err != nil {
		t.Fatal(err)
	}
	if got.ID != "d1" {
		t.Fatalf("ID = %q, want d1", got.ID)
	}
}

func TestDocumentChunks(t *testing.T) {
	_, c := newTestServer(t, func(w http.ResponseWriter, r *http.Request) {
		assertPath(t, r, "/api/v1/documents/d1/chunks")
		if v := r.URL.Query().Get("version"); v != "2" {
			t.Errorf("version = %q, want 2", v)
		}
		writeJSON(t, w, paginated([]ivector.DocumentChunk{{Index: 0, Content: "hello"}}))
	})

	v := 2
	got, err := c.DocumentChunks(bg, "d1", &v)
	if err != nil {
		t.Fatal(err)
	}
	if len(got) != 1 {
		t.Fatalf("expected 1 chunk, got %d", len(got))
	}
}

func TestDocumentMarkdown(t *testing.T) {
	_, c := newTestServer(t, func(w http.ResponseWriter, r *http.Request) {
		assertPath(t, r, "/api/v1/documents/d1/markdown")
		w.Write([]byte("# Hello"))
	})

	b, err := c.DocumentMarkdown(bg, "d1", nil)
	if err != nil {
		t.Fatal(err)
	}
	if string(b) != "# Hello" {
		t.Fatalf("content = %q, want %q", string(b), "# Hello")
	}
}

func TestDeleteDocument(t *testing.T) {
	_, c := newTestServer(t, func(w http.ResponseWriter, r *http.Request) {
		assertMethod(t, r, http.MethodDelete)
		assertPath(t, r, "/api/v1/documents/d1")
		w.WriteHeader(http.StatusNoContent)
	})

	if err := c.DeleteDocument(bg, "d1"); err != nil {
		t.Fatal(err)
	}
}

func TestReprocessDocument(t *testing.T) {
	_, c := newTestServer(t, func(w http.ResponseWriter, r *http.Request) {
		assertMethod(t, r, http.MethodPost)
		assertPath(t, r, "/api/v1/documents/d1/reprocess")
		if m := r.URL.Query().Get("mode"); m != "append" {
			t.Errorf("mode = %q, want append", m)
		}
		writeJSON(t, w, map[string]any{"version": 2})
	})

	got, err := c.ReprocessDocument(bg, "d1", &ivector.ReprocessOptions{Mode: "append"})
	if err != nil {
		t.Fatal(err)
	}
	if got["version"] == nil {
		t.Fatal("expected version in response")
	}
}

// ── Upload ───────────────────────────────────────────────────────────────────

func TestUpload(t *testing.T) {
	_, c := newTestServer(t, func(w http.ResponseWriter, r *http.Request) {
		assertMethod(t, r, http.MethodPost)
		assertPath(t, r, "/api/v1/upload")
		if !strings.HasPrefix(r.Header.Get("Content-Type"), "multipart/form-data") {
			t.Errorf("bad content-type: %s", r.Header.Get("Content-Type"))
		}
		r.ParseMultipartForm(1 << 20)
		if r.FormValue("collection_id") != "col1" {
			t.Errorf("collection_id = %q, want col1", r.FormValue("collection_id"))
		}
		writeJSON(t, w, ivector.UploadResponse{Success: true, DocumentID: "d1", Version: 1})
	})

	got, err := c.Upload(bg, "file.pdf", strings.NewReader("content"), ivector.UploadOptions{
		CollectionID: "col1",
		DocumentType: "report",
	})
	if err != nil {
		t.Fatal(err)
	}
	if !got.Success || got.DocumentID != "d1" {
		t.Fatalf("unexpected: %+v", got)
	}
}

// ── Search ───────────────────────────────────────────────────────────────────

func TestSearch(t *testing.T) {
	_, c := newTestServer(t, func(w http.ResponseWriter, r *http.Request) {
		assertPath(t, r, "/api/v1/search")
		if q := r.URL.Query().Get("query"); q != "machine learning" {
			t.Errorf("query = %q, want %q", q, "machine learning")
		}
		writeJSON(t, w, paginated([]ivector.SearchResult{{ID: "r1", Score: 0.95, Content: "snippet"}}))
	})

	got, err := c.Search(bg, "machine learning", &ivector.SearchOptions{Limit: 5})
	if err != nil {
		t.Fatal(err)
	}
	if len(got) != 1 || got[0].Score != 0.95 {
		t.Fatalf("unexpected: %v", got)
	}
}

func TestSearchAPIError(t *testing.T) {
	_, c := newTestServer(t, func(w http.ResponseWriter, r *http.Request) {
		http.Error(w, `{"detail":"unauthorized"}`, http.StatusUnauthorized)
	})

	_, err := c.Search(bg, "test", nil)
	if err == nil {
		t.Fatal("expected error, got nil")
	}
	apiErr, ok := err.(*ivector.ApiError)
	if !ok {
		t.Fatalf("expected *ApiError, got %T", err)
	}
	if apiErr.StatusCode != http.StatusUnauthorized {
		t.Fatalf("StatusCode = %d, want 401", apiErr.StatusCode)
	}
}

// ── Tags ─────────────────────────────────────────────────────────────────────

func TestTags(t *testing.T) {
	_, c := newTestServer(t, func(w http.ResponseWriter, r *http.Request) {
		assertPath(t, r, "/api/v1/tags")
		writeJSON(t, w, paginated([]string{"go", "python", "java"}))
	})

	got, err := c.Tags(bg, 0, 100)
	if err != nil {
		t.Fatal(err)
	}
	if len(got) != 3 {
		t.Fatalf("expected 3 tags, got %d", len(got))
	}
}

func TestCreateTag(t *testing.T) {
	_, c := newTestServer(t, func(w http.ResponseWriter, r *http.Request) {
		assertMethod(t, r, http.MethodPost)
		var body map[string]string
		json.NewDecoder(r.Body).Decode(&body)
		if body["name"] != "compliance" {
			t.Errorf("name = %q, want compliance", body["name"])
		}
		writeJSON(t, w, ivector.Tag{ID: "t1", Name: "compliance"})
	})

	got, err := c.CreateTag(bg, "compliance")
	if err != nil {
		t.Fatal(err)
	}
	if got.Name != "compliance" {
		t.Fatalf("Name = %q, want compliance", got.Name)
	}
}

// ── Stats ────────────────────────────────────────────────────────────────────

func TestDashboardStats(t *testing.T) {
	_, c := newTestServer(t, func(w http.ResponseWriter, r *http.Request) {
		assertPath(t, r, "/api/v1/stats/dashboard")
		writeJSON(t, w, ivector.DashboardStats{TotalCollections: 3, TotalVectors: 1000})
	})

	got, err := c.DashboardStats(bg)
	if err != nil {
		t.Fatal(err)
	}
	if got.TotalCollections != 3 {
		t.Fatalf("TotalCollections = %d, want 3", got.TotalCollections)
	}
}

func TestUploadsPerDay(t *testing.T) {
	_, c := newTestServer(t, func(w http.ResponseWriter, r *http.Request) {
		assertPath(t, r, "/api/v1/stats/uploads-per-day")
		if d := r.URL.Query().Get("days"); d != "30" {
			t.Errorf("days = %q, want 30", d)
		}
		writeJSON(t, w, paginated([]ivector.UploadsPerDay{{Date: "2026-03-01", Count: 5}}))
	})

	got, err := c.UploadsPerDay(bg, 30)
	if err != nil {
		t.Fatal(err)
	}
	if len(got) != 1 {
		t.Fatalf("expected 1, got %d", len(got))
	}
}

// ── Progress ─────────────────────────────────────────────────────────────────

func TestActiveJobs(t *testing.T) {
	_, c := newTestServer(t, func(w http.ResponseWriter, r *http.Request) {
		assertPath(t, r, "/api/v1/progress/active")
		writeJSON(t, w, paginated([]ivector.JobProgress{{DocumentID: "d1", Status: "chunking", Percent: 50}}))
	})

	got, err := c.ActiveJobs(bg)
	if err != nil {
		t.Fatal(err)
	}
	if len(got) != 1 || got[0].Status != "chunking" {
		t.Fatalf("unexpected: %v", got)
	}
}

func TestJobProgress(t *testing.T) {
	_, c := newTestServer(t, func(w http.ResponseWriter, r *http.Request) {
		assertPath(t, r, "/api/v1/progress/d1/versions/1")
		writeJSON(t, w, ivector.JobProgress{DocumentID: "d1", Version: 1, Status: "completed", Percent: 100})
	})

	got, err := c.JobProgress(bg, "d1", 1)
	if err != nil {
		t.Fatal(err)
	}
	if got.Status != "completed" {
		t.Fatalf("Status = %q, want completed", got.Status)
	}
}

func TestCancelIngestion(t *testing.T) {
	_, c := newTestServer(t, func(w http.ResponseWriter, r *http.Request) {
		assertMethod(t, r, http.MethodPost)
		assertPath(t, r, "/api/v1/progress/d1/versions/1/cancel")
		writeJSON(t, w, map[string]any{"ok": true})
	})

	got, err := c.CancelIngestion(bg, "d1", 1)
	if err != nil {
		t.Fatal(err)
	}
	if got["ok"] != true {
		t.Fatalf("expected ok=true, got %v", got)
	}
}

// ── Logs ─────────────────────────────────────────────────────────────────────

func TestLogs(t *testing.T) {
	_, c := newTestServer(t, func(w http.ResponseWriter, r *http.Request) {
		assertPath(t, r, "/api/v1/logs")
		if n := r.URL.Query().Get("nivel"); n != "ERROR" {
			t.Errorf("nivel = %q, want ERROR", n)
		}
		writeJSON(t, w, ivector.LogList{
			Items: []ivector.LogEntry{{ID: "l1", Nivel: "ERROR"}},
			Meta:  ivector.PageMeta{Total: 1, Pages: 1},
		})
	})

	got, err := c.Logs(bg, &ivector.LogsOptions{Nivel: "ERROR", PageSize: 25})
	if err != nil {
		t.Fatal(err)
	}
	if len(got.Items) != 1 || got.Meta.Total != 1 {
		t.Fatalf("unexpected: %+v", got)
	}
}

func TestLogFacets(t *testing.T) {
	_, c := newTestServer(t, func(w http.ResponseWriter, r *http.Request) {
		assertPath(t, r, "/api/v1/logs/facets")
		writeJSON(t, w, ivector.LogFacets{Levels: []string{"INFO", "ERROR"}})
	})

	got, err := c.LogFacets(bg)
	if err != nil {
		t.Fatal(err)
	}
	if len(got.Levels) != 2 {
		t.Fatalf("expected 2 levels, got %d", len(got.Levels))
	}
}

func TestExportLogs(t *testing.T) {
	_, c := newTestServer(t, func(w http.ResponseWriter, r *http.Request) {
		assertPath(t, r, "/api/v1/logs/export")
		if f := r.URL.Query().Get("format"); f != "csv" {
			t.Errorf("format = %q, want csv", f)
		}
		io.WriteString(w, "timestamp,nivel\n2026-01-01,ERROR\n")
	})

	b, err := c.ExportLogs(bg, &ivector.ExportLogsOptions{Format: "csv", Limit: 100})
	if err != nil {
		t.Fatal(err)
	}
	if !strings.Contains(string(b), "timestamp,nivel") {
		t.Fatalf("unexpected content: %q", string(b))
	}
}

func TestApiErrorMessage(t *testing.T) {
	err := &ivector.ApiError{StatusCode: 404, Body: "not found"}
	want := "ivector: API error 404: not found"
	if err.Error() != want {
		t.Fatalf("Error() = %q, want %q", err.Error(), want)
	}
}
