using System.Net.Http.Json;
using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;
using IngestaoVetorial.SDK.Exceptions;
using IngestaoVetorial.SDK.Models;

namespace IngestaoVetorial.SDK;

/// <summary>
/// Official .NET client for the Ingestão Vetorial API.
/// </summary>
/// <remarks>
/// The client wraps an <see cref="HttpClient"/>. For dependency injection,
/// register an <c>HttpClient</c> and pass it to the constructor.
/// </remarks>
public sealed class IngestaoVetorialClient : IDisposable
{
    private readonly HttpClient _http;
    private readonly bool _ownsHttpClient;

    private static readonly JsonSerializerOptions JsonOpts = new()
    {
        PropertyNamingPolicy        = JsonNamingPolicy.SnakeCaseLower,
        PropertyNameCaseInsensitive = true,
        DefaultIgnoreCondition      = JsonIgnoreCondition.WhenWritingNull,
    };

    /// <summary>
    /// Creates a client that owns its <see cref="HttpClient"/> with a 30-second timeout.
    /// </summary>
    public IngestaoVetorialClient(string baseUrl, string apiKey)
        : this(CreateOwnedHttpClient(baseUrl), apiKey, ownsHttpClient: true)
    {
    }

    private static HttpClient CreateOwnedHttpClient(string baseUrl)
        => new() { BaseAddress = new Uri(baseUrl.Trim().TrimEnd('/') + "/") };

