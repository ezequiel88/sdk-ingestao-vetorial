# ingestao-vetorial-sdk · Python

SDK Python oficial para a API do **Ingestão Vetorial** — sistema de ingestão e busca vetorial com suporte a RAG (Retrieval-Augmented Generation).

Cobre todos os recursos da API: coleções, documentos, upload, busca semântica, tags, estatísticas, progresso de ingestão e logs.

Os endpoints de lista da API retornam `items` e `meta`, mas o SDK preserva a interface anterior e devolve listas diretamente nesses métodos, desempacotando `items` automaticamente.

---

## Índice

- [Requisitos](#requisitos)
- [Instalação](#instalação)
- [Início rápido](#início-rápido)
- [Tratamento de erros](#tratamento-de-erros)
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

- Python >= 3.10
- `httpx >= 0.27.0`

---

## Instalação

```bash
# PyPI (após publicação)
pip install ingestao-vetorial-sdk

# Desenvolvimento local
pip install -e ./python
```

---

## Início rápido

```python
from ingestao_vetorial_sdk import Client

client = Client(
    base_url="http://localhost:8000",
    api_key="sua_api_key",   # enviado como X-API-Key em toda requisição
    timeout=30.0,            # opcional, padrão: 30 s
)

# Criar uma coleção
col = client.create_collection(
    name="Documentos Jurídicos",
    embedding_model="amazon.titan-embed-text-v2:0",
    dimension=1024,
)

# Fazer upload de um arquivo
resp = client.upload(
    "contrato.pdf",
    collection_id=col["id"],
    document_type="contract",
    tags=["jurídico", "2024"],
)

# Busca semântica
results = client.search(
    "cláusula de rescisão contratual",
    collection_id=col["id"],
    limit=5,
    min_score=0.75,
)
for r in results:
    print(f"[{r['score']:.2f}] {r['document_name']}: {r['content'][:120]}")
```

---

## Tratamento de erros

O SDK propaga `httpx.HTTPStatusError` para respostas 4xx/5xx:

```python
import httpx
from ingestao_vetorial_sdk import Client

client = Client("http://localhost:8000", api_key="minha-key")

try:
    doc = client.document("id-inexistente")
except httpx.HTTPStatusError as e:
    print(f"Erro {e.response.status_code}: {e.response.text}")
except httpx.RequestError as e:
    # Timeout, conexão recusada, etc.
    print(f"Erro de rede: {e}")
```

---

## Referência completa

### Coleções

#### `embedding_models() -> list[dict]`

Lista os modelos de embedding disponíveis.

```python
models = client.embedding_models()
# [{"id": "text-embedding-3-small", "provider": "openai", "dimensions": [1536], ...}]
```

#### `collections(*, skip=0, limit=100, logic="and", ...) -> list[dict]`

```python
cols = client.collections(query="jurídico", limit=10)
```

#### `create_collection(name, embedding_model, dimension, chunk_size=1400, chunk_overlap=250, *, description=None, alias=None, is_public=False, user_id=None, project_id=None) -> dict`

```python
col = client.create_collection(
    name="Base de Conhecimento",
    embedding_model="text-embedding-3-small",
    dimension=1536,
    description="Documentos internos da empresa",
)
```

#### `get_collection(collection_id) -> dict`

```python
col = client.get_collection("uuid-da-colecao")
```

#### `update_collection(collection_id, *, name=None, description=None, is_public=None) -> dict`

```python
col = client.update_collection("uuid", name="Novo Nome", is_public=True)
```

#### `delete_collection(collection_id) -> None`

```python
client.delete_collection("uuid-da-colecao")
```

#### `collection_raw(collection_id) -> dict`

Retorna informações brutas do Qdrant para a coleção.

#### `collection_documents(collection_id, *, skip=0, limit=100) -> list[dict]`

```python
docs = client.collection_documents("uuid", limit=25)
```

---

### Documentos

#### `documents(*, skip=0, limit=100, collection_id=None) -> list[dict]`

```python
docs = client.documents(collection_id="uuid", limit=50)
```

#### `document(document_id) -> dict`

Retorna detalhes completos incluindo versões e metadados.

```python
doc = client.document("uuid-do-doc")
print(doc["versions"])   # lista de versões
```

#### `document_chunks(document_id, *, version=None, q=None) -> list[dict]`

Quando `q` é informado, o filtro acontece no servidor sobre o documento inteiro. O SDK pagina internamente até reunir todos os resultados.

```python
chunks = client.document_chunks("uuid", version=1)
filtered_chunks = client.document_chunks("uuid", version=1, q="cláusula penal")
for c in chunks:
    print(c["content"][:80], "→ tokens:", c["tokens"])
```

O mesmo desempacotamento automático vale para `embedding_models()`, `collections()`, `collection_documents()`, `documents()`, `search()`, `tags()`, `search_tags()`, `recent_activity()`, `top_collections()`, `uploads_per_day()`, `vectors_per_week()` e `active_jobs()`.

#### `document_markdown(document_id, *, version=None) -> bytes`

```python
md = client.document_markdown("uuid", version=1)
with open("extraido.md", "wb") as f:
    f.write(md)
```

#### `delete_document(document_id) -> None`

#### `reprocess_document(document_id, *, source_version=None, mode="replace", extraction_tool=None) -> dict`

```python
resp = client.reprocess_document("uuid", mode="replace", extraction_tool="pypdf")
print(resp["version"])
```

#### `delete_document_version(document_id, version) -> None`

#### `set_version_active(document_id, version, *, is_active) -> dict`

```python
client.set_version_active("uuid", 2, is_active=True)
```

---

### Upload

#### `upload(file, collection_id, *, document_type="document", description="", tags=None, custom_fields=None, overwrite_existing=False, embedding_model=None, dimension=None, extraction_tool=None) -> dict`

`file` pode ser caminho (`str`) ou qualquer objeto `BinaryIO`.

```python
# Por caminho
resp = client.upload(
    "relatorio_anual.pdf",
    collection_id="uuid",
    document_type="report",
    tags=["finanças", "2024"],
    custom_fields=[{"key": "departamento", "value": "RH"}],
    overwrite_existing=True,
)
print(resp["document_id"], resp["version"])

# Por objeto de arquivo
with open("dados.csv", "rb") as fp:
    resp = client.upload(fp, collection_id="uuid", document_type="dataset")
```

**Resposta:**

```python
{
    "success": True,
    "document_id": "uuid",
    "vector_count": 0,   # 0 até a ingestão concluir (assíncrona)
    "version": 1,
    "message": None,
}
```

---

### Busca semântica

#### `search(query, collection_id=None, *, limit=10, offset=0, min_score=0.0) -> list[dict]`

```python
results = client.search(
    "procedimentos de rescisão contratual",
    collection_id="uuid",
    limit=5,
    min_score=0.75,
)
for r in results:
    print(f"[{r['score']:.3f}] {r['document_name']} — chunk {r['chunk_index']}")
    print(r["content"][:200])
```

**Campos do resultado:**

| Campo | Tipo | Descrição |
|---|---|---|
| `id` | str | ID do chunk |
| `score` | float | Similaridade cosine (0–1) |
| `content` | str | Texto do chunk |
| `document_name` | str | Nome do documento |
| `collection_id` | str | UUID da coleção |
| `collection_name` | str | Nome da coleção |
| `chunk_index` | int\|None | Posição do chunk |
| `metadata` | dict | Metadados adicionais |

---

### Tags

#### `tags(*, skip=0, limit=100) -> list[str]`

```python
all_tags = client.tags()
```

#### `search_tags(q) -> list[str]`

```python
tags = client.search_tags("fin")
```

#### `create_tag(name) -> dict`

```python
tag = client.create_tag("compliance")
print(tag["id"], tag["name"])
```

---

### Estatísticas

```python
overview = client.dashboard_overview()
stats    = client.dashboard_stats()
# {"total_collections": 4, "total_vectors": 1000, "total_size_mb": 50.5}

activity = client.recent_activity(limit=10)
top      = client.top_collections(limit=3)
uploads  = client.uploads_per_day(days=30)
vecs     = client.vectors_per_week(weeks=12)
```

---

### Progresso de ingestão

#### `active_jobs() -> list[dict]`

```python
jobs = client.active_jobs()
for j in jobs:
    print(f"{j['document_name']} — {j['status']} ({j['percent']:.0f}%)")
```

#### `job_progress(document_id, version) -> dict`

```python
import time

while True:
    p = client.job_progress("uuid", 1)
    print(p["status"], p["percent"])
    if p["status"] in ("completed", "error", "cancelled"):
        break
    time.sleep(2)
```

**Status:** `extracting` → `chunking` → `upserting` → `completed` | `error` | `cancelled`

#### `stream_progress() -> stream context manager`

Abre o endpoint SSE bruto de progresso.

```python
with client.stream_progress() as response:
    for line in response.iter_lines():
        print(line)
```

#### `cancel_ingestion(document_id, version) -> dict`

```python
result = client.cancel_ingestion("uuid", 1)
# {"ok": True}
```

---

### Logs

#### `logs(*, page=1, page_size=50, order_by="timestamp", order_dir="desc", from_ts=None, to_ts=None, nivel=None, ...) -> dict`

`from_ts` e `to_ts` aceitam `datetime` ou string ISO-8601.

```python
from datetime import datetime, timedelta, timezone

agora = datetime.now(timezone.utc)
ontem = agora - timedelta(days=1)

logs = client.logs(from_ts=ontem, to_ts=agora, nivel="ERROR", page_size=20)
for entry in logs["items"]:
    print(entry["timestamp"], entry["acao"])
```

#### `log_facets() -> dict`

```python
facets = client.log_facets()
print(facets["apps"], facets["endpoints"])
```

#### `log_summary(*, from_ts=None, to_ts=None) -> dict`

```python
summary = client.log_summary()
print(summary["total"], summary["byLevel"])
```

#### `export_logs(*, format="json", ..., limit=10000) -> bytes`

```python
data = client.export_logs(format="csv", nivel="ERROR", limit=500)
with open("erros.csv", "wb") as f:
    f.write(data)
```

#### `ingest_logs(payload, *, log_sink_token=None) -> dict`

```python
result = client.ingest_logs(
    [{"nivel": "INFO", "modulo": "sdk", "acao": "startup", "detalhes": {"app": "external"}}],
    log_sink_token="token-opcional",
)
print(result["accepted"])
```

### Uso de tokens de IA

#### `token_usage(...) -> dict`

```python
usage = client.token_usage(provider="openai", page_size=20)
print(usage["summary"]["totalTokens"])
```

---

## Executar testes

```bash
pip install -e ".[dev]"
pytest tests/ -v
```

---

## Licença

MIT
