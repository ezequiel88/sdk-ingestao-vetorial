"""
Tests for the ingestao_vetorial_sdk Client.

Uses pytest + unittest.mock to stub httpx responses — no real server required.
"""

from __future__ import annotations

import io
import json
from typing import Any
from unittest.mock import MagicMock, patch

import pytest

from ingestao_vetorial_sdk import Client


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------


def _make_response(
    data: Any = None,
    *,
    status_code: int = 200,
    content: bytes | None = None,
) -> MagicMock:
    """Build a mock httpx.Response."""
    resp = MagicMock()
    resp.status_code = status_code
    if content is not None:
        resp.content = content
    else:
        resp.content = json.dumps(data).encode()
    resp.json.return_value = data
    resp.raise_for_status = MagicMock()
    return resp


def _mock_client_method(
    client: Client,
    method: str,
    return_value: Any = None,
    *,
    status_code: int = 200,
    content: bytes | None = None,
) -> MagicMock:
    """Patch client._client.<method> and return the mock."""
    mock_resp = _make_response(return_value, status_code=status_code, content=content)
    mock_fn = MagicMock(return_value=mock_resp)
    setattr(client._client, method, mock_fn)
    return mock_fn


# ---------------------------------------------------------------------------
# Collections
# ---------------------------------------------------------------------------


COLLECTION = {
    "id": "col-1",
    "name": "My Collection",
    "alias": "my-col",
    "description": None,
    "is_public": False,
    "embedding_model": "text-embedding-3-small",
    "dimension": 1536,
    "chunk_size": 1400,
    "chunk_overlap": 250,
    "created_at": "2024-01-01T00:00:00Z",
    "document_count": 3,
    "user_id": None,
    "project_id": None,
}


def _paginated(items: list[Any], *, skip: int = 0, limit: int | None = None, total: int | None = None, has_more: bool = False) -> dict[str, Any]:
    return {
        "items": items,
        "meta": {
            "skip": skip,
            "limit": len(items) if limit is None else limit,
            "total": len(items) if total is None else total,
            "has_more": has_more,
        },
    }


