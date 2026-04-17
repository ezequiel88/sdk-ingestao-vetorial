using System.Net;
using System.Text;
using System.Text.Json;
using IngestaoVetorial.SDK;
using IngestaoVetorial.SDK.Exceptions;
using IngestaoVetorial.SDK.Models;
using Xunit;
using System.Reflection;

namespace IngestaoVetorial.SDK.Tests;

// ── Mock HTTP handler ─────────────────────────────────────────────────────────

file sealed class MockHandler : HttpMessageHandler
{
    private readonly Func<HttpRequestMessage, HttpResponseMessage> _respond;
    public HttpRequestMessage? LastRequest { get; private set; }

    public MockHandler(Func<HttpRequestMessage, HttpResponseMessage> respond)
        => _respond = respond;

    protected override Task<HttpResponseMessage> SendAsync(HttpRequestMessage request, CancellationToken ct)
    {
        LastRequest = request;
        return Task.FromResult(_respond(request));
    }
}

file static class Helpers
{
    private static readonly JsonSerializerOptions Opts = new()
    {
        PropertyNamingPolicy        = JsonNamingPolicy.SnakeCaseLower,
        PropertyNameCaseInsensitive = true,
    };

    public static HttpResponseMessage Json(object data, HttpStatusCode code = HttpStatusCode.OK)
    {
        var json = JsonSerializer.Serialize(data, Opts);
        return new HttpResponseMessage(code)
        {
            Content = new StringContent(json, Encoding.UTF8, "application/json"),
        };
    }

    public static object Paginated(object items, bool hasMore = false)
        => new
        {
            items,
            meta = new { skip = 0, limit = 100, total = 1, has_more = hasMore },
        };

    public static HttpResponseMessage Error(HttpStatusCode code, string body = "error")
        => new(code) { Content = new StringContent(body, Encoding.UTF8, "application/json") };

    public static IngestaoVetorialClient Client(Func<HttpRequestMessage, HttpResponseMessage> handler,
        out MockHandler mock)
    {
        mock = new MockHandler(handler);
        var http = new HttpClient(mock) { BaseAddress = new Uri("http://test/") };
        return new IngestaoVetorialClient(http, "test-key");
    }
}

// ── Collections ───────────────────────────────────────────────────────────────

public class CollectionsTests
{
    [Fact]
    public void Constructor_NormalizesBaseUrlBeforeAssigningBaseAddress()
    {
        using var client = new IngestaoVetorialClient(" http://localhost:8000/// ", "test-key");

        var httpField = typeof(IngestaoVetorialClient).GetField("_http", BindingFlags.NonPublic | BindingFlags.Instance);
        var http = Assert.IsType<HttpClient>(httpField?.GetValue(client));

        Assert.Equal(new Uri("http://localhost:8000/"), http.BaseAddress);
    }

    [Fact]
    public async Task EmbeddingModels_ReturnsModels()
    {
        var data = Helpers.Paginated(new[] { new { Id = "m1", Name = "Model 1", Provider = "openai", Dimensions = new[] { 1536 } } });
        using var c = Helpers.Client(_ => Helpers.Json(data), out var mock);

        var result = await c.EmbeddingModelsAsync();

        Assert.Single(result);
        Assert.Equal("GET", mock.LastRequest!.Method.Method);
        Assert.Contains("embedding-models", mock.LastRequest.RequestUri!.PathAndQuery);
        Assert.Equal("test-key", mock.LastRequest.Headers.GetValues("X-API-Key").First());
    }

    [Fact]
    public async Task Collections_WithQuery_SendsQueryParam()
    {
        using var c = Helpers.Client(_ => Helpers.Json(Helpers.Paginated(Array.Empty<Collection>())), out var mock);

        await c.CollectionsAsync(new CollectionsOptions(Query: "test"));

        Assert.Contains("query=test", mock.LastRequest!.RequestUri!.Query);
    }

    [Fact]
    public async Task CreateCollection_PostsCorrectPayload()
    {
        var col = new { Id = "c1", Name = "Test", EmbeddingModel = "m1", Dimension = 1536 };
        using var c = Helpers.Client(_ => Helpers.Json(col, HttpStatusCode.Created), out var mock);

        var result = await c.CreateCollectionAsync(new CreateCollectionParams("Test", "m1", 1536));

        Assert.Equal("POST", mock.LastRequest!.Method.Method);
        Assert.Contains("collections", mock.LastRequest.RequestUri!.PathAndQuery);
    }

    [Fact]
    public async Task GetCollection_ReturnsCollection()
    {
        var col = new { Id = "abc", Name = "Coll", EmbeddingModel = "m1", Dimension = 1536 };
        using var c = Helpers.Client(_ => Helpers.Json(col), out var mock);

        await c.GetCollectionAsync("abc");

        Assert.Contains("/abc", mock.LastRequest!.RequestUri!.PathAndQuery);
    }

