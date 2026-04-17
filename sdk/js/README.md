# ingestao-vetorial-sdk · JavaScript / TypeScript

SDK TypeScript oficial para a API do **Ingestão Vetorial** — zero dependências de runtime, compatível com **Node.js 18+**, **React Native** e **browsers modernos**.

Cobre todos os recursos da API: coleções, documentos, upload, busca semântica, tags, estatísticas, progresso de ingestão e logs.

Os endpoints de lista da API respondem com `items` e `meta`. O SDK mantém a interface pública anterior e retorna listas simples nesses métodos, desempacotando `items` internamente.

---

## Índice

- [Requisitos](#requisitos)
- [Instalação](#instalação)
- [Início rápido](#início-rápido)
- [Uso com React Native](#uso-com-react-native)
- [Tratamento de erros](#tratamento-de-erros)
- [Referência de tipos](#referência-de-tipos)
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

- **Node.js** ≥ 18 (para `fetch` e `FormData` nativos)
- **React Native** ≥ 0.71 (Hermes com `fetch` nativo)
- **Browsers**: Chrome 95+, Firefox 93+, Safari 15.4+

Nenhuma dependência de runtime — usa apenas `fetch` e `FormData` globais.

---

## Instalação

```bash
# npm
npm install ingestao-vetorial-sdk

# yarn
yarn add ingestao-vetorial-sdk

# pnpm
pnpm add ingestao-vetorial-sdk

# bun
bun add ingestao-vetorial-sdk
```

---

## Início rápido

```typescript
import { IngestaoVetorialClient } from 'ingestao-vetorial-sdk';

const client = new IngestaoVetorialClient(
  'http://localhost:8000',
  'sua_api_key',   // enviado como X-API-Key em toda requisição
  30_000,          // timeout em ms, opcional (padrão: 30 000)
);

// Criar uma coleção
const col = await client.createCollection({
  name: 'Documentos Jurídicos',
  embedding_model: 'text-embedding-3-small',
  dimension: 1536,
  description: 'Contratos e pareceres',
});

// Fazer upload de um arquivo (Node.js)
import { readFileSync } from 'fs';
const file = new File([readFileSync('contrato.pdf')], 'contrato.pdf');
const resp = await client.upload(file, {
  collection_id: col.id,
  metadata: { document_type: 'contract', tags: ['jurídico', '2024'] },
});
console.log(resp.document_id);

// Busca semântica
const results = await client.search('cláusula de rescisão', {
  collection_id: col.id,
  limit: 5,
  min_score: 0.75,
});
results.forEach(r => console.log(`[${r.score.toFixed(3)}] ${r.document_name}`));
```

---

## Uso com React Native

O SDK usa apenas `fetch` e `FormData` globais — sem configuração extra no React Native.

```typescript
import { IngestaoVetorialClient } from 'ingestao-vetorial-sdk';

const client = new IngestaoVetorialClient(
  'https://api.meuservidor.com',
  'minha-api-key',
);

// Upload a partir do seletor de arquivos (Expo DocumentPicker)
import * as DocumentPicker from 'expo-document-picker';

const picked = await DocumentPicker.getDocumentAsync({ type: 'application/pdf' });
if (picked.assets?.[0]) {
  const asset = picked.assets[0];
  const resp = await client.upload(
    { uri: asset.uri, name: asset.name, type: asset.mimeType ?? 'application/octet-stream' },
    { collection_id: 'uuid-da-colecao' },
  );
  console.log('Documento enviado:', resp.document_id);
}
```

---

## Tratamento de erros

Qualquer resposta não-2xx lança `ApiError`:

```typescript
import { IngestaoVetorialClient, ApiError } from 'ingestao-vetorial-sdk';

const client = new IngestaoVetorialClient('http://localhost:8000', 'key');

try {
  const doc = await client.document('id-inexistente');
} catch (err) {
  if (err instanceof ApiError) {
    console.error(`HTTP ${err.statusCode}:`, err.body);
    // err.message também contém "API error 404: ..."
  } else {
    // Timeout (AbortError), rede offline, etc.
    throw err;
  }
}
```

---

## Referência de tipos

Todos os tipos são exportados e podem ser usados diretamente:

```typescript
import type {
  Collection,
  Document,
  DocumentDetail,
  DocumentChunk,
  SearchResult,
  SearchParams,
  UploadOptions,
  UploadFile,
  UploadResponse,
  JobProgress,
  JobStatus,
  DashboardStats,
  LogList,
  LogListParams,
  LogExportFormat,
  Tag,
  EmbeddingModelOption,
} from 'ingestao-vetorial-sdk';
```

---

## Referência completa

### Coleções

#### `embeddingModels(): Promise<EmbeddingModelOption[]>`

```typescript
const models = await client.embeddingModels();
// [{ id: 'text-embedding-3-small', provider: 'openai', dimensions: [1536], defaultDimension: 1536 }]
```

---

#### `collections(params?: CollectionListParams): Promise<Collection[]>`

```typescript
const cols = await client.collections({ query: 'jurídico', limit: 10 });
```

---

#### `createCollection(params: CreateCollectionParams): Promise<Collection>`

```typescript
const col = await client.createCollection({
  name: 'Base RAG',
  embedding_model: 'text-embedding-3-small',
  dimension: 1536,
  chunk_size: 1400,
  chunk_overlap: 250,
  is_public: false,
});
```

---

#### `getCollection(collectionId: string): Promise<Collection>`

```typescript
const col = await client.getCollection('uuid');
```

---

#### `updateCollection(collectionId: string, params: UpdateCollectionParams): Promise<Collection>`

```typescript
const col = await client.updateCollection('uuid', { name: 'Novo Nome', is_public: true });
```

---

#### `deleteCollection(collectionId: string): Promise<void>`

```typescript
await client.deleteCollection('uuid');
```

---

#### `collectionDocuments(collectionId: string, params?): Promise<Document[]>`

```typescript
const docs = await client.collectionDocuments('uuid', { skip: 0, limit: 25 });
```

---

### Documentos

#### `documents(params?: DocumentListParams): Promise<Document[]>`

```typescript
const docs = await client.documents({ collection_id: 'uuid', limit: 20 });
```

---

#### `document(documentId: string): Promise<DocumentDetail>`

Retorna detalhes completos incluindo versões e metadados estruturados.

```typescript
const doc = await client.document('uuid');
console.log(doc.versions);   // DocumentVersion[]
console.log(doc.metadata);   // DocumentMetadata
```

---

#### `documentChunks(documentId: string, version?: number, q?: string): Promise<DocumentChunk[]>`

Quando `q` é informado, o filtro é aplicado no servidor sobre o conteúdo dos chunks. O SDK pagina internamente até devolver todos os resultados.

```typescript
const chunks = await client.documentChunks('uuid', 1);
const filteredChunks = await client.documentChunks('uuid', 1, 'cláusula penal');
chunks.forEach(c => console.log(c.content.slice(0, 80), '— tokens:', c.tokens));
```

Esse desempacotamento automático também vale para `embeddingModels()`, `collections()`, `collectionDocuments()`, `documents()`, `search()`, `tags()`, `searchTags()`, `recentActivity()`, `topCollections()`, `uploadsPerDay()`, `vectorsPerWeek()` e `activeJobs()`.

---

#### `documentMarkdown(documentId: string, version?: number): Promise<ArrayBuffer>`

```typescript
const buffer = await client.documentMarkdown('uuid', 1);
// Node.js: salvar em arquivo
import { writeFileSync } from 'fs';
writeFileSync('extraido.md', Buffer.from(buffer));
// Browser: criar blob para download
const url = URL.createObjectURL(new Blob([buffer], { type: 'text/markdown' }));
```

---

#### `deleteDocument(documentId: string): Promise<void>`

```typescript
await client.deleteDocument('uuid');
```

---

#### `reprocessDocument(documentId: string, params?): Promise<UploadResponse>`

Params são enviados na query string (não no body).

```typescript
const resp = await client.reprocessDocument('uuid', {
  mode: 'replace',
  source_version: 1,
  extraction_tool: 'pypdf',
});
console.log('Nova versão:', resp.version);
```

---

#### `deleteDocumentVersion(documentId: string, version: number): Promise<void>`

```typescript
await client.deleteDocumentVersion('uuid', 2);
```

---

#### `setVersionActive(documentId: string, version: number, isActive: boolean): Promise<DocumentDetail>`

```typescript
await client.setVersionActive('uuid', 2, true);
```

---

### Upload

#### `upload(file: UploadFile, options: UploadOptions): Promise<UploadResponse>`

`metadata` é um objeto tipado — o SDK serializa internamente para JSON string.

**Tipos aceitos para `file`:**

| Tipo | Ambiente |
|---|---|
| `File` | Browser, Node 18+ |
| `Blob` | Browser, Node 18+ |
| `{ blob: Blob; name: string }` | Universal |
| `{ uri: string; name: string; type?: string }` | React Native |

```typescript
// Browser — input type="file"
const [file] = (event.target as HTMLInputElement).files!;
const resp = await client.upload(file, {
  collection_id: 'uuid',
  metadata: {
    document_type: 'report',
    tags: ['rh', '2024'],
    custom_fields: [{ key: 'departamento', value: 'RH' }],
  },
  overwrite_existing: true,
  embedding_model: 'text-embedding-3-small',
  dimension: 1536,
});

// Node.js — path
import { readFileSync } from 'fs';
await client.upload(
  new File([readFileSync('doc.pdf')], 'doc.pdf'),
  { collection_id: 'uuid' },
);
```

**Resposta `UploadResponse`:**

```typescript
{
  success: true,
  document_id: 'uuid',
  vector_count: 0,   // 0 enquanto ingestão é assíncrona
  version: 1,
  message: null,
}
```

---

### Busca semântica

#### `search(query: string, params?: SearchParams): Promise<SearchResult[]>`

```typescript
const results = await client.search('rescisão contratual', {
  collection_id: 'uuid',
  limit: 5,
  offset: 0,
  min_score: 0.75,
});

results.forEach(r => {
  console.log(`[${r.score.toFixed(3)}] ${r.document_name} — chunk ${r.chunk_index}`);
  console.log(r.content.slice(0, 200));
});
```

**Campos de `SearchResult`:**

| Campo | Tipo | Descrição |
|---|---|---|
| `id` | `string` | ID do chunk |
| `score` | `number` | Similaridade cosine (0–1) |
| `content` | `string` | Texto do chunk |
| `document_name` | `string` | Nome do documento |
| `collection_id` | `string` | UUID da coleção |
| `collection_name` | `string` | Nome da coleção |
| `chunk_index` | `number \| null` | Posição do chunk |
| `metadata` | `Record<string, unknown>` | Metadados adicionais |

---

### Tags

#### `tags(params?: TagListParams): Promise<string[]>`

```typescript
const all = await client.tags();
```

#### `searchTags(q: string): Promise<string[]>`

```typescript
const found = await client.searchTags('fin');
```

#### `createTag(name: string): Promise<Tag>`

```typescript
const tag = await client.createTag('compliance');
console.log(tag.id, tag.name);
```

---

### Estatísticas

```typescript
const overview = await client.dashboardOverview();

const stats    = await client.dashboardStats();
// { total_collections: 4, total_documents: 20, total_vectors: 1000, total_size_mb: 50.5 }

const activity = await client.recentActivity(10);
const top      = await client.topCollections(3);
const uploads  = await client.uploadsPerDay(30);
const vecs     = await client.vectorsPerWeek(12);
```

---

### Progresso de ingestão

#### `activeJobs(): Promise<JobProgress[]>`

```typescript
const jobs = await client.activeJobs();
jobs.forEach(j => console.log(`${j.document_name}: ${j.status} (${j.percent.toFixed(0)}%)`));
```

#### `jobProgress(documentId: string, version: number): Promise<JobProgress>`

```typescript
// Polling simples
const poll = async (docId: string, ver: number) => {
  while (true) {
    const p = await client.jobProgress(docId, ver);
    console.log(p.status, p.percent);
    if (['completed', 'error', 'cancelled'].includes(p.status)) break;
    await new Promise(r => setTimeout(r, 2000));
  }
};
```

**Status:** `extracting` → `chunking` → `upserting` → `completed` | `error` | `cancelled`

#### `streamProgress(): Promise<ReadableStream<Uint8Array> | null>`

Retorna o stream SSE bruto para consumo manual.

```typescript
const body = await client.streamProgress();
const reader = body?.getReader();
```

#### `cancelIngestion(documentId: string, version: number): Promise<CancelIngestionResponse>`

```typescript
const { ok } = await client.cancelIngestion('uuid', 1);
```

---

### Logs

#### `logs(params?: LogListParams): Promise<LogList>`

`from_ts` / `to_ts` aceitam `string` ISO-8601 ou `Date`.

```typescript
const page = await client.logs({
  from_ts: new Date(Date.now() - 86_400_000),
  nivel: 'ERROR',
  page: 1,
  page_size: 20,
});
console.log(page.meta.total, 'erros');
page.items.forEach(e => console.log(e.timestamp, e.acao));
```

#### `logFacets(): Promise<LogFacets>`

```typescript
const f = await client.logFacets();
console.log(f.apps, f.endpoints);
```

#### `logSummary(params?): Promise<LogSummary>`

```typescript
const s = await client.logSummary({ from_ts: '2024-01-01T00:00:00Z' });
console.log(s.total, s.byLevel);
```

#### `exportLogs(params?: LogExportParams): Promise<ArrayBuffer>`

```typescript
// CSV para download no browser
const buffer = await client.exportLogs({ format: 'csv', nivel: 'ERROR' });
const a = document.createElement('a');
a.href = URL.createObjectURL(new Blob([buffer], { type: 'text/csv' }));
a.download = 'logs.csv';
a.click();

// JSON no Node.js
import { writeFileSync } from 'fs';
const buf = await client.exportLogs({ format: 'json', limit: 500 });
writeFileSync('logs.json', Buffer.from(buf));
```

#### `ingestLogs(payload: LogIngestItem[], logSinkToken?: string): Promise<LogIngestResponse>`

```typescript
const result = await client.ingestLogs(
  [{ nivel: 'INFO', modulo: 'sdk', acao: 'startup', detalhes: { app: 'external' } }],
  'token-opcional',
);
console.log(result.accepted);
```

### Uso de tokens

#### `tokenUsage(params?: TokenUsageParams): Promise<TokenUsageList>`

```typescript
const usage = await client.tokenUsage({ provider: 'openai', page_size: 20 });
console.log(usage.summary.totalTokens);
```

---

## Executar testes

```bash
cd sdk/js
npm install
npm run typecheck   # tsc --noEmit
npm test            # vitest run
npm run build       # tsup → dist/
```

---

## Licença

MIT
