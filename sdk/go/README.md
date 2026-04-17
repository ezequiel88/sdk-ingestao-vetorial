# ingestao-vetorial-sdk · Go

SDK Go oficial para a API do **Ingestão Vetorial** — sistema de ingestão e busca vetorial com suporte a RAG.

Usa apenas a biblioteca padrão do Go (`net/http`). Zero dependências externas.

Os endpoints de lista da API respondem com `items` e `meta`. O SDK continua retornando slices diretamente nesses métodos, desempacotando `items` de forma transparente para manter compatibilidade.

---

## Índice

- [Requisitos](#requisitos)
- [Instalação](#instalação)
- [Início rápido](#início-rápido)
- [Tratamento de erros](#tratamento-de-erros)
- [Referência](#referência)
- [Executar testes](#executar-testes)

---

## Requisitos

- Go >= 1.22

---

## Instalação

```bash
go get github.com/ezequiel88/sdk-ingestao-vetorial/sdk/go@v0.1.0
```

---

## Início rápido

```go
package main

import (
    "context"
    "fmt"
    "log"
    "os"
    "strings"

    ivector "github.com/ezequiel88/sdk-ingestao-vetorial/sdk/go"
)

func main() {
    ctx := context.Background()

    c := ivector.New("http://localhost:8000", "sua-api-key")

    // Criar coleção
    col, err := c.CreateCollection(ctx, ivector.CreateCollectionParams{
        Name:           "Documentos Jurídicos",
        EmbeddingModel: "text-embedding-3-small",
        Dimension:      1536,
    })
    if err != nil {
        log.Fatal(err)
    }
    fmt.Println("Coleção criada:", col.ID)

    // Upload de arquivo
    f, _ := os.Open("contrato.pdf")
    defer f.Close()
    resp, err := c.Upload(ctx, "contrato.pdf", f, ivector.UploadOptions{
        CollectionID: col.ID,
        DocumentType: "contract",
        Tags:         []string{"jurídico", "2024"},
    })
    if err != nil {
        log.Fatal(err)
    }
    fmt.Println("Documento:", resp.DocumentID)

    // Busca semântica
    results, err := c.Search(ctx, "cláusula de rescisão", &ivector.SearchOptions{
        CollectionID: col.ID,
        Limit:        5,
        MinScore:     0.75,
    })
    if err != nil {
        log.Fatal(err)
    }
    for _, r := range results {
        content := r.Content
        if len(content) > 120 {
            content = content[:120]
        }
        fmt.Printf("[%.2f] %s: %s\n", r.Score, r.DocumentName, content)
    }

    // Upload a partir de string (io.Reader)
    body := strings.NewReader("conteúdo do documento em texto")
    r2, _ := c.Upload(ctx, "nota.txt", body, ivector.UploadOptions{CollectionID: col.ID})
    fmt.Println("Doc 2:", r2.DocumentID)
}
```

---

## Tratamento de erros

Erros HTTP da API são retornados como `*ivector.ApiError`:

```go
_, err := c.GetCollection(ctx, "id-inexistente")
if err != nil {
    var apiErr *ivector.ApiError
    if errors.As(err, &apiErr) {
        fmt.Printf("Erro %d: %s\n", apiErr.StatusCode, apiErr.Body)
    }
}
```

Erros de rede/timeout são retornados diretamente do `net/http`.

---

## Referência

### Criação do cliente

```go
c := ivector.New(baseURL, apiKey,
    ivector.WithTimeout(60*time.Second),
    ivector.WithHTTPClient(myHttpClient), // para testes ou proxies
)
```

### Coleções

| Método | Descrição |
|---|---|
| `EmbeddingModels(ctx)` | Lista modelos disponíveis |
| `Collections(ctx, *CollectionsOptions)` | Lista coleções |
| `CreateCollection(ctx, CreateCollectionParams)` | Cria coleção |
| `GetCollection(ctx, id)` | Busca coleção |
| `UpdateCollection(ctx, id, UpdateCollectionParams)` | Atualiza coleção |
| `DeleteCollection(ctx, id)` | Deleta coleção |
| `CollectionRaw(ctx, id)` | Info bruta do Qdrant |
| `CollectionDocuments(ctx, id, skip, limit)` | Lista documentos da coleção |

### Documentos

| Método | Descrição |
|---|---|
| `Documents(ctx, *DocumentsOptions)` | Lista documentos |
| `Document(ctx, id)` | Detalhes de um documento |
| `DocumentChunks(ctx, id, *version)` | Chunks de um documento |
| `DocumentChunksSearch(ctx, id, *version, query)` | Chunks filtrados por conteúdo |
| `DocumentMarkdown(ctx, id, *version)` | Markdown extraído (bytes) |
| `DeleteDocument(ctx, id)` | Deleta documento |
| `ReprocessDocument(ctx, id, *ReprocessOptions)` | Reinicia ingestão |
| `DeleteDocumentVersion(ctx, id, version)` | Deleta versão |
| `SetVersionActive(ctx, id, version, isActive)` | Ativa/desativa versão |

### Upload

```go
f, _ := os.Open("arquivo.pdf")
defer f.Close()
resp, err := c.Upload(ctx, "arquivo.pdf", f, ivector.UploadOptions{
    CollectionID:      "uuid",
    DocumentType:      "report",
    Tags:              []string{"rh", "2024"},
    OverwriteExisting: true,
})
```

### Busca

```go
results, err := c.Search(ctx, "rescisão contratual", &ivector.SearchOptions{
    CollectionID: "uuid",
    Limit:        10,
    MinScore:     0.7,
})

filteredChunks, err := c.DocumentChunksSearch(ctx, "doc-id", nil, "cláusula penal")
```

O mesmo desempacotamento automático vale para `EmbeddingModels`, `Collections`, `CollectionDocuments`, `Documents`, `Search`, `Tags`, `SearchTags`, `RecentActivity`, `TopCollections`, `UploadsPerDay`, `VectorsPerWeek` e `ActiveJobs`.

### Tags

```go
tags, _  := c.Tags(ctx, 0, 100)
found, _ := c.SearchTags(ctx, "fin")
tag, _   := c.CreateTag(ctx, "compliance")
```

### Estatísticas

```go
stats, _   := c.DashboardStats(ctx)
activity, _ := c.RecentActivity(ctx, 10)
top, _      := c.TopCollections(ctx, 5)
uploads, _  := c.UploadsPerDay(ctx, 30)
vectors, _  := c.VectorsPerWeek(ctx, 12)
```

### Progresso de ingestão

```go
jobs, _ := c.ActiveJobs(ctx)

// Poll até completar
for {
    p, _ := c.JobProgress(ctx, docID, 1)
    fmt.Println(p.Status, p.Percent)
    if p.Status == "completed" || p.Status == "error" {
        break
    }
    time.Sleep(2 * time.Second)
}

// Cancelar
c.CancelIngestion(ctx, docID, 1)
```

### Logs

```go
logs, _ := c.Logs(ctx, &ivector.LogsOptions{
    Nivel:    "ERROR",
    PageSize: 25,
})

facets, _  := c.LogFacets(ctx)
summary, _ := c.LogSummary(ctx, "2026-01-01T00:00:00Z", "2026-03-31T23:59:59Z")

// Exportar como CSV
data, _ := c.ExportLogs(ctx, &ivector.ExportLogsOptions{Format: "csv", Limit: 500})
os.WriteFile("erros.csv", data, 0644)
```

---

## Executar testes

```bash
go test ./... -v
```

---

## Licença

MIT