    /// <summary>
    /// Creates a client using an existing <see cref="HttpClient"/>.
    /// Useful for testing (inject a mock handler) or DI containers.
    /// </summary>
    public IngestaoVetorialClient(HttpClient httpClient, string apiKey, bool ownsHttpClient = false)
    {
        _http = httpClient;
        _http.DefaultRequestHeaders.Remove("X-API-Key");
        if (!string.IsNullOrEmpty(apiKey))
            _http.DefaultRequestHeaders.Add("X-API-Key", apiKey);

        _ownsHttpClient = ownsHttpClient;
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    private async Task<T> GetAsync<T>(string path, CancellationToken ct = default)
    {
        var resp = await _http.GetAsync(path, ct);
        return await ReadAsync<T>(resp, ct);
    }

    private async Task<IReadOnlyList<T>> GetItemsAsync<T>(string path, CancellationToken ct = default)
    {
        var resp = await _http.GetAsync(path, ct);
        var body = await ReadBodyAsync(resp, ct);
        var wrapped = JsonSerializer.Deserialize<PaginatedList<T>>(body, JsonOpts);
        if (wrapped?.Items is not null)
            return wrapped.Items;

        return JsonSerializer.Deserialize<IReadOnlyList<T>>(body, JsonOpts)
               ?? throw new InvalidOperationException("API returned null");
    }

    private async Task<IReadOnlyList<T>> GetAllItemsAsync<T>(Func<int, int, string> pathBuilder, int limit = 100, CancellationToken ct = default)
    {
        var items = new List<T>();
        var skip = 0;

        while (true)
        {
            var resp = await _http.GetAsync(pathBuilder(skip, limit), ct);
            var body = await ReadBodyAsync(resp, ct);
            var wrapped = JsonSerializer.Deserialize<PaginatedList<T>>(body, JsonOpts);
            if (wrapped?.Items is not null)
            {
                items.AddRange(wrapped.Items);
                if (!(wrapped.Meta?.HasMore ?? false) || wrapped.Items.Count == 0)
                    break;
                skip += wrapped.Items.Count;
                continue;
            }

            var page = JsonSerializer.Deserialize<IReadOnlyList<T>>(body, JsonOpts)
                       ?? throw new InvalidOperationException("API returned null");
            items.AddRange(page);
            if (page.Count < limit || page.Count == 0)
                break;
            skip += page.Count;
        }

        return items;
    }

    private async Task<byte[]> GetBytesAsync(string path, CancellationToken ct = default)
    {
        var resp = await _http.GetAsync(path, ct);
        if (!resp.IsSuccessStatusCode)
        {
            var body = await resp.Content.ReadAsStringAsync(ct);
            throw new ApiException((int)resp.StatusCode, body);
        }
        return await resp.Content.ReadAsByteArrayAsync(ct);
    }

    private async Task<T> PostAsync<T>(string path, object? payload = null, CancellationToken ct = default)
    {
        HttpContent content = payload is null
            ? new StringContent("{}", Encoding.UTF8, "application/json")
            : JsonContent.Create(payload, options: JsonOpts);
        var resp = await _http.PostAsync(path, content, ct);
        return await ReadAsync<T>(resp, ct);
    }

    private async Task PostNoBodyAsync(string path, CancellationToken ct = default)
    {
        var resp = await _http.PostAsync(path, null, ct);
        if (!resp.IsSuccessStatusCode)
        {
            var body = await resp.Content.ReadAsStringAsync(ct);
            throw new ApiException((int)resp.StatusCode, body);
        }
    }

    private async Task<T> PatchAsync<T>(string path, object payload, CancellationToken ct = default)
    {
        var req = new HttpRequestMessage(HttpMethod.Patch, path)
        {
            Content = JsonContent.Create(payload, options: JsonOpts),
        };
        var resp = await _http.SendAsync(req, ct);
        return await ReadAsync<T>(resp, ct);
    }

    private async Task DeleteAsync(string path, CancellationToken ct = default)
    {
        var resp = await _http.DeleteAsync(path, ct);
        if (!resp.IsSuccessStatusCode && (int)resp.StatusCode != 204)
        {
            var body = await resp.Content.ReadAsStringAsync(ct);
            throw new ApiException((int)resp.StatusCode, body);
        }
    }

    private static async Task<T> ReadAsync<T>(HttpResponseMessage resp, CancellationToken ct)
    {
        var body = await ReadBodyAsync(resp, ct);
        return JsonSerializer.Deserialize<T>(body, JsonOpts)
               ?? throw new InvalidOperationException("API returned null");
    }

    private static async Task<string> ReadBodyAsync(HttpResponseMessage resp, CancellationToken ct)
    {
        var body = await resp.Content.ReadAsStringAsync(ct);
        if (!resp.IsSuccessStatusCode)
            throw new ApiException((int)resp.StatusCode, body);
        return body;
    }

    private static string Q(Dictionary<string, string?> p)
    {
        var pairs = p
            .Where(kv => kv.Value is not null)
            .Select(kv => $"{Uri.EscapeDataString(kv.Key)}={Uri.EscapeDataString(kv.Value!)}");
        var qs = string.Join("&", pairs);
        return qs.Length > 0 ? "?" + qs : string.Empty;
    }

    // ── Collections ───────────────────────────────────────────────────────────

    /// <summary>Returns the list of embedding models available for new collections.</summary>
    public Task<IReadOnlyList<EmbeddingModel>> EmbeddingModelsAsync(CancellationToken ct = default)
        => GetItemsAsync<EmbeddingModel>("api/v1/collections/embedding-models", ct);

    /// <summary>Lists collections with optional filters.</summary>
    public Task<IReadOnlyList<Collection>> CollectionsAsync(CollectionsOptions? opts = null, CancellationToken ct = default)
    {
        opts ??= new();
        var qs = Q(new()
        {
            ["skip"]       = opts.Skip.ToString(),
            ["limit"]      = opts.Limit.ToString(),
            ["logic"]      = opts.Logic,
            ["user_id"]    = opts.UserId,
            ["project_id"] = opts.ProjectId,
            ["alias"]      = opts.Alias,
            ["query"]      = opts.Query,
        });
        return GetItemsAsync<Collection>("api/v1/collections" + qs, ct);
    }

    /// <summary>Creates a new collection.</summary>
    public Task<Collection> CreateCollectionAsync(CreateCollectionParams p, CancellationToken ct = default)
    {
        var payload = new Dictionary<string, object?>
        {
            ["name"]            = p.Name,
            ["embedding_model"] = p.EmbeddingModel,
            ["dimension"]       = p.Dimension,
            ["is_public"]       = p.IsPublic,
        };
        if (p.ChunkSize > 0)       payload["chunk_size"]    = p.ChunkSize;
        if (p.ChunkOverlap > 0)    payload["chunk_overlap"] = p.ChunkOverlap;
        if (p.Description != null) payload["description"]   = p.Description;
        if (p.Alias != null)       payload["alias"]         = p.Alias;
        if (p.UserId != null)      payload["user_id"]       = p.UserId;
        if (p.ProjectId != null)   payload["project_id"]    = p.ProjectId;
        return PostAsync<Collection>("api/v1/collections", payload, ct);
    }

    /// <summary>Fetches a single collection by ID.</summary>
    public Task<Collection> GetCollectionAsync(string collectionId, CancellationToken ct = default)
        => GetAsync<Collection>($"api/v1/collections/{collectionId}", ct);

    /// <summary>Updates a collection's mutable fields.</summary>
    public Task<Collection> UpdateCollectionAsync(string collectionId, UpdateCollectionParams p, CancellationToken ct = default)
    {
        var payload = new Dictionary<string, object?>();
        if (p.Name != null)        payload["name"]        = p.Name;
        if (p.Description != null) payload["description"] = p.Description;
        if (p.IsPublic != null)    payload["is_public"]   = p.IsPublic;
        return PatchAsync<Collection>($"api/v1/collections/{collectionId}", payload, ct);
    }

    /// <summary>Permanently deletes a collection and all its documents.</summary>
    public Task DeleteCollectionAsync(string collectionId, CancellationToken ct = default)
        => DeleteAsync($"api/v1/collections/{collectionId}", ct);

    /// <summary>Returns the raw Qdrant collection info.</summary>
    public Task<IDictionary<string, object?>> CollectionRawAsync(string collectionId, CancellationToken ct = default)
        => GetAsync<IDictionary<string, object?>>($"api/v1/collections/{collectionId}/raw", ct);

    /// <summary>Lists documents in a collection.</summary>
    public Task<IReadOnlyList<Document>> CollectionDocumentsAsync(string collectionId, int skip = 0, int limit = 100, CancellationToken ct = default)
        => GetItemsAsync<Document>($"api/v1/collections/{collectionId}/documents?skip={skip}&limit={limit}", ct);

    // ── Documents ─────────────────────────────────────────────────────────────

    /// <summary>Lists all documents, optionally filtered by collection.</summary>
    public Task<IReadOnlyList<Document>> DocumentsAsync(DocumentsOptions? opts = null, CancellationToken ct = default)
    {
        opts ??= new();
        var qs = Q(new()
        {
            ["skip"]          = opts.Skip.ToString(),
            ["limit"]         = opts.Limit.ToString(),
            ["collection_id"] = opts.CollectionId,
        });
        return GetItemsAsync<Document>("api/v1/documents" + qs, ct);
    }

    /// <summary>Fetches full document details including versions and metadata.</summary>
    public Task<DocumentDetail> DocumentAsync(string documentId, CancellationToken ct = default)
        => GetAsync<DocumentDetail>($"api/v1/documents/{documentId}", ct);

    /// <summary>Returns the chunks for a document version (null = active version).</summary>
    public Task<IReadOnlyList<DocumentChunk>> DocumentChunksAsync(string documentId, int? version = null, string? query = null, CancellationToken ct = default)
    {
        return GetAllItemsAsync<DocumentChunk>((skip, limit) =>
        {
            var qs = Q(new()
            {
                ["version"] = version?.ToString(),
                ["q"] = query,
                ["skip"] = skip.ToString(),
                ["limit"] = limit.ToString(),
            });
            return $"api/v1/documents/{documentId}/chunks{qs}";
        }, 100, ct);
    }

    /// <summary>Downloads the extracted markdown for a document version.</summary>
    public Task<byte[]> DocumentMarkdownAsync(string documentId, int? version = null, CancellationToken ct = default)
    {
        var qs = version.HasValue ? $"?version={version}" : string.Empty;
        return GetBytesAsync($"api/v1/documents/{documentId}/markdown{qs}", ct);
    }

    /// <summary>Deletes a document and all its versions.</summary>
    public Task DeleteDocumentAsync(string documentId, CancellationToken ct = default)
        => DeleteAsync($"api/v1/documents/{documentId}", ct);

    /// <summary>Re-runs the ingestion pipeline for an existing document.</summary>
    public Task<IDictionary<string, object?>> ReprocessDocumentAsync(string documentId, ReprocessOptions? opts = null, CancellationToken ct = default)
    {
        opts ??= new();
        var qs = Q(new()
        {
            ["mode"]             = opts.Mode,
            ["source_version"]   = opts.SourceVersion?.ToString(),
            ["extraction_tool"]  = opts.ExtractionTool,
        });
        return PostAsync<IDictionary<string, object?>>($"api/v1/documents/{documentId}/reprocess{qs}", null, ct);
    }

    /// <summary>Deletes a specific document version.</summary>
    public Task DeleteDocumentVersionAsync(string documentId, int version, CancellationToken ct = default)
        => DeleteAsync($"api/v1/documents/{documentId}/versions/{version}", ct);

    /// <summary>Activates or deactivates a document version.</summary>
    public Task<IDictionary<string, object?>> SetVersionActiveAsync(string documentId, int version, bool isActive, CancellationToken ct = default)
        => PatchAsync<IDictionary<string, object?>>($"api/v1/documents/{documentId}/versions/{version}", new { is_active = isActive }, ct);

    // ── Upload ────────────────────────────────────────────────────────────────

    /// <summary>Uploads a file and starts the ingestion pipeline.</summary>
    public async Task<UploadResponse> UploadAsync(string filename, Stream content, UploadOptions opts, CancellationToken ct = default)
    {
        var meta = JsonSerializer.Serialize(new
        {
            document_type = string.IsNullOrEmpty(opts.DocumentType) ? "document" : opts.DocumentType,
            description   = opts.Description ?? string.Empty,
            tags          = opts.Tags ?? [],
            custom_fields = opts.CustomFields ?? [],
        });

        using var form = new MultipartFormDataContent();
        form.Add(new StreamContent(content), "file", filename);
        form.Add(new StringContent(opts.CollectionId),                               "collection_id");
        form.Add(new StringContent(meta),                                            "metadata");
        form.Add(new StringContent(opts.OverwriteExisting.ToString().ToLower()),     "overwrite_existing");
        if (opts.EmbeddingModel is not null) form.Add(new StringContent(opts.EmbeddingModel), "embedding_model");
        if (opts.Dimension.HasValue)         form.Add(new StringContent(opts.Dimension.Value.ToString()), "dimension");
        if (opts.ExtractionTool is not null) form.Add(new StringContent(opts.ExtractionTool), "extraction_tool");

        var resp = await _http.PostAsync("api/v1/upload", form, ct);
        return await ReadAsync<UploadResponse>(resp, ct);
    }

    // ── Search ────────────────────────────────────────────────────────────────

    /// <summary>Runs a semantic search query.</summary>
    public Task<IReadOnlyList<SearchResult>> SearchAsync(string query, SearchOptions? opts = null, CancellationToken ct = default)
    {
        opts ??= new();
        var qs = Q(new()
        {
            ["query"]         = query,
            ["limit"]         = opts.Limit.ToString(),
            ["offset"]        = opts.Offset.ToString(),
            ["min_score"]     = opts.MinScore.ToString("G", System.Globalization.CultureInfo.InvariantCulture),
            ["collection_id"] = opts.CollectionId,
        });
        return GetItemsAsync<SearchResult>("api/v1/search" + qs, ct);
    }

    // ── Tags ──────────────────────────────────────────────────────────────────

    /// <summary>Lists all tags.</summary>
    public Task<IReadOnlyList<string>> TagsAsync(int skip = 0, int limit = 100, CancellationToken ct = default)
        => GetItemsAsync<string>($"api/v1/tags?skip={skip}&limit={limit}", ct);

    /// <summary>Searches tags by partial name.</summary>
    public Task<IReadOnlyList<string>> SearchTagsAsync(string q, CancellationToken ct = default)
        => GetItemsAsync<string>($"api/v1/tags/search?q={Uri.EscapeDataString(q)}", ct);

    /// <summary>Creates a new tag.</summary>
    public Task<Tag> CreateTagAsync(string name, CancellationToken ct = default)
        => PostAsync<Tag>("api/v1/tags", new { name }, ct);

    // ── Stats ─────────────────────────────────────────────────────────────────

    /// <summary>Returns aggregate system metrics.</summary>
    public Task<DashboardStats> DashboardStatsAsync(CancellationToken ct = default)
        => GetAsync<DashboardStats>("api/v1/stats/dashboard", ct);

    /// <summary>Returns recent ingestion/upload activity.</summary>
    public Task<IReadOnlyList<RecentActivity>> RecentActivityAsync(int limit = 5, CancellationToken ct = default)
        => GetItemsAsync<RecentActivity>($"api/v1/stats/activity?limit={limit}", ct);

    /// <summary>Returns the top collections by document/vector count.</summary>
    public Task<IReadOnlyList<TopCollection>> TopCollectionsAsync(int limit = 5, CancellationToken ct = default)
        => GetItemsAsync<TopCollection>($"api/v1/stats/top-collections?limit={limit}", ct);

    /// <summary>Returns upload counts grouped by day.</summary>
    public Task<IReadOnlyList<UploadsPerDay>> UploadsPerDayAsync(int days = 7, CancellationToken ct = default)
        => GetItemsAsync<UploadsPerDay>($"api/v1/stats/uploads-per-day?days={days}", ct);

    /// <summary>Returns vector counts grouped by week.</summary>
    public Task<IReadOnlyList<VectorsPerWeek>> VectorsPerWeekAsync(int weeks = 6, CancellationToken ct = default)
        => GetItemsAsync<VectorsPerWeek>($"api/v1/stats/vectors-per-week?weeks={weeks}", ct);

    // ── Progress ──────────────────────────────────────────────────────────────

    /// <summary>Returns all currently active ingestion jobs.</summary>
    public Task<IReadOnlyList<JobProgress>> ActiveJobsAsync(CancellationToken ct = default)
        => GetAllItemsAsync<JobProgress>((skip, limit) => $"api/v1/progress/active?skip={skip}&limit={limit}", 100, ct);

    /// <summary>Returns the ingestion progress for a specific document version.</summary>
    public Task<JobProgress> JobProgressAsync(string documentId, int version, CancellationToken ct = default)
        => GetAsync<JobProgress>($"api/v1/progress/{documentId}/versions/{version}", ct);

    /// <summary>Requests cancellation of an in-progress ingestion job.</summary>
    public Task<IDictionary<string, object?>> CancelIngestionAsync(string documentId, int version, CancellationToken ct = default)
        => PostAsync<IDictionary<string, object?>>($"api/v1/progress/{documentId}/versions/{version}/cancel", null, ct);

    // ── Logs ──────────────────────────────────────────────────────────────────

    /// <summary>Queries the application log store with filters and pagination.</summary>
    public Task<LogList> LogsAsync(LogsOptions? opts = null, CancellationToken ct = default)
    {
        opts ??= new();
        var qs = Q(new()
        {
            ["page"]        = opts.Page.ToString(),
            ["page_size"]   = opts.PageSize.ToString(),
            ["order_by"]    = opts.OrderBy,
            ["order_dir"]   = opts.OrderDir,
            ["from_ts"]     = opts.FromTs,
            ["to_ts"]       = opts.ToTs,
            ["nivel"]       = opts.Nivel,
            ["app"]         = opts.App,
            ["endpoint"]    = opts.Endpoint,
            ["status_code"] = opts.StatusCode?.ToString(),
            ["q"]           = opts.Q,
            ["user_id"]     = opts.UserId,
            ["session_id"]  = opts.SessionId,
            ["project_ids"] = opts.ProjectIds,
        });
        return GetAsync<LogList>("api/v1/logs" + qs, ct);
    }

    /// <summary>Returns the distinct filter values for the log viewer.</summary>
    public Task<LogFacets> LogFacetsAsync(CancellationToken ct = default)
        => GetAsync<LogFacets>("api/v1/logs/facets", ct);

    /// <summary>Returns aggregated log statistics for an optional time window.</summary>
    public Task<LogSummary> LogSummaryAsync(string? fromTs = null, string? toTs = null, CancellationToken ct = default)
    {
        var qs = Q(new() { ["from_ts"] = fromTs, ["to_ts"] = toTs });
        return GetAsync<LogSummary>("api/v1/logs/summary" + qs, ct);
    }

    /// <summary>Exports logs as bytes in JSON or CSV format.</summary>
    public Task<byte[]> ExportLogsAsync(ExportLogsOptions? opts = null, CancellationToken ct = default)
    {
        opts ??= new();
        var qs = Q(new()
        {
            ["format"]      = opts.Format,
            ["limit"]       = opts.Limit.ToString(),
            ["from_ts"]     = opts.FromTs,
            ["to_ts"]       = opts.ToTs,
            ["nivel"]       = opts.Nivel,
            ["app"]         = opts.App,
            ["endpoint"]    = opts.Endpoint,
            ["status_code"] = opts.StatusCode?.ToString(),
            ["q"]           = opts.Q,
            ["user_id"]     = opts.UserId,
            ["session_id"]  = opts.SessionId,
            ["project_ids"] = opts.ProjectIds,
        });
        return GetBytesAsync("api/v1/logs/export" + qs, ct);
    }

    public void Dispose()
    {
        if (_ownsHttpClient)
            _http.Dispose();
    }
}