    [Fact]
    public async Task UpdateCollection_SendsPatch()
    {
        var col = new { Id = "abc", Name = "Renamed", EmbeddingModel = "m1", Dimension = 1536 };
        using var c = Helpers.Client(_ => Helpers.Json(col), out var mock);

        await c.UpdateCollectionAsync("abc", new UpdateCollectionParams(Name: "Renamed"));

        Assert.Equal("PATCH", mock.LastRequest!.Method.Method);
    }

    [Fact]
    public async Task DeleteCollection_SendsDelete()
    {
        using var c = Helpers.Client(_ => Helpers.Error(HttpStatusCode.NoContent, ""), out var mock);

        await c.DeleteCollectionAsync("abc");

        Assert.Equal("DELETE", mock.LastRequest!.Method.Method);
    }
}

// ── Documents ─────────────────────────────────────────────────────────────────

public class DocumentsTests
{
    [Fact]
    public async Task Documents_ReturnsDocuments()
    {
        var docs = Helpers.Paginated(new[] { new { Id = "d1", Name = "doc.pdf", Size = "1 MB", UploadedAt = "2024-01-01T00:00:00Z", VectorCount = 10, ChunkCount = 1, Version = 1, CollectionId = "c1", Tags = Array.Empty<string>(), VersionCount = 1 } });
        using var c = Helpers.Client(_ => Helpers.Json(docs), out _);

        var result = await c.DocumentsAsync();

        Assert.Single(result);
    }

    [Fact]
    public async Task Document_ReturnsDetail()
    {
        var doc = new { Id = "d1", Name = "doc.pdf", CollectionId = "c1", DocumentType = "report", Versions = new object[] { } };
        using var c = Helpers.Client(_ => Helpers.Json(doc), out var mock);

        await c.DocumentAsync("d1");

        Assert.Contains("/d1", mock.LastRequest!.RequestUri!.PathAndQuery);
    }

    [Fact]
    public async Task DocumentChunks_WithVersion_SendsVersionParam()
    {
        using var c = Helpers.Client(_ => Helpers.Json(Helpers.Paginated(Array.Empty<object>())), out var mock);

        await c.DocumentChunksAsync("d1", version: 2);

        Assert.Contains("version=2", mock.LastRequest!.RequestUri!.Query);
    }

    [Fact]
    public async Task DocumentMarkdown_ReturnBytes()
    {
        var expected = "# Hello"u8.ToArray();
        using var c = Helpers.Client(_ => new HttpResponseMessage(HttpStatusCode.OK)
        {
            Content = new ByteArrayContent(expected),
        }, out _);

        var result = await c.DocumentMarkdownAsync("d1");

        Assert.Equal(expected, result);
    }

    [Fact]
    public async Task ReprocessDocument_SendsPostWithQueryParams()
    {
        using var c = Helpers.Client(_ => Helpers.Json(new { version = 2 }), out var mock);

        await c.ReprocessDocumentAsync("d1", new ReprocessOptions(Mode: "append"));

        Assert.Equal("POST", mock.LastRequest!.Method.Method);
        Assert.Contains("mode=append", mock.LastRequest.RequestUri!.Query);
    }
}

// ── Upload ────────────────────────────────────────────────────────────────────

public class UploadTests
{
    [Fact]
    public async Task Upload_SendsMultipartForm()
    {
        var resp = new { Success = true, DocumentId = "d1", VectorCount = 0, Version = 1 };
        string? contentType = null;
        using var c = Helpers.Client(req =>
        {
            contentType = req.Content?.Headers?.ContentType?.MediaType;
            return Helpers.Json(resp);
        }, out _);

        using var stream = new MemoryStream("file content"u8.ToArray());
        var result = await c.UploadAsync("file.pdf", stream, new UploadOptions("col1"));

        Assert.Equal("multipart/form-data", contentType);
        Assert.True(result.Success);
        Assert.Equal("d1", result.DocumentId);
    }
}

// ── Search ────────────────────────────────────────────────────────────────────

public class SearchTests
{
    [Fact]
    public async Task Search_SendsQueryParam()
    {
        var hits = Helpers.Paginated(new[] { new { Id = "r1", Score = 0.95, Content = "snippet", DocumentName = "doc.pdf", CollectionId = "c1", CollectionName = "Coll" } });
        using var c = Helpers.Client(_ => Helpers.Json(hits), out var mock);

        var result = await c.SearchAsync("machine learning", new SearchOptions(Limit: 5));

        Assert.Single(result);
        Assert.Contains("query=machine%20learning", mock.LastRequest!.RequestUri!.Query);
        Assert.Contains("limit=5", mock.LastRequest.RequestUri!.Query);
    }

    [Fact]
    public async Task Search_ApiError_ThrowsApiException()
    {
        using var c = Helpers.Client(_ => Helpers.Error(HttpStatusCode.Unauthorized, "unauthorized"), out _);

        var ex = await Assert.ThrowsAsync<ApiException>(() => c.SearchAsync("query"));

        Assert.Equal(401, ex.StatusCode);
    }
}

