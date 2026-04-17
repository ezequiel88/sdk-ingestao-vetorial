# ingestao-vetorial-sdk · C# / .NET

SDK .NET oficial para a API do **Ingestão Vetorial** — sistema de ingestão e busca vetorial com suporte a RAG.

Usa apenas `System.Net.Http` e `System.Text.Json` da BCL. Zero dependências externas.  
Totalmente async/await, com suporte a `CancellationToken` em todos os métodos.

Os endpoints de lista da API retornam `items` e `meta`, mas o SDK mantém compatibilidade e continua expondo `IReadOnlyList<T>` nesses métodos, desempacotando `items` internamente.

---

## Índice

- [Requisitos](#requisitos)
- [Instalação](#instalação)
- [Início rápido](#início-rápido)
- [Integração com ASP.NET Core (DI)](#integração-com-aspnet-core)
- [Tratamento de erros](#tratamento-de-erros)
- [Referência](#referência)
- [Executar testes](#executar-testes)

---

## Requisitos

- .NET >= 8.0

---

## Instalação

```bash
# NuGet (após publicação)
dotnet add package IngestaoVetorial.SDK

# Desenvolvimento local
dotnet add reference ./sdk/csharp/IngestaoVetorial.SDK/IngestaoVetorial.SDK.csproj
```

---

## Início rápido

```csharp
using IngestaoVetorial.SDK;
using IngestaoVetorial.SDK.Models;

var client = new IngestaoVetorialClient("http://localhost:8000", "sua-api-key");

// Criar coleção
var col = await client.CreateCollectionAsync(new CreateCollectionParams(
    Name:           "Documentos Jurídicos",
    EmbeddingModel: "text-embedding-3-small",
    Dimension:      1536
));
Console.WriteLine($"Coleção: {col.Id}");

// Upload de arquivo
await using var stream = File.OpenRead("contrato.pdf");
var resp = await client.UploadAsync("contrato.pdf", stream, new UploadOptions(
    CollectionId: col.Id,
    DocumentType: "contract",
    Tags:         ["jurídico", "2024"]
));
Console.WriteLine($"Documento: {resp.DocumentId}");

// Busca semântica
var results = await client.SearchAsync("cláusula de rescisão", new SearchOptions(
    CollectionId: col.Id,
    Limit:        5,
    MinScore:     0.75
));
foreach (var r in results)
    Console.WriteLine($"[{r.Score:F2}] {r.DocumentName}: {r.Content[..Math.Min(120, r.Content.Length)]}");
```

---

## Integração com ASP.NET Core

```csharp
// Program.cs
builder.Services.AddHttpClient<IngestaoVetorialClient>(http =>
{
    http.BaseAddress = new Uri(builder.Configuration["IngestaoVetorial:BaseUrl"]!);
});

// Registration helper — wrap in a keyed service or extension method as needed
builder.Services.AddScoped(sp =>
{
    var http = sp.GetRequiredService<IHttpClientFactory>().CreateClient();
    http.BaseAddress = new Uri(builder.Configuration["IngestaoVetorial:BaseUrl"]!);
    return new IngestaoVetorialClient(http, builder.Configuration["IngestaoVetorial:ApiKey"]!);
});
```

Then inject in any controller or service:

```csharp
public class DocumentService(IngestaoVetorialClient client) { ... }
```

---

## Tratamento de erros

```csharp
using IngestaoVetorial.SDK.Exceptions;

try
{
    var doc = await client.DocumentAsync("id-inexistente");
}
catch (ApiException ex)
{
    Console.Error.WriteLine($"Erro {ex.StatusCode}: {ex.ResponseBody}");
}
catch (HttpRequestException ex)
{
    // Timeout, DNS failure, etc.
    Console.Error.WriteLine($"Erro de rede: {ex.Message}");
}
```

---

## Referência

### Construção do cliente

```csharp
// Simples (cria HttpClient internamente)
var client = new IngestaoVetorialClient(baseUrl, apiKey);

// Injeção de HttpClient (recomendado para produção / DI / testes)
var client = new IngestaoVetorialClient(httpClient, apiKey);
```

### Coleções

| Método | Descrição |
|---|---|
| `EmbeddingModelsAsync()` | Lista modelos disponíveis |
| `CollectionsAsync(opts?)` | Lista coleções |
| `CreateCollectionAsync(params)` | Cria coleção |
| `GetCollectionAsync(id)` | Busca coleção |
| `UpdateCollectionAsync(id, params)` | Atualiza campos |
| `DeleteCollectionAsync(id)` | Deleta coleção |
| `CollectionRawAsync(id)` | Info bruta do Qdrant |
| `CollectionDocumentsAsync(id, skip, limit)` | Lista documentos da coleção |

### Documentos

| Método | Descrição |
|---|---|
| `DocumentsAsync(opts?)` | Lista documentos |
| `DocumentAsync(id)` | Detalhes de documento |
| `DocumentChunksAsync(id, version?, query?)` | Chunks de um documento, com filtro opcional por conteúdo |
| `DocumentMarkdownAsync(id, version?)` | Markdown extraído (bytes) |
| `DeleteDocumentAsync(id)` | Deleta documento |
| `ReprocessDocumentAsync(id, opts?)` | Reinicia ingestão |
| `DeleteDocumentVersionAsync(id, version)` | Deleta versão |
| `SetVersionActiveAsync(id, version, isActive)` | Ativa/desativa versão |

### Upload

```csharp
await using var stream = File.OpenRead("arquivo.pdf");
var resp = await client.UploadAsync("arquivo.pdf", stream, new UploadOptions(
    CollectionId:      "uuid",
    DocumentType:      "report",
    Tags:              ["rh", "2024"],
    OverwriteExisting: true
));
```

### Busca

```csharp
var results = await client.SearchAsync("rescisão contratual", new SearchOptions(
    CollectionId: "uuid",
    Limit:        10,
    MinScore:     0.7
));

var filteredChunks = await client.DocumentChunksAsync("doc-id", version: 1, query: "cláusula penal");
```

O mesmo desempacotamento automático é aplicado em `EmbeddingModelsAsync()`, `CollectionsAsync()`, `CollectionDocumentsAsync()`, `DocumentsAsync()`, `SearchAsync()`, `TagsAsync()`, `SearchTagsAsync()`, `RecentActivityAsync()`, `TopCollectionsAsync()`, `UploadsPerDayAsync()`, `VectorsPerWeekAsync()` e `ActiveJobsAsync()`.

### Estatísticas

```csharp
var stats    = await client.DashboardStatsAsync();
var activity = await client.RecentActivityAsync(limit: 10);
var top      = await client.TopCollectionsAsync(limit: 5);
var uploads  = await client.UploadsPerDayAsync(days: 30);
var vectors  = await client.VectorsPerWeekAsync(weeks: 12);
```

### Progresso de ingestão

```csharp
var jobs = await client.ActiveJobsAsync();

// Poll até completar
while (true)
{
    var p = await client.JobProgressAsync(docId, 1);
    Console.WriteLine($"{p.Status} {p.Percent:F0}%");
    if (p.Status is "completed" or "error" or "cancelled") break;
    await Task.Delay(TimeSpan.FromSeconds(2));
}

// Cancelar
await client.CancelIngestionAsync(docId, 1);
```

### Logs

```csharp
var logs = await client.LogsAsync(new LogsOptions(Nivel: "ERROR", PageSize: 25));

var facets  = await client.LogFacetsAsync();
var summary = await client.LogSummaryAsync("2026-01-01T00:00:00Z", "2026-03-31T23:59:59Z");

// Exportar como CSV
var data = await client.ExportLogsAsync(new ExportLogsOptions(Format: "csv", Limit: 500));
await File.WriteAllBytesAsync("erros.csv", data);
```

---

## Executar testes

```bash
dotnet test ./sdk/csharp/ --logger "console;verbosity=normal"
```

---

## Licença

MIT