class TestEmbeddingModels:
    def test_returns_list(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        models = [{"id": "text-embedding-3-small", "name": "Small", "provider": "openai", "dimensions": [1536], "defaultDimension": 1536}]
        _mock_client_method(client, "get", _paginated(models))
        result = client.embedding_models()
        assert result == models

    def test_calls_correct_path(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        mock_get = _mock_client_method(client, "get", [])
        client.embedding_models()
        mock_get.assert_called_once()
        call_path = mock_get.call_args[0][0]
        assert call_path == "/api/v1/collections/embedding-models"


class TestCollections:
    def test_list_collections_defaults(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        mock_get = _mock_client_method(client, "get", _paginated([COLLECTION]))
        result = client.collections()
        assert isinstance(result, list)
        assert result[0]["id"] == "col-1"
        # Default params forwarded
        params = mock_get.call_args[1]["params"]
        assert params["skip"] == 0
        assert params["limit"] == 100

    def test_list_collections_custom_params(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        mock_get = _mock_client_method(client, "get", _paginated([]))
        client.collections(skip=10, limit=5, query="test", project_id="proj-1")
        params = mock_get.call_args[1]["params"]
        assert params["skip"] == 10
        assert params["limit"] == 5
        assert params["query"] == "test"
        assert params["project_id"] == "proj-1"

    def test_create_collection(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        mock_post = _mock_client_method(client, "post", COLLECTION)
        result = client.create_collection(
            name="My Collection",
            embedding_model="text-embedding-3-small",
            dimension=1536,
        )
        assert result["name"] == "My Collection"
        body = mock_post.call_args[1]["json"]
        assert body["embedding_model"] == "text-embedding-3-small"
        assert body["dimension"] == 1536

    def test_get_collection(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        mock_get = _mock_client_method(client, "get", COLLECTION)
        result = client.get_collection("col-1")
        assert result["id"] == "col-1"
        assert "/col-1" in mock_get.call_args[0][0]

    def test_update_collection(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        updated = {**COLLECTION, "name": "Renamed"}
        mock_patch = _mock_client_method(client, "patch", updated)
        result = client.update_collection("col-1", name="Renamed")
        assert result["name"] == "Renamed"
        body = mock_patch.call_args[1]["json"]
        assert body["name"] == "Renamed"

    def test_delete_collection(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        mock_delete = _mock_client_method(client, "delete", None)
        client.delete_collection("col-1")
        assert mock_delete.called
        assert "/col-1" in mock_delete.call_args[0][0]

    def test_collection_raw(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        raw = {"qdrant_info": "some raw data"}
        _mock_client_method(client, "get", raw)
        result = client.collection_raw("col-1")
        assert result == raw

    def test_collection_documents(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        docs = [{"id": "doc-1", "name": "file.pdf"}]
        mock_get = _mock_client_method(client, "get", _paginated(docs))
        result = client.collection_documents("col-1", skip=0, limit=10)
        assert result[0]["id"] == "doc-1"
        assert "/col-1/documents" in mock_get.call_args[0][0]

    def test_api_key_sent_in_headers(self) -> None:
        client = Client("http://localhost:8000", api_key="super-secret")
        mock_get = _mock_client_method(client, "get", [])
        client.collections()
        headers = mock_get.call_args[1]["headers"]
        assert headers["X-API-Key"] == "super-secret"


# ---------------------------------------------------------------------------
# Documents
# ---------------------------------------------------------------------------


DOCUMENT = {
    "id": "doc-1",
    "name": "file.pdf",
    "size": "1.2 MB",
    "uploaded_at": "2024-01-01T00:00:00Z",
    "vector_count": 100,
    "chunk_count": 10,
    "version": 1,
    "collection_id": "col-1",
    "tags": ["pdf"],
    "version_count": 1,
}


class TestDocuments:
    def test_list_documents(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        mock_get = _mock_client_method(client, "get", _paginated([DOCUMENT]))
        result = client.documents(collection_id="col-1")
        assert result[0]["id"] == "doc-1"
        params = mock_get.call_args[1]["params"]
        assert params["collection_id"] == "col-1"

    def test_get_document(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        detail = {**DOCUMENT, "checksum": "abc123", "metadata": {}, "versions": []}
        mock_get = _mock_client_method(client, "get", detail)
        result = client.document("doc-1")
        assert result["checksum"] == "abc123"
        assert "/documents/doc-1" in mock_get.call_args[0][0]

    def test_document_chunks(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        chunks = [{"index": 0, "content": "text", "tokens": 50, "embedding": [0.1, 0.2]}]
        mock_get = _mock_client_method(client, "get", _paginated(chunks))
        result = client.document_chunks("doc-1", version=1)
        assert isinstance(result, list)
        params = mock_get.call_args[1]["params"]
        assert params["version"] == 1

    def test_document_chunks_supports_search_query(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        mock_get = _mock_client_method(client, "get", _paginated([], has_more=False))
        client.document_chunks("doc-1", q="clausula")
        params = mock_get.call_args[1]["params"]
        assert params["q"] == "clausula"

    def test_document_chunks_no_version(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        _mock_client_method(client, "get", [])
        client.document_chunks("doc-1")
        # When version is None, should not include "version" key in params
        # The client does: params = {} if version is None else {"version": version}
        # Access via internal _client.get call
        mock_get = client._client.get
        params = mock_get.call_args[1]["params"]
        assert "version" not in params

    def test_document_markdown_returns_bytes(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        binary = b"%PDF-1.4 binary content"
        mock_resp = MagicMock()
        mock_resp.content = binary
        mock_resp.raise_for_status = MagicMock()
        client._client.get = MagicMock(return_value=mock_resp)
        result = client.document_markdown("doc-1", version=1)
        assert result == binary

    def test_delete_document(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        mock_delete = _mock_client_method(client, "delete", None)
        client.delete_document("doc-1")
        assert mock_delete.called
        assert "/documents/doc-1" in mock_delete.call_args[0][0]

    def test_reprocess_uses_query_string(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        resp_data = {"success": True, "document_id": "doc-1", "vector_count": 0, "version": 2}
        mock_post = _mock_client_method(client, "post", resp_data)
        client.reprocess_document("doc-1", mode="replace", source_version=1)
        # Params must be on query string (not json body)
        call_kwargs = mock_post.call_args[1]
        assert "params" in call_kwargs
        assert call_kwargs["params"]["mode"] == "replace"
        assert call_kwargs["params"]["source_version"] == 1
        assert "json" not in call_kwargs

    def test_delete_document_version(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        mock_delete = _mock_client_method(client, "delete", None)
        client.delete_document_version("doc-1", 2)
        path = mock_delete.call_args[0][0]
        assert "/doc-1/versions/2" in path

    def test_set_version_active(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        mock_patch = _mock_client_method(client, "patch", DOCUMENT)
        client.set_version_active("doc-1", 2, is_active=True)
        body = mock_patch.call_args[1]["json"]
        assert body["is_active"] is True


# ---------------------------------------------------------------------------
# Upload
# ---------------------------------------------------------------------------


UPLOAD_RESPONSE = {
    "success": True,
    "document_id": "doc-new",
    "vector_count": 0,
    "version": 1,
    "message": None,
}


class TestUpload:
    def test_upload_with_file_path(self, tmp_path: Any) -> None:
        tmp_file = tmp_path / "test.txt"
        tmp_file.write_bytes(b"hello world")

        client = Client("http://localhost:8000", api_key="test-key")
        mock_resp = _make_response(UPLOAD_RESPONSE)
        client._client.post = MagicMock(return_value=mock_resp)

        result = client.upload(str(tmp_file), collection_id="col-1")
        assert result["success"] is True
        assert result["document_id"] == "doc-new"

    def test_upload_with_file_object(self) -> None:
        fp = io.BytesIO(b"hello")
        fp.name = "test.pdf"

        client = Client("http://localhost:8000", api_key="test-key")
        mock_resp = _make_response(UPLOAD_RESPONSE)
        client._client.post = MagicMock(return_value=mock_resp)

        result = client.upload(fp, collection_id="col-1")
        assert result["success"] is True

    def test_upload_metadata_is_json_string(self, tmp_path: Any) -> None:
        """metadata field must be serialised to a JSON string, not a dict."""
        tmp_file = tmp_path / "doc.pdf"
        tmp_file.write_bytes(b"dummy")

        client = Client("http://localhost:8000", api_key="test-key")
        mock_resp = _make_response(UPLOAD_RESPONSE)
        client._client.post = MagicMock(return_value=mock_resp)

        client.upload(
            str(tmp_file),
            collection_id="col-1",
            document_type="report",
            tags=["finance", "q4"],
            overwrite_existing=True,
        )

        call_kwargs = client._client.post.call_args[1]
        form_data = call_kwargs["data"]
        assert isinstance(form_data["metadata"], str), "metadata must be a JSON string"
        parsed = json.loads(form_data["metadata"])
        assert parsed["document_type"] == "report"
        assert "finance" in parsed["tags"]

    def test_overwrite_existing_sent_as_lowercase_string(self, tmp_path: Any) -> None:
        tmp_file = tmp_path / "doc.pdf"
        tmp_file.write_bytes(b"dummy")

        client = Client("http://localhost:8000", api_key="test-key")
        mock_resp = _make_response(UPLOAD_RESPONSE)
        client._client.post = MagicMock(return_value=mock_resp)

        client.upload(str(tmp_file), collection_id="col-1", overwrite_existing=True)
        form_data = client._client.post.call_args[1]["data"]
        assert form_data["overwrite_existing"] == "true"

    def test_optional_upload_fields_omitted_when_none(self, tmp_path: Any) -> None:
        tmp_file = tmp_path / "doc.pdf"
        tmp_file.write_bytes(b"dummy")

        client = Client("http://localhost:8000", api_key="test-key")
        mock_resp = _make_response(UPLOAD_RESPONSE)
        client._client.post = MagicMock(return_value=mock_resp)

        client.upload(str(tmp_file), collection_id="col-1")
        form_data = client._client.post.call_args[1]["data"]
        assert "embedding_model" not in form_data
        assert "dimension" not in form_data
        assert "extraction_tool" not in form_data

    def test_dimension_sent_as_string(self, tmp_path: Any) -> None:
        tmp_file = tmp_path / "doc.pdf"
        tmp_file.write_bytes(b"dummy")

        client = Client("http://localhost:8000", api_key="test-key")
        mock_resp = _make_response(UPLOAD_RESPONSE)
        client._client.post = MagicMock(return_value=mock_resp)

        client.upload(str(tmp_file), collection_id="col-1", dimension=1536)
        form_data = client._client.post.call_args[1]["data"]
        assert form_data["dimension"] == "1536"


# ---------------------------------------------------------------------------
# Search
# ---------------------------------------------------------------------------


SEARCH_RESULT = {
    "id": "r-1",
    "type": "chunk",
    "document_name": "doc.pdf",
    "collection_id": "col-1",
    "collection_name": "My Col",
    "chunk_index": 0,
    "content": "relevant text",
    "score": 0.92,
    "metadata": {},
}


class TestSearch:
    def test_search_returns_list(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        mock_get = _mock_client_method(client, "get", _paginated([SEARCH_RESULT]))
        result = client.search("machine learning", collection_id="col-1", limit=5, min_score=0.8)
        assert result[0]["score"] == 0.92
        params = mock_get.call_args[1]["params"]
        assert params["query"] == "machine learning"
        assert params["collection_id"] == "col-1"
        assert params["min_score"] == 0.8
        assert params["limit"] == 5

    def test_search_default_params(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        mock_get = _mock_client_method(client, "get", [])
        client.search("hello")
        params = mock_get.call_args[1]["params"]
        assert params["limit"] == 10
        assert params["offset"] == 0
        assert params["min_score"] == 0.0

    def test_search_without_collection_id(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        mock_get = _mock_client_method(client, "get", [])
        client.search("test query")
        params = mock_get.call_args[1]["params"]
        assert "collection_id" not in params


# ---------------------------------------------------------------------------
# Tags
# ---------------------------------------------------------------------------


class TestTags:
    def test_list_tags(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        _mock_client_method(client, "get", _paginated(["tag1", "tag2"]))
        result = client.tags()
        assert result == ["tag1", "tag2"]

    def test_search_tags(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        mock_get = _mock_client_method(client, "get", _paginated(["finance"]))
        result = client.search_tags("fin")
        assert "finance" in result
        params = mock_get.call_args[1]["params"]
        assert params["q"] == "fin"

    def test_create_tag(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        mock_post = _mock_client_method(client, "post", {"id": "tag-uuid", "name": "new-tag"})
        result = client.create_tag("new-tag")
        assert result["name"] == "new-tag"
        body = mock_post.call_args[1]["json"]
        assert body["name"] == "new-tag"


# ---------------------------------------------------------------------------
# Stats
# ---------------------------------------------------------------------------


class TestStats:
    def test_dashboard_overview(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        overview = {
            "summary": {"total_collections": 4, "total_documents": 20, "total_vectors": 1000, "total_size_mb": 50.5},
            "recent_activity": [],
            "top_collections": [],
            "uploads_per_day": [],
            "vectors_per_week": [],
            "logs_overview": {"total": 10, "by_level": {"INFO": 8}, "by_app": {"api": 10}, "top_endpoints": []},
        }
        _mock_client_method(client, "get", overview)
        result = client.dashboard_overview()
        assert result["summary"]["total_vectors"] == 1000

    def test_dashboard_stats(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        stats = {"total_collections": 4, "total_documents": 20, "total_vectors": 1000, "total_size_mb": 50.5}
        _mock_client_method(client, "get", stats)
        result = client.dashboard_stats()
        assert result["total_vectors"] == 1000

    def test_recent_activity_default_limit(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        mock_get = _mock_client_method(client, "get", _paginated([]))
        client.recent_activity()
        params = mock_get.call_args[1]["params"]
        assert params["limit"] == 5

    def test_top_collections(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        top = [{"id": "col-1", "name": "Best", "document_count": 10, "vector_count": 500}]
        _mock_client_method(client, "get", _paginated(top))
        result = client.top_collections(limit=3)
        assert result[0]["name"] == "Best"

    def test_uploads_per_day(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        mock_get = _mock_client_method(client, "get", _paginated([]))
        client.uploads_per_day(days=14)
        params = mock_get.call_args[1]["params"]
        assert params["days"] == 14

    def test_vectors_per_week(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        mock_get = _mock_client_method(client, "get", _paginated([]))
        client.vectors_per_week(weeks=4)
        params = mock_get.call_args[1]["params"]
        assert params["weeks"] == 4


# ---------------------------------------------------------------------------
# Progress
# ---------------------------------------------------------------------------


JOB_PROGRESS = {
    "job_id": "job-1",
    "document_id": "doc-1",
    "version": 1,
    "status": "chunking",
    "percent": 45.0,
    "processed_chunks": 9,
    "total_chunks": 20,
    "started_at": 1700000000.0,
    "updated_at": 1700000010.0,
    "eta_seconds": 11.0,
    "message": "Processing…",
    "document_name": "doc.pdf",
    "collection_id": "col-1",
    "error": "",
}


class TestProgress:
    def test_active_jobs(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        _mock_client_method(client, "get", _paginated([JOB_PROGRESS]))
        result = client.active_jobs()
        assert result[0]["status"] == "chunking"

    def test_job_progress(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        mock_get = _mock_client_method(client, "get", JOB_PROGRESS)
        result = client.job_progress("doc-1", 1)
        assert result["percent"] == 45.0
        assert "/doc-1/versions/1" in mock_get.call_args[0][0]

    def test_cancel_ingestion_is_post_with_no_body(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        mock_post = _mock_client_method(client, "post", {"ok": True})
        result = client.cancel_ingestion("doc-1", 1)
        assert result["ok"] is True
        path = mock_post.call_args[0][0]
        assert "/doc-1/versions/1/cancel" in path

    def test_stream_progress_uses_sse_endpoint(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        client._client.stream = MagicMock(return_value="stream")
        result = client.stream_progress()
        assert result == "stream"
        client._client.stream.assert_called_once()
        assert client._client.stream.call_args[0][1] == "/api/v1/progress/stream"


# ---------------------------------------------------------------------------
# Logs
# ---------------------------------------------------------------------------


LOG_LIST_RESPONSE = {
    "items": [
        {
            "id": "log-1",
            "timestamp": "2024-01-01T00:00:00Z",
            "requestId": None,
            "nivel": "INFO",
            "modulo": "api",
            "acao": "search",
            "detalhes": {},
            "request": None,
            "response": None,
            "usuarioId": None,
            "projetoId": None,
            "tempoExecucao": 42,
        }
    ],
    "meta": {"page": 1, "pageSize": 50, "total": 1},
}


class TestLogs:
    def test_logs_default_params(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        mock_get = _mock_client_method(client, "get", LOG_LIST_RESPONSE)
        result = client.logs()
        assert result["meta"]["total"] == 1
        params = mock_get.call_args[1]["params"]
        assert params["page"] == 1
        assert params["order_dir"] == "desc"

    def test_logs_datetime_converted_to_iso(self) -> None:
        from datetime import datetime, timezone

        client = Client("http://localhost:8000", api_key="test-key")
        mock_get = _mock_client_method(client, "get", LOG_LIST_RESPONSE)
        dt = datetime(2024, 1, 15, 10, 0, 0, tzinfo=timezone.utc)
        client.logs(from_ts=dt)
        params = mock_get.call_args[1]["params"]
        assert params["from_ts"] == dt.isoformat()

    def test_logs_string_timestamp_passed_as_is(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        mock_get = _mock_client_method(client, "get", LOG_LIST_RESPONSE)
        client.logs(from_ts="2024-01-01T00:00:00Z")
        params = mock_get.call_args[1]["params"]
        assert params["from_ts"] == "2024-01-01T00:00:00Z"

    def test_log_facets(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        facets = {"apps": ["api", "worker"], "endpoints": ["/search"], "projects": [], "users": []}
        mock_get = _mock_client_method(client, "get", facets)
        result = client.log_facets()
        assert "api" in result["apps"]
        assert "/api/v1/logs/facets" in mock_get.call_args[0][0]

    def test_log_summary(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        summary = {"total": 100, "byLevel": {"INFO": 80, "ERROR": 20}, "byApp": {}, "topEndpoints": []}
        _mock_client_method(client, "get", summary)
        result = client.log_summary()
        assert result["total"] == 100

    def test_export_logs_returns_bytes(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        binary = b'[{"id":"log-1"}]'
        mock_resp = MagicMock()
        mock_resp.content = binary
        mock_resp.raise_for_status = MagicMock()
        client._client.get = MagicMock(return_value=mock_resp)
        result = client.export_logs(format="json")
        assert result == binary

    def test_export_logs_format_in_params(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        mock_resp = MagicMock()
        mock_resp.content = b""
        mock_resp.raise_for_status = MagicMock()
        client._client.get = MagicMock(return_value=mock_resp)
        client.export_logs(format="csv", limit=500)
        params = client._client.get.call_args[1]["params"]
        assert params["format"] == "csv"
        assert params["limit"] == 500

    def test_ingest_logs_sends_optional_sink_token(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        mock_post = _mock_client_method(client, "post", {"accepted": 1})
        result = client.ingest_logs([
            {"nivel": "INFO", "modulo": "sdk", "acao": "ping", "detalhes": {"app": "external"}},
        ], log_sink_token="sink-token")
        assert result["accepted"] == 1
        headers = mock_post.call_args[1]["headers"]
        assert headers["X-Log-Sink-Token"] == "sink-token"


class TestTokenUsage:
    def test_token_usage_returns_summary_payload(self) -> None:
        client = Client("http://localhost:8000", api_key="test-key")
        payload = {
            "items": [],
            "meta": {"page": 1, "pageSize": 50, "total": 0},
            "summary": {"totalRecords": 0, "totalInputTokens": 0, "totalOutputTokens": 0, "totalTokens": 0, "providers": []},
        }
        mock_get = _mock_client_method(client, "get", payload)
        result = client.token_usage(provider="openai", status="success")
        assert result["summary"]["totalTokens"] == 0
        params = mock_get.call_args[1]["params"]
        assert params["provider"] == "openai"
        assert params["status"] == "success"