// ── Tags ──────────────────────────────────────────────────────────────────────

public class TagsTests
{
    [Fact]
    public async Task Tags_ReturnsList()
    {
        using var c = Helpers.Client(_ => Helpers.Json(Helpers.Paginated(new[] { "go", "python" })), out _);

        var result = await c.TagsAsync();

        Assert.Equal(2, result.Count);
    }

    [Fact]
    public async Task CreateTag_PostsName()
    {
        using var c = Helpers.Client(_ => Helpers.Json(new { Id = "t1", Name = "compliance" }), out var mock);

        var tag = await c.CreateTagAsync("compliance");

        Assert.Equal("POST", mock.LastRequest!.Method.Method);
        Assert.Equal("compliance", tag.Name);
    }
}

// ── Stats ─────────────────────────────────────────────────────────────────────

public class StatsTests
{
    [Fact]
    public async Task DashboardStats_ReturnsStats()
    {
        using var c = Helpers.Client(_ => Helpers.Json(new { TotalCollections = 3, TotalDocuments = 10, TotalVectors = 500, TotalSizeMb = 1.5 }), out _);

        var result = await c.DashboardStatsAsync();

        Assert.Equal(3, result.TotalCollections);
    }

    [Fact]
    public async Task UploadsPerDay_SendsDaysParam()
    {
        using var c = Helpers.Client(_ => Helpers.Json(Helpers.Paginated(new[] { new { Date = "2026-01-01", Count = 5 } })), out var mock);

        await c.UploadsPerDayAsync(days: 30);

        Assert.Contains("days=30", mock.LastRequest!.RequestUri!.Query);
    }
}

// ── Progress ──────────────────────────────────────────────────────────────────

public class ProgressTests
{
    [Fact]
    public async Task ActiveJobs_ReturnsJobs()
    {
        using var c = Helpers.Client(_ => Helpers.Json(Helpers.Paginated(new[] { new { DocumentId = "d1", DocumentName = "doc.pdf", Version = 1, Status = "chunking", Percent = 50.0 } })), out _);

        var result = await c.ActiveJobsAsync();

        Assert.Single(result);
        Assert.Equal("chunking", result[0].Status);
    }

    [Fact]
    public async Task JobProgress_PathContainsDocumentAndVersion()
    {
        using var c = Helpers.Client(_ => Helpers.Json(new { DocumentId = "d1", Version = 1, Status = "completed", Percent = 100.0 }), out var mock);

        var result = await c.JobProgressAsync("d1", 1);

        Assert.Contains("/d1/versions/1", mock.LastRequest!.RequestUri!.PathAndQuery);
        Assert.Equal("completed", result.Status);
    }

    [Fact]
    public async Task CancelIngestion_SendsPost()
    {
        using var c = Helpers.Client(_ => Helpers.Json(new Dictionary<string, object?> { ["ok"] = true }), out var mock);

        await c.CancelIngestionAsync("d1", 1);

        Assert.Equal("POST", mock.LastRequest!.Method.Method);
        Assert.Contains("cancel", mock.LastRequest.RequestUri!.PathAndQuery);
    }
}

// ── Logs ──────────────────────────────────────────────────────────────────────

public class LogsTests
{
    [Fact]
    public async Task Logs_WithNivel_SendsNivelParam()
    {
        var list = new { Items = new object[] { }, Meta = new { Page = 1, PageSize = 50, Total = 0, Pages = 0 } };
        using var c = Helpers.Client(_ => Helpers.Json(list), out var mock);

        await c.LogsAsync(new LogsOptions(Nivel: "ERROR"));

        Assert.Contains("nivel=ERROR", mock.LastRequest!.RequestUri!.Query);
    }

    [Fact]
    public async Task LogFacets_PathIsCorrect()
    {
        using var c = Helpers.Client(_ => Helpers.Json(new { Levels = new[] { "INFO" }, Apps = new string[] { }, Endpoints = new string[] { } }), out var mock);

        await c.LogFacetsAsync();

        Assert.Contains("facets", mock.LastRequest!.RequestUri!.PathAndQuery);
    }

    [Fact]
    public async Task ExportLogs_SendsFormatParam()
    {
        using var c = Helpers.Client(_ => new HttpResponseMessage(HttpStatusCode.OK)
        {
            Content = new StringContent("timestamp,nivel", Encoding.UTF8, "text/csv"),
        }, out var mock);

        var data = await c.ExportLogsAsync(new ExportLogsOptions(Format: "csv"));

        Assert.Contains("format=csv", mock.LastRequest!.RequestUri!.Query);
        Assert.NotEmpty(data);
    }

    [Fact]
    public void ApiException_HasCorrectStatusCodeAndMessage()
    {
        var ex = new ApiException(422, "validation error");
        Assert.Equal(422, ex.StatusCode);
        Assert.Contains("422", ex.Message);
    }
}
