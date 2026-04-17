using System.Text.Json.Serialization;

namespace IngestaoVetorial.SDK.Models;

public sealed record EmbeddingModel(
    string Id,
    string Name,
    string Provider,
    int[] Dimensions
);

public sealed record Collection(
    string Id,
    string Name,
    string Alias,
    string? Description,
    bool IsPublic,
    string EmbeddingModel,
    int Dimension,
    int ChunkSize,
    int ChunkOverlap,
    string CreatedAt,
    int DocumentCount,
    string? UserId,
    string? ProjectId
);

public sealed record Document(
    string Id,
    string Name,
    string Size,
    string UploadedAt,
    int VectorCount,
    int ChunkCount,
    int Version,
    string CollectionId,
    string[] Tags,
    int VersionCount
);

public sealed record DocumentMetadata(
    string DocumentType,
    string? Description,
    IReadOnlyList<string> Tags,
    IReadOnlyList<IDictionary<string, object?>> CustomFields
);

public sealed record DocumentVersion(
    int Version,
    string UploadedAt,
    int VectorCount,
    string Checksum,
    string? FilePath,
    string? MarkdownPath,
    bool IsActive
);

public sealed record DocumentDetail(
    string Id,
    string Name,
    string Size,
    string UploadedAt,
    int VectorCount,
    int ChunkCount,
    int Version,
    string CollectionId,
    string[] Tags,
    int VersionCount,
    string Checksum,
    DocumentMetadata Metadata,
    IReadOnlyList<DocumentVersion> Versions
);

public sealed record ChunkMetadata(
    string DocumentPath,
    int PageNumber,
    string Section,
    int StartChar,
    int EndChar,
    string ChunkId,
    string CollectionId,
    string CreatedAt,
    string Model,
    int Dimension
);

public sealed record DocumentChunk(
    int Index,
    string Content,
    int Tokens,
    IReadOnlyList<double> Embedding,
    ChunkMetadata Metadata
);

public sealed record SearchResult(
    string Id,
    double Score,
    string Content,
    string DocumentId,
    string DocumentName,
    string CollectionId,
    string CollectionName,
    int? ChunkIndex,
    IDictionary<string, object?> Metadata
);

public sealed record Tag(
    string Id,
    string Name
);

public sealed record UploadResponse(
    bool Success,
    string DocumentId,
    int VectorCount,
    int Version,
    string? Message
);

public sealed record DashboardStats(
    int TotalCollections,
    int TotalDocuments,
    int TotalVectors,
    [property: JsonPropertyName("total_size_mb")] double TotalSizeMb
);

public sealed record RecentActivity(
    string Id,
    string Action,
    string Entity,
    string Timestamp,
    IDictionary<string, object?> Details
);

public sealed record TopCollection(
    string Id,
    string Name,
    int DocumentCount,
    int VectorCount
);

public sealed record UploadsPerDay(
    string Date,
    int Count
);

public sealed record VectorsPerWeek(
    string WeekStart,
    int Count
);

public sealed record PaginatedList<T>(
    IReadOnlyList<T> Items,
    PaginationMeta? Meta
);

public sealed record PaginationMeta(
    int Skip,
    int Limit,
    int Total,
    [property: JsonPropertyName("has_more")] bool HasMore
);

public sealed record JobProgress(
    string DocumentId,
    string DocumentName,
    int Version,
    string Status,
    double Percent,
    string? Error
);

public sealed record LogEntry(
    string Id,
    string Timestamp,
    string Nivel,
    string App,
    string Endpoint,
    int StatusCode,
    string Acao,
    string? UserId,
    string? ProjectId,
    IDictionary<string, object?> Extra
);

public sealed record PageMeta(
    int Page,
    int PageSize,
    int Total,
    int Pages
);

public sealed record LogList(
    IReadOnlyList<LogEntry> Items,
    PageMeta Meta
);

public sealed record LogFacets(
    string[] Levels,
    string[] Apps,
    string[] Endpoints
);

public sealed record LogSummary(
    int Total,
    [property: JsonPropertyName("byLevel")] IDictionary<string, int> ByLevel
);

// ── Request parameter types ───────────────────────────────────────────────────

public sealed record CollectionsOptions(
    int Skip = 0,
    int Limit = 100,
    string Logic = "and",
    string? UserId = null,
    string? ProjectId = null,
    string? Alias = null,
    string? Query = null
);

public sealed record CreateCollectionParams(
    string Name,
    string EmbeddingModel,
    int Dimension,
    int ChunkSize = 0,        // 0 = server default
    int ChunkOverlap = 0,     // 0 = server default
    string? Description = null,
    string? Alias = null,
    bool IsPublic = false,
    string? UserId = null,
    string? ProjectId = null
);

public sealed record UpdateCollectionParams(
    string? Name = null,
    string? Description = null,
    bool? IsPublic = null
);

public sealed record DocumentsOptions(
    int Skip = 0,
    int Limit = 100,
    string? CollectionId = null
);

public sealed record ReprocessOptions(
    string Mode = "replace",
    int? SourceVersion = null,
    string? ExtractionTool = null
);

public sealed record UploadOptions(
    string CollectionId,
    string DocumentType = "document",
    string Description = "",
    IReadOnlyList<string>? Tags = null,
    IReadOnlyList<IDictionary<string, string>>? CustomFields = null,
    bool OverwriteExisting = false,
    string? EmbeddingModel = null,
    int? Dimension = null,
    string? ExtractionTool = null
);

public sealed record SearchOptions(
    string? CollectionId = null,
    int Limit = 10,
    int Offset = 0,
    double MinScore = 0.0
);

public sealed record LogsOptions(
    int Page = 1,
    int PageSize = 50,
    string OrderBy = "timestamp",
    string OrderDir = "desc",
    string? FromTs = null,
    string? ToTs = null,
    string? Nivel = null,
    string? App = null,
    string? Endpoint = null,
    int? StatusCode = null,
    string? Q = null,
    string? UserId = null,
    string? SessionId = null,
    string? ProjectIds = null
);

public sealed record ExportLogsOptions(
    string Format = "json",
    int Limit = 10000,
    string? FromTs = null,
    string? ToTs = null,
    string? Nivel = null,
    string? App = null,
    string? Endpoint = null,
    int? StatusCode = null,
    string? Q = null,
    string? UserId = null,
    string? SessionId = null,
    string? ProjectIds = null
);
