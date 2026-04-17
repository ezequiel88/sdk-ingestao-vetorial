from __future__ import annotations

import json
import os
from datetime import datetime
from typing import IO, Any, BinaryIO

import httpx


class Client:
    """
    Synchronous HTTP client for the Ingestão Vetorial API.

    Parameters
    ----------
    base_url:
        Base URL of the API server, e.g. ``http://localhost:8000``.
    api_key:
        API key sent as ``X-API-Key`` header on every request.
    timeout:
        Default request timeout in seconds (default: 30).

    Usage
    -----
    >>> client = Client("http://localhost:8000", api_key="my-key")
    >>> cols = client.collections()
    """

    def __init__(
        self,
        base_url: str,
        api_key: str | None = None,
        timeout: float = 30.0,
    ) -> None:
        self.base_url = base_url.rstrip("/")
        self.api_key = api_key
        self.timeout = timeout
        self._client = httpx.Client(base_url=self.base_url, timeout=self.timeout)

    # ------------------------------------------------------------------
    # Internal helpers
    # ------------------------------------------------------------------

    def _headers(self) -> dict[str, str]:
        headers: dict[str, str] = {"Accept": "application/json"}
        if self.api_key:
            headers["X-API-Key"] = str(self.api_key)
        return headers

    def _get(self, path: str, params: dict[str, Any] | None = None) -> Any:
        r = self._client.get(path, params=params, headers=self._headers())
        r.raise_for_status()
        return r.json()

    @staticmethod
    def _unwrap_items(payload: Any) -> list[Any]:
        if isinstance(payload, dict) and isinstance(payload.get("items"), list):
            return payload["items"]
        if isinstance(payload, list):
            return payload
        raise TypeError("Expected a list response or a paginated payload with items")

    def _get_items(self, path: str, params: dict[str, Any] | None = None) -> list[Any]:
        return self._unwrap_items(self._get(path, params))

    def _get_all_items(
        self,
        path: str,
        params: dict[str, Any] | None = None,
        *,
        limit: int = 100,
    ) -> list[Any]:
        items: list[Any] = []
        query = dict(params or {})
        skip = int(query.get("skip", 0) or 0)
        query["limit"] = int(query.get("limit", limit) or limit)

        while True:
            query["skip"] = skip
            payload = self._get(path, query)
            page_items = self._unwrap_items(payload)
            items.extend(page_items)

            if isinstance(payload, dict):
                meta = payload.get("meta") or {}
                if not meta.get("has_more"):
                    break
            elif len(page_items) < query["limit"]:
                break

            if not page_items:
                break
            skip += len(page_items)

        return items

    def _post(self, path: str, payload: dict[str, Any] | None = None) -> Any:
        r = self._client.post(path, json=payload, headers=self._headers())
        r.raise_for_status()
        return r.json()

    def _patch(self, path: str, payload: dict[str, Any]) -> Any:
        r = self._client.patch(path, json=payload, headers=self._headers())
        r.raise_for_status()
        return r.json()

    def _delete(self, path: str) -> None:
        r = self._client.delete(path, headers=self._headers())
        r.raise_for_status()

    def _headers_with(self, extra_headers: dict[str, str] | None = None) -> dict[str, str]:
        headers = self._headers()
        if extra_headers:
            headers.update(extra_headers)
        return headers

    # ------------------------------------------------------------------
    # Collections
    # ------------------------------------------------------------------

    def embedding_models(self) -> list[dict[str, Any]]:
        """Return the list of available embedding models."""
        return self._get_items("/api/v1/collections/embedding-models")

    def collections(
        self,
        *,
        skip: int = 0,
        limit: int = 100,
        logic: str = "and",
        user_id: str | None = None,
        project_id: str | None = None,
        alias: str | None = None,
        query: str | None = None,
    ) -> list[dict[str, Any]]:
        """List collections with optional filters."""
        params: dict[str, Any] = {"skip": skip, "limit": limit, "logic": logic}
        if user_id is not None:
            params["user_id"] = user_id
        if project_id is not None:
            params["project_id"] = project_id
        if alias is not None:
            params["alias"] = alias
        if query is not None:
            params["query"] = query
        return self._get_items("/api/v1/collections", params)

    def create_collection(
        self,
        name: str,
        embedding_model: str,
        dimension: int,
        chunk_size: int = 1400,
        chunk_overlap: int = 250,
        *,
        description: str | None = None,
        alias: str | None = None,
        is_public: bool = False,
        user_id: str | None = None,
        project_id: str | None = None,
    ) -> dict[str, Any]:
        """Create a new collection."""
        payload: dict[str, Any] = {
            "name": name,
            "embedding_model": embedding_model,
            "dimension": dimension,
            "chunk_size": chunk_size,
            "chunk_overlap": chunk_overlap,
            "is_public": is_public,
        }
        if description is not None:
            payload["description"] = description
        if alias is not None:
            payload["alias"] = alias
        if user_id is not None:
            payload["user_id"] = user_id
        if project_id is not None:
            payload["project_id"] = project_id
        return self._post("/api/v1/collections", payload)

    def get_collection(self, collection_id: str) -> dict[str, Any]:
        """Fetch a single collection by ID."""
        return self._get(f"/api/v1/collections/{collection_id}")

    def update_collection(
        self,
        collection_id: str,
        *,
        name: str | None = None,
        description: str | None = None,
        is_public: bool | None = None,
    ) -> dict[str, Any]:
        """Update a collection's mutable fields."""
        payload: dict[str, Any] = {}
        if name is not None:
            payload["name"] = name
        if description is not None:
            payload["description"] = description
        if is_public is not None:
            payload["is_public"] = is_public
        return self._patch(f"/api/v1/collections/{collection_id}", payload)

    def delete_collection(self, collection_id: str) -> None:
        """Permanently delete a collection and all its documents."""
        self._delete(f"/api/v1/collections/{collection_id}")

    def collection_raw(self, collection_id: str) -> Any:
        """Return the raw Qdrant collection info for a collection."""
        return self._get(f"/api/v1/collections/{collection_id}/raw")

    def collection_documents(
        self,
        collection_id: str,
        *,
        skip: int = 0,
        limit: int = 100,
    ) -> list[dict[str, Any]]:
        """List documents belonging to a specific collection."""
        return self._get_items(
            f"/api/v1/collections/{collection_id}/documents",
            {"skip": skip, "limit": limit},
        )

    # ------------------------------------------------------------------
    # Documents
    # ------------------------------------------------------------------

    def documents(
        self,
        *,
        skip: int = 0,
        limit: int = 100,
        collection_id: str | None = None,
    ) -> list[dict[str, Any]]:
        """List all documents, optionally filtered by collection."""
        params: dict[str, Any] = {"skip": skip, "limit": limit}
        if collection_id is not None:
            params["collection_id"] = collection_id
        return self._get_items("/api/v1/documents", params)

    def document(self, document_id: str) -> dict[str, Any]:
        """Fetch full document details including versions and metadata."""
        return self._get(f"/api/v1/documents/{document_id}")

    def document_chunks(
        self,
        document_id: str,
        *,
        version: int | None = None,
        q: str | None = None,
    ) -> list[dict[str, Any]]:
        """Return all chunks (with embeddings) for a document version."""
        params: dict[str, Any] = {}
        if version is not None:
            params["version"] = version
        if q is not None:
            params["q"] = q
        return self._get_all_items(f"/api/v1/documents/{document_id}/chunks", params)

    def document_markdown(
        self,
        document_id: str,
        *,
        version: int | None = None,
    ) -> bytes:
        """Download the extracted markdown for a document version as bytes."""
        params: dict[str, Any] = {}
        if version is not None:
            params["version"] = version
        r = self._client.get(
            f"/api/v1/documents/{document_id}/markdown",
            params=params,
            headers=self._headers(),
        )
        r.raise_for_status()
        return r.content

    def delete_document(self, document_id: str) -> None:
        """Delete a document and all its versions."""
        self._delete(f"/api/v1/documents/{document_id}")

    def reprocess_document(
        self,
        document_id: str,
        *,
        source_version: int | None = None,
        mode: str = "replace",
        extraction_tool: str | None = None,
    ) -> dict[str, Any]:
        """Re-run the ingestion pipeline for an existing document."""
        params: dict[str, Any] = {"mode": mode}
        if source_version is not None:
            params["source_version"] = source_version
        if extraction_tool is not None:
            params["extraction_tool"] = extraction_tool
        r = self._client.post(
            f"/api/v1/documents/{document_id}/reprocess",
            params=params,
            headers=self._headers(),
        )
        r.raise_for_status()
        return r.json()

    def delete_document_version(self, document_id: str, version: int) -> None:
        """Delete a specific version of a document."""
        self._delete(f"/api/v1/documents/{document_id}/versions/{version}")

    def set_version_active(
        self,
        document_id: str,
        version: int,
        *,
        is_active: bool,
    ) -> dict[str, Any]:
        """Activate or deactivate a document version."""
        return self._patch(
            f"/api/v1/documents/{document_id}/versions/{version}",
            {"is_active": is_active},
        )

    # ------------------------------------------------------------------
    # Upload
    # ------------------------------------------------------------------

    def upload(
        self,
        file: str | BinaryIO,
        collection_id: str,
        *,
        document_type: str = "document",
        description: str = "",
        tags: list[str] | None = None,
        custom_fields: list[dict[str, Any]] | None = None,
        overwrite_existing: bool = False,
        embedding_model: str | None = None,
        dimension: int | None = None,
        extraction_tool: str | None = None,
    ) -> dict[str, Any]:
        """
        Upload a file and start the ingestion pipeline.

        Parameters
        ----------
        file:
            Local file path (str) or any binary file-like object.
        collection_id:
            Target collection UUID.
        document_type:
            Arbitrary type label for the document (e.g. ``"pdf"``).
        description:
            Optional description stored with the document metadata.
        tags:
            List of tag strings.
        custom_fields:
            List of ``{"key": ..., "value": ...}`` dicts for extra metadata.
        overwrite_existing:
            If True, replace an existing document with the same checksum.
        embedding_model:
            Override the collection's default embedding model.
        dimension:
            Override the collection's default vector dimension.
        extraction_tool:
            Force a specific extraction backend (e.g. ``"pypdf"``).
        """
        should_close = False
        if isinstance(file, str):
            fp: BinaryIO = open(file, "rb")  # noqa: WPS515
            filename = os.path.basename(file)
            should_close = True
        else:
            fp = file
            filename = getattr(fp, "name", "upload.bin")
            filename = os.path.basename(filename)

        metadata = {
            "document_type": document_type,
            "description": description,
            "tags": tags or [],
            "custom_fields": custom_fields or [],
        }

        data: dict[str, Any] = {
            "collection_id": collection_id,
            "metadata": json.dumps(metadata),
            "overwrite_existing": str(overwrite_existing).lower(),
        }
        if embedding_model is not None:
            data["embedding_model"] = embedding_model
        if dimension is not None:
            data["dimension"] = str(dimension)
        if extraction_tool is not None:
            data["extraction_tool"] = extraction_tool

        try:
            r = self._client.post(
                "/api/v1/upload",
                data=data,
                files={"file": (filename, fp, "application/octet-stream")},
                headers=self._headers(),
            )
            r.raise_for_status()
            return r.json()
        finally:
            if should_close:
                fp.close()

    # ------------------------------------------------------------------
    # Search
    # ------------------------------------------------------------------

    def search(
        self,
        query: str,
        collection_id: str | None = None,
        *,
        limit: int = 10,
        offset: int = 0,
        min_score: float = 0.0,
    ) -> list[dict[str, Any]]:
        """
        Run a semantic search query.

        Parameters
        ----------
        query:
            Natural-language search text.
        collection_id:
            Restrict results to a specific collection.
        limit:
            Maximum number of results (default: 10).
        offset:
            Pagination offset (default: 0).
        min_score:
            Minimum cosine similarity threshold 0–1 (default: 0.0).
        """
        params: dict[str, Any] = {
            "query": query,
            "limit": limit,
            "offset": offset,
            "min_score": min_score,
        }
        if collection_id:
            params["collection_id"] = collection_id
        return self._get_items("/api/v1/search", params)

    # ------------------------------------------------------------------
    # Tags
    # ------------------------------------------------------------------

    def tags(self, *, skip: int = 0, limit: int = 100) -> list[str]:
        """List all tags."""
        return self._get_items("/api/v1/tags", {"skip": skip, "limit": limit})

    def search_tags(self, q: str) -> list[str]:
        """Search tags by partial name."""
        return self._get_items("/api/v1/tags/search", {"q": q})

    def create_tag(self, name: str) -> dict[str, Any]:
        """Create a new tag."""
        return self._post("/api/v1/tags", {"name": name})

    # ------------------------------------------------------------------
    # Stats
    # ------------------------------------------------------------------

    def dashboard_stats(self) -> dict[str, Any]:
        """Return aggregate counts: collections, documents, vectors, size."""
        return self._get("/api/v1/stats/dashboard")

    def dashboard_overview(self) -> dict[str, Any]:
        """Return the full dashboard payload in a single request."""
        return self._get("/api/v1/stats/overview")

    def recent_activity(self, limit: int = 5) -> list[dict[str, Any]]:
        """Return the most recent ingestion/upload activity entries."""
        return self._get_items("/api/v1/stats/activity", {"limit": limit})

    def top_collections(self, limit: int = 5) -> list[dict[str, Any]]:
        """Return the collections with the highest document/vector counts."""
        return self._get_items("/api/v1/stats/top-collections", {"limit": limit})

    def uploads_per_day(self, days: int = 7) -> list[dict[str, Any]]:
        """Return upload counts grouped by day for the last ``days`` days."""
        return self._get_items("/api/v1/stats/uploads-per-day", {"days": days})

    def vectors_per_week(self, weeks: int = 6) -> list[dict[str, Any]]:
        """Return vector counts grouped by week for the last ``weeks`` weeks."""
        return self._get_items("/api/v1/stats/vectors-per-week", {"weeks": weeks})

    # ------------------------------------------------------------------
    # Ingestion progress
    # ------------------------------------------------------------------

    def active_jobs(self) -> list[dict[str, Any]]:
        """Return all currently active ingestion jobs."""
        return self._get_all_items("/api/v1/progress/active")

    def job_progress(self, document_id: str, version: int) -> dict[str, Any]:
        """Return the ingestion progress for a specific document version."""
        return self._get(f"/api/v1/progress/{document_id}/versions/{version}")

    def cancel_ingestion(self, document_id: str, version: int) -> dict[str, Any]:
        """Request cancellation of an in-progress ingestion job."""
        r = self._client.post(
            f"/api/v1/progress/{document_id}/versions/{version}/cancel",
            headers=self._headers(),
        )
        r.raise_for_status()
        return r.json()

    def stream_progress(self) -> httpx._client._StreamContextManager:  # type: ignore[attr-defined]
        """
        Open the server-sent events stream for ingestion progress.

        Usage:
        >>> with client.stream_progress() as response:
        ...     for line in response.iter_lines():
        ...         print(line)
        """
        return self._client.stream("GET", "/api/v1/progress/stream", headers=self._headers())

    # ------------------------------------------------------------------
    # Logs
    # ------------------------------------------------------------------

    def logs(
        self,
        *,
        page: int = 1,
        page_size: int = 50,
        order_by: str = "timestamp",
        order_dir: str = "desc",
        from_ts: datetime | str | None = None,
        to_ts: datetime | str | None = None,
        nivel: str | None = None,
        app: str | None = None,
        endpoint: str | None = None,
        status_code: int | None = None,
        q: str | None = None,
        user_id: str | None = None,
        session_id: str | None = None,
        project_ids: str | None = None,
    ) -> dict[str, Any]:
        """
        Query the application log store with filters and pagination.

        Returns a paginated ``LogListOut`` dict.
        Timestamps can be ISO-8601 strings or ``datetime`` objects.
        """
        params: dict[str, Any] = {
            "page": page,
            "page_size": page_size,
            "order_by": order_by,
            "order_dir": order_dir,
        }
        if from_ts is not None:
            params["from_ts"] = (
                from_ts.isoformat() if isinstance(from_ts, datetime) else from_ts
            )
        if to_ts is not None:
            params["to_ts"] = (
                to_ts.isoformat() if isinstance(to_ts, datetime) else to_ts
            )
        for key, val in [
            ("nivel", nivel),
            ("app", app),
            ("endpoint", endpoint),
            ("status_code", status_code),
            ("q", q),
            ("user_id", user_id),
            ("session_id", session_id),
            ("project_ids", project_ids),
        ]:
            if val is not None:
                params[key] = val
        return self._get("/api/v1/logs", params)

    def log_facets(self) -> dict[str, Any]:
        """Return distinct values for log filter fields (nivel, app, endpoint…)."""
        return self._get("/api/v1/logs/facets")

    def log_summary(
        self,
        *,
        from_ts: datetime | str | None = None,
        to_ts: datetime | str | None = None,
    ) -> dict[str, Any]:
        """Return aggregated log statistics for a time window."""
        params: dict[str, Any] = {}
        if from_ts is not None:
            params["from_ts"] = (
                from_ts.isoformat() if isinstance(from_ts, datetime) else from_ts
            )
        if to_ts is not None:
            params["to_ts"] = (
                to_ts.isoformat() if isinstance(to_ts, datetime) else to_ts
            )
        return self._get("/api/v1/logs/summary", params or None)

    def export_logs(
        self,
        *,
        format: str = "json",
        from_ts: datetime | str | None = None,
        to_ts: datetime | str | None = None,
        nivel: str | None = None,
        app: str | None = None,
        endpoint: str | None = None,
        status_code: int | None = None,
        q: str | None = None,
        user_id: str | None = None,
        session_id: str | None = None,
        project_ids: str | None = None,
        limit: int = 10000,
    ) -> bytes:
        """
        Export logs as raw bytes.

        Parameters
        ----------
        format:
            ``"json"`` (default) or ``"csv"``.
        limit:
            Maximum number of rows (1–50 000, default: 10 000).

        Returns the response body as bytes — write to a file or decode directly.
        """
        params: dict[str, Any] = {"format": format, "limit": limit}
        if from_ts is not None:
            params["from_ts"] = (
                from_ts.isoformat() if isinstance(from_ts, datetime) else from_ts
            )
        if to_ts is not None:
            params["to_ts"] = (
                to_ts.isoformat() if isinstance(to_ts, datetime) else to_ts
            )
        for key, val in [
            ("nivel", nivel),
            ("app", app),
            ("endpoint", endpoint),
            ("status_code", status_code),
            ("q", q),
            ("user_id", user_id),
            ("session_id", session_id),
            ("project_ids", project_ids),
        ]:
            if val is not None:
                params[key] = val
        r = self._client.get("/api/v1/logs/export", params=params, headers=self._headers())
        r.raise_for_status()
        return r.content

    def ingest_logs(
        self,
        payload: list[dict[str, Any]],
        *,
        log_sink_token: str | None = None,
    ) -> dict[str, Any]:
        """Ingest external application logs into the backend log store."""
        r = self._client.post(
            "/api/v1/logs/ingest",
            json=payload,
            headers=self._headers_with(
                {"X-Log-Sink-Token": log_sink_token} if log_sink_token is not None else None,
            ),
        )
        r.raise_for_status()
        return r.json()

    def token_usage(
        self,
        *,
        page: int = 1,
        page_size: int = 50,
        order_by: str = "timestamp",
        order_dir: str = "desc",
        provider: str | None = None,
        call_type: str | None = None,
        operation: str | None = None,
        user_id: str | None = None,
        collection_id: str | None = None,
        status: str | None = None,
        from_ts: datetime | str | None = None,
        to_ts: datetime | str | None = None,
    ) -> dict[str, Any]:
        """List AI token usage records and summary metrics."""
        params: dict[str, Any] = {
            "page": page,
            "page_size": page_size,
            "order_by": order_by,
            "order_dir": order_dir,
        }
        if from_ts is not None:
            params["from_ts"] = from_ts.isoformat() if isinstance(from_ts, datetime) else from_ts
        if to_ts is not None:
            params["to_ts"] = to_ts.isoformat() if isinstance(to_ts, datetime) else to_ts
        for key, val in [
            ("provider", provider),
            ("call_type", call_type),
            ("operation", operation),
            ("user_id", user_id),
            ("collection_id", collection_id),
            ("status", status),
        ]:
            if val is not None:
                params[key] = val
        return self._get("/api/v1/token-usage", params)
