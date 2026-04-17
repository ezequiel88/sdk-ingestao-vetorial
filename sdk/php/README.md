# ingestao-vetorial-sdk · PHP

SDK PHP oficial para a API do **Ingestão Vetorial** — sistema de ingestão e busca vetorial com suporte a RAG (Retrieval-Augmented Generation).

Requer PHP 8.1+ e Guzzle 7. Totalmente compatível com **Laravel**, **Symfony** e PHP puro.

Os endpoints paginados da API respondem com `items` e `meta`, mas o SDK continua retornando arrays de DTOs ou strings nos métodos de lista, desempacotando `items` internamente para manter compatibilidade.

---

## Índice

- [Requisitos](#requisitos)
- [Instalação](#instalação)
- [Início rápido](#início-rápido)
- [Integração com Laravel](#integração-com-laravel)
- [Tratamento de erros](#tratamento-de-erros)
- [DTOs e tipos retornados](#dtos-e-tipos-retornados)
- [Referência completa](#referência-completa)
  - [Coleções](#coleções)
  - [Documentos](#documentos)
  - [Upload](#upload)
  - [Busca semântica](#busca-semântica)
  - [Tags](#tags)
  - [Estatísticas](#estatísticas)
  - [Progresso de ingestão](#progresso-de-ingestão)
  - [Logs](#logs)
- [Executar testes](#executar-testes)

---

## Requisitos

- PHP ≥ 8.1
- `guzzlehttp/guzzle: ^7.0`

---

## Instalação

```bash
composer require ingestao-vetorial/sdk
```

---

## Início rápido

```php
<?php

use IngestaoVetorial\Client;
use IngestaoVetorial\DTO\UploadOptions;

$client = new Client(
    baseUrl: 'http://localhost:8000',
    apiKey:  'sua_api_key',   // enviado como X-API-Key em toda requisição
    timeout: 30.0,
);

// Criar uma coleção
$col = $client->createCollection([
    'name'            => 'Documentos Jurídicos',
    'embedding_model' => 'text-embedding-3-small',
    'dimension'       => 1536,
    'description'     => 'Contratos e pareceres',
]);
echo $col->id . PHP_EOL;

// Upload de arquivo
$options = new UploadOptions(
    collectionId:  $col->id,
    documentType:  'contract',
    tags:          ['jurídico', '2024'],
    overwriteExisting: true,
);
$resp = $client->upload('/caminho/para/contrato.pdf', $options);
echo $resp->document_id . PHP_EOL;

// Busca semântica
$results = $client->search(
    query:        'cláusula de rescisão contratual',
    collectionId: $col->id,
    limit:        5,
    minScore:     0.75,
);
foreach ($results as $r) {
    echo sprintf("[%.3f] %s\n", $r->score, $r->document_name);
    echo substr($r->content, 0, 200) . PHP_EOL;
}
```

---

## Integração com Laravel

Registre o cliente como singleton no `AppServiceProvider`:

```php
// app/Providers/AppServiceProvider.php
use IngestaoVetorial\Client;

public function register(): void
{
    $this->app->singleton(Client::class, fn () => new Client(
        baseUrl: config('services.ingestao.url'),
        apiKey:  config('services.ingestao.api_key'),
    ));
}
```

Configure em `config/services.php`:

```php
'ingestao' => [
    'url'     => env('INGESTAO_URL', 'http://localhost:8000'),
    'api_key' => env('INGESTAO_API_KEY'),
],
```

Use com injeção de dependência:

```php
use IngestaoVetorial\Client;

class DocumentController extends Controller
{
    public function __construct(private readonly Client $ingestao) {}

    public function search(Request $request): JsonResponse
    {
        $results = $this->ingestao->search(
            query:        $request->string('q'),
            collectionId: $request->string('collection_id'),
            limit:        10,
        );
        return response()->json($results);
    }
}
```

---

## Tratamento de erros

Todo erro 4xx/5xx lança `ApiException`:

```php
use IngestaoVetorial\Exceptions\ApiException;

try {
    $doc = $client->document('id-inexistente');
} catch (ApiException $e) {
    echo $e->statusCode();    // 404
    echo $e->responseBody();  // '{"detail":"Not found"}'
    echo $e->getMessage();    // "API error 404: ..."
}
```

---

## DTOs e tipos retornados

O SDK retorna **DTOs imutáveis** com tipagem completa via `readonly` properties. Todos os DTOs possuem um método estático `fromArray(array $data)` e são encontrados em `IngestaoVetorial\DTO\*`:

| DTO | Retornado por |
|---|---|
| `Collection` | `collections()`, `createCollection()`, `getCollection()`, `updateCollection()` |
| `Document` | `documents()`, `collectionDocuments()` |
| `DocumentDetail` | `document()`, `setVersionActive()` |
| `DocumentChunk` | `documentChunks()` |
| `SearchResult` | `search()` |
| `Tag` | `createTag()` |
| `UploadResponse` | `upload()`, `reprocessDocument()` |
| `DashboardStats` | `dashboardStats()` |
| `RecentActivity` | `recentActivity()` |
| `TopCollection` | `topCollections()` |
| `UploadsPerDay` | `uploadsPerDay()` |
| `VectorsPerWeek` | `vectorsPerWeek()` |
| `JobProgress` | `activeJobs()`, `jobProgress()` |
| `LogList` | `logs()` |
| `LogFacets` | `logFacets()` |
| `LogSummary` | `logSummary()` |
| `EmbeddingModelOption` | `embeddingModels()` |

**Enums disponíveis** em `IngestaoVetorial\Enums\*`:

```php
use IngestaoVetorial\Enums\JobStatus;
use IngestaoVetorial\Enums\LogExportFormat;
use IngestaoVetorial\Enums\ReprocessMode;

$mode   = ReprocessMode::Replace;  // 'replace'
$format = LogExportFormat::Csv;    // 'csv'
```

---

## Referência completa

### Coleções

#### `embeddingModels(): EmbeddingModelOption[]`

```php
$models = $client->embeddingModels();
foreach ($models as $m) {
    echo "$m->id ($m->provider) — dims: " . implode(', ', $m->dimensions) . PHP_EOL;
}
```

---

#### `collections(array $params = []): Collection[]`

```php
$cols = $client->collections(['query' => 'jurídico', 'limit' => 10]);
foreach ($cols as $col) {
    echo "$col->name — $col->document_count docs\n";
}
```

---

#### `createCollection(array $params): Collection`

```php
$col = $client->createCollection([
    'name'            => 'Base RAG',
    'embedding_model' => 'text-embedding-3-small',
    'dimension'       => 1536,
    'chunk_size'      => 1400,
    'chunk_overlap'   => 250,
    'is_public'       => false,
]);
```

---

#### `getCollection(string $collectionId): Collection`

```php
$col = $client->getCollection('uuid');
echo $col->embedding_model;
```

---

#### `updateCollection(string $collectionId, array $params): Collection`

```php
$col = $client->updateCollection('uuid', ['name' => 'Novo Nome', 'is_public' => true]);
```

---

#### `deleteCollection(string $collectionId): void`

```php
$client->deleteCollection('uuid');
```

---

#### `collectionDocuments(string $collectionId, int $skip = 0, int $limit = 100): Document[]`

```php
$docs = $client->collectionDocuments('uuid', limit: 25);
```

---

### Documentos

#### `documents(int $skip = 0, int $limit = 100, ?string $collectionId = null): Document[]`

```php
$docs = $client->documents(collectionId: 'uuid', limit: 20);
```

---

#### `document(string $documentId): DocumentDetail`

```php
$doc = $client->document('uuid');
echo $doc->checksum;
echo $doc->metadata->document_type;
foreach ($doc->versions as $v) {
    echo "v{$v->version} — ativo: " . ($v->is_active ? 'sim' : 'não') . PHP_EOL;
}
```

---

#### `documentChunks(string $documentId, ?int $version = null, ?string $q = null): DocumentChunk[]`

Quando `$q` é informado, o filtro é aplicado no servidor sobre o conteúdo dos chunks. O SDK pagina internamente até devolver todos os resultados.

```php
$chunks = $client->documentChunks('uuid', version: 1);
$filtered = $client->documentChunks('uuid', version: 1, q: 'cláusula penal');
foreach ($chunks as $c) {
    echo substr($c->content, 0, 80) . " — tokens: {$c->tokens}\n";
    echo "embedding dim: " . count($c->embedding) . PHP_EOL;
}
```

Esse comportamento vale também para `embeddingModels()`, `collections()`, `collectionDocuments()`, `documents()`, `search()`, `tags()`, `searchTags()`, `recentActivity()`, `topCollections()`, `uploadsPerDay()`, `vectorsPerWeek()` e `activeJobs()`.

---

#### `documentMarkdown(string $documentId, ?int $version = null): string`

Retorna bytes brutos (string PHP).

```php
$md = $client->documentMarkdown('uuid', 1);
file_put_contents('extraido.md', $md);
```

---

#### `deleteDocument(string $documentId): void`

```php
$client->deleteDocument('uuid');
```

---

#### `reprocessDocument(string $documentId, ReprocessMode $mode = ReprocessMode::Replace, ?int $sourceVersion = null, ?string $extractionTool = null): UploadResponse`

```php
use IngestaoVetorial\Enums\ReprocessMode;

$resp = $client->reprocessDocument('uuid', ReprocessMode::Replace, sourceVersion: 1);
echo "Nova versão: {$resp->version}\n";
```

---

#### `deleteDocumentVersion(string $documentId, int $version): void`

```php
$client->deleteDocumentVersion('uuid', 2);
```

---

#### `setVersionActive(string $documentId, int $version, bool $isActive): DocumentDetail`

```php
$doc = $client->setVersionActive('uuid', 2, isActive: true);
```

---

### Upload

#### `upload(string|\SplFileInfo $file, UploadOptions $options): UploadResponse`

`metadata` é um objeto `UploadOptions` tipado — o SDK serializa internamente para JSON string.

```php
use IngestaoVetorial\DTO\UploadOptions;

$options = new UploadOptions(
    collectionId:      'uuid-da-colecao',
    documentType:      'report',
    description:       'Relatório anual 2024',
    tags:              ['finanças', '2024'],
    customFields:      [['key' => 'departamento', 'value' => 'RH']],
    overwriteExisting: true,
    embeddingModel:    'text-embedding-3-small',
    dimension:         1536,
);

$resp = $client->upload('/caminho/relatorio.pdf', $options);
echo $resp->document_id . "\n";

// Também aceita SplFileInfo
$resp = $client->upload(new \SplFileInfo('/path/to/file.pdf'), $options);
```

**Campos de `UploadResponse`:**

```php
$resp->success       // bool
$resp->document_id   // string  (UUID)
$resp->vector_count  // int     (0 até ingestão concluir)
$resp->version       // int
$resp->message       // string|null
```

---

### Busca semântica

#### `search(string $query, ?string $collectionId = null, int $limit = 10, int $offset = 0, float $minScore = 0.0): SearchResult[]`

```php
$results = $client->search(
    query:        'rescisão contratual',
    collectionId: 'uuid',
    limit:        5,
    minScore:     0.75,
);

foreach ($results as $r) {
    printf("[%.3f] %s — chunk %d\n", $r->score, $r->document_name, $r->chunk_index ?? 0);
    echo substr($r->content, 0, 200) . "\n";
}
```

---

### Tags

#### `tags(int $skip = 0, int $limit = 100): string[]`

```php
$all = $client->tags();
```

#### `searchTags(string $q): string[]`

```php
$found = $client->searchTags('fin');
```

#### `createTag(string $name): Tag`

```php
$tag = $client->createTag('compliance');
echo $tag->id . ' — ' . $tag->name;
```

---

### Estatísticas

```php
$stats    = $client->dashboardStats();
// $stats->total_collections, $stats->total_vectors, $stats->total_size_mb

$activity = $client->recentActivity(limit: 10);
$top      = $client->topCollections(limit: 3);
$uploads  = $client->uploadsPerDay(days: 30);
$vecs     = $client->vectorsPerWeek(weeks: 12);
```

---

### Progresso de ingestão

#### `activeJobs(): JobProgress[]`

```php
$jobs = $client->activeJobs();
foreach ($jobs as $j) {
    printf("%s — %s (%.0f%%)\n", $j->document_name, $j->status, $j->percent);
}
```

#### `jobProgress(string $documentId, int $version): JobProgress`

```php
// Polling simples
do {
    sleep(2);
    $p = $client->jobProgress('uuid', 1);
    echo "{$p->status} {$p->percent}%\n";
} while (!in_array($p->status, ['completed', 'error', 'cancelled']));
```

**Status:** `extracting` → `chunking` → `upserting` → `completed` | `error` | `cancelled`

#### `cancelIngestion(string $documentId, int $version): array`

```php
$result = $client->cancelIngestion('uuid', 1);
// ['ok' => true]
```

---

### Logs

#### `logs(array $params = []): LogList`

`from_ts` / `to_ts` aceitam string ISO-8601 ou `\DateTimeInterface`.

```php
$page = $client->logs([
    'from_ts'   => new \DateTime('-1 day'),
    'nivel'     => 'ERROR',
    'page'      => 1,
    'page_size' => 20,
]);
echo $page->meta->total . " erros\n";
foreach ($page->items as $entry) {
    echo $entry->timestamp . ' ' . $entry->acao . PHP_EOL;
}
```

#### `logFacets(): LogFacets`

```php
$f = $client->logFacets();
print_r($f->apps);
print_r($f->endpoints);
```

#### `logSummary(\DateTimeInterface|string|null $fromTs = null, ...$toTs): LogSummary`

```php
$s = $client->logSummary(fromTs: new \DateTime('-7 days'));
echo $s->total;
print_r($s->byLevel);
```

#### `exportLogs(LogExportFormat $format = LogExportFormat::Json, int $limit = 10000, array $params = []): string`

```php
use IngestaoVetorial\Enums\LogExportFormat;

// CSV
$csv = $client->exportLogs(LogExportFormat::Csv, limit: 500, params: ['nivel' => 'ERROR']);
file_put_contents('erros.csv', $csv);

// JSON
$json = $client->exportLogs(LogExportFormat::Json);
$registros = json_decode($json, true);
```

---

## Executar testes

```bash
cd sdk/php
composer install

# Todos os testes
./vendor/bin/phpunit --testdox

# Análise estática (PHPStan nível 8)
./vendor/bin/phpstan analyse src/ --level=8
```

---

## Licença

MIT
