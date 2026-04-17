import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:http/http.dart' as http;

import 'models.dart';

JsonMap _asJsonMap(Object? value) {
  if (value is Map<String, dynamic>) {
    return value;
  }
  if (value is Map) {
    return value.map((key, dynamic mapValue) => MapEntry(key.toString(), mapValue));
  }
  return <String, dynamic>{};
}

bool _asBool(Object? value) {
  if (value is bool) {
    return value;
  }
  if (value is String) {
    return value.toLowerCase() == 'true';
  }
  return false;
}

class IngestaoVetorialClient {
  IngestaoVetorialClient({
    required String baseUrl,
    this.apiKey,
    Duration timeout = const Duration(seconds: 30),
    http.Client? httpClient,
  })  : _baseUrl = baseUrl.trim().replaceAll(RegExp(r'/+$'), ''),
        _timeout = timeout,
        _httpClient = httpClient ?? http.Client();

  final String _baseUrl;
  final String? apiKey;
  final Duration _timeout;
  final http.Client _httpClient;

  Uri _buildUri(String path, [Map<String, Object?>? queryParameters]) {
    final filtered = <String, String>{};
    if (queryParameters != null) {
      for (final entry in queryParameters.entries) {
        final value = entry.value;
        if (value != null) {
          filtered[entry.key] = value.toString();
        }
      }
    }
    return Uri.parse('$_baseUrl$path').replace(queryParameters: filtered.isEmpty ? null : filtered);
  }

  Map<String, String> _headers({bool jsonBody = false}) {
    return <String, String>{
      'Accept': 'application/json',
      if (apiKey != null && apiKey!.isNotEmpty) 'X-API-Key': apiKey!,
      if (jsonBody) 'Content-Type': 'application/json',
    };
  }

  Map<String, String> _headersWith(Map<String, String>? extraHeaders, {bool jsonBody = false}) {
    return <String, String>{..._headers(jsonBody: jsonBody), ...?extraHeaders};
  }

  Future<http.Response> _send(String method, String path,
      {Map<String, Object?>? queryParameters, Object? body, Map<String, String>? extraHeaders}) async {
    final request = http.Request(method, _buildUri(path, queryParameters));
    request.headers.addAll(_headersWith(extraHeaders, jsonBody: body != null));
    if (body != null) {
      request.body = jsonEncode(body);
    }

    final streamed = await _httpClient.send(request).timeout(_timeout);
    return http.Response.fromStream(streamed);
  }

  dynamic _decodeBody(http.Response response) {
    if (response.bodyBytes.isEmpty) {
      return null;
    }
    return jsonDecode(utf8.decode(response.bodyBytes));
  }

  Never _throwApiError(http.Response response) {
    throw ApiError(response.statusCode, utf8.decode(response.bodyBytes));
  }

  Future<dynamic> _requestJson(String method, String path,
      {Map<String, Object?>? queryParameters, Object? body, Map<String, String>? extraHeaders}) async {
    final response = await _send(method, path,
        queryParameters: queryParameters, body: body, extraHeaders: extraHeaders);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      _throwApiError(response);
    }
    return _decodeBody(response);
  }

  Future<Uint8List> _requestBytes(String path, {Map<String, Object?>? queryParameters}) async {
    final response = await _send('GET', path, queryParameters: queryParameters);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      _throwApiError(response);
    }
    return response.bodyBytes;
  }

  List<T> _extractItems<T>(dynamic payload, T Function(JsonMap json) decoder) {
    if (payload is List) {
      return payload.map((item) => decoder(_asJsonMap(item))).toList(growable: false);
    }
    final items = _asJsonMap(payload)['items'];
    if (items is List) {
      return items.map((item) => decoder(_asJsonMap(item))).toList(growable: false);
    }
    return const [];
  }

  Future<List<T>> _getItems<T>(String path, T Function(JsonMap json) decoder,
      {Map<String, Object?>? queryParameters}) async {
    final payload = await _requestJson('GET', path, queryParameters: queryParameters);
    return _extractItems(payload, decoder);
  }

  Future<List<T>> _getAllItems<T>(String path, T Function(JsonMap json) decoder,
      {Map<String, Object?>? queryParameters}) async {
    final items = <T>[];
    var skip = (queryParameters?['skip'] as int?) ?? 0;
    final limit = (queryParameters?['limit'] as int?) ?? 100;

    while (true) {
      final payload = await _requestJson('GET', path, queryParameters: <String, Object?>{
        ...?queryParameters,
        'skip': skip,
        'limit': limit,
      });
      final pageItems = _extractItems(payload, decoder);
      items.addAll(pageItems);

      if (payload is List) {
        if (pageItems.length < limit) {
          break;
        }
      } else {
        final meta = _asJsonMap(payload)['meta'];
        final hasMore = _asBool(_asJsonMap(meta)['has_more']);
        if (!hasMore) {
          break;
        }
      }

      if (pageItems.isEmpty) {
        break;
      }
      skip += pageItems.length;
    }

    return items;
  }

  Future<T> _getObject<T>(String path, T Function(JsonMap json) decoder,
      {Map<String, Object?>? queryParameters}) async {
    final payload = await _requestJson('GET', path, queryParameters: queryParameters);
    return decoder(_asJsonMap(payload));
  }

  Future<T> _postObject<T>(String path, T Function(JsonMap json) decoder,
      {Map<String, Object?>? queryParameters, Object? body, Map<String, String>? extraHeaders}) async {
    final payload = await _requestJson('POST', path,
        queryParameters: queryParameters, body: body, extraHeaders: extraHeaders);
    return decoder(_asJsonMap(payload));
  }

  Future<T> _patchObject<T>(String path, T Function(JsonMap json) decoder, Object body) async {
    final payload = await _requestJson('PATCH', path, body: body);
    return decoder(_asJsonMap(payload));
  }

  Future<void> _delete(String path) async {
    final response = await _send('DELETE', path);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      _throwApiError(response);
    }
  }

  Future<List<EmbeddingModelOption>> embeddingModels() {
    return _getItems('/api/v1/collections/embedding-models', EmbeddingModelOption.fromJson);
  }

  Future<List<Collection>> collections([CollectionListParams params = const CollectionListParams()]) {
    return _getItems('/api/v1/collections', Collection.fromJson,
        queryParameters: params.toQueryParameters());
  }

  Future<Collection> createCollection(CreateCollectionParams params) {
    return _postObject('/api/v1/collections', Collection.fromJson, body: params.toJson());
  }

  Future<Collection> getCollection(String collectionId) {
    return _getObject('/api/v1/collections/$collectionId', Collection.fromJson);
  }

  Future<Collection> updateCollection(String collectionId, UpdateCollectionParams params) {
    return _patchObject('/api/v1/collections/$collectionId', Collection.fromJson, params.toJson());
  }

  Future<void> deleteCollection(String collectionId) => _delete('/api/v1/collections/$collectionId');

  Future<JsonMap> collectionRaw(String collectionId) async {
    final payload = await _requestJson('GET', '/api/v1/collections/$collectionId/raw');
    return _asJsonMap(payload);
  }

  Future<List<Document>> collectionDocuments(String collectionId,
      {int skip = 0, int limit = 100}) {
    return _getItems('/api/v1/collections/$collectionId/documents', Document.fromJson,
        queryParameters: <String, Object?>{'skip': skip, 'limit': limit});
  }

  Future<List<Document>> documents([DocumentListParams params = const DocumentListParams()]) {
    return _getItems('/api/v1/documents', Document.fromJson,
        queryParameters: params.toQueryParameters());
  }

  Future<DocumentDetail> document(String documentId) {
    return _getObject('/api/v1/documents/$documentId', DocumentDetail.fromJson);
  }

  Future<List<DocumentChunk>> documentChunks(String documentId,
      {int? version, String? query}) {
    return _getAllItems('/api/v1/documents/$documentId/chunks', DocumentChunk.fromJson,
        queryParameters: <String, Object?>{'version': version, 'q': query});
  }

  Future<Uint8List> documentMarkdown(String documentId, {int? version}) {
    return _requestBytes('/api/v1/documents/$documentId/markdown',
        queryParameters: <String, Object?>{'version': version});
  }

  Future<void> deleteDocument(String documentId) => _delete('/api/v1/documents/$documentId');

  Future<UploadResponse> reprocessDocument(String documentId,
      {ReprocessMode mode = ReprocessMode.replace, int? sourceVersion, String? extractionTool}) {
    return _postObject('/api/v1/documents/$documentId/reprocess', UploadResponse.fromJson,
        queryParameters: <String, Object?>{
          'mode': mode.value,
          'source_version': sourceVersion,
          'extraction_tool': extractionTool,
        });
  }

  Future<void> deleteDocumentVersion(String documentId, int version) =>
      _delete('/api/v1/documents/$documentId/versions/$version');

  Future<DocumentDetail> setVersionActive(String documentId, int version, bool isActive) {
    return _patchObject(
      '/api/v1/documents/$documentId/versions/$version',
      DocumentDetail.fromJson,
      <String, Object?>{'is_active': isActive},
    );
  }

  Future<UploadResponse> upload(UploadFile file, UploadOptions options) async {
    final request = http.MultipartRequest('POST', _buildUri('/api/v1/upload'));
    request.headers.addAll(_headers());
    request.fields['collection_id'] = options.collectionId;
    request.fields['metadata'] = jsonEncode((options.metadata ?? const UploadMetadata()).toJson());
    request.fields['overwrite_existing'] = (options.overwriteExisting ?? false).toString();
    if (options.embeddingModel != null) {
      request.fields['embedding_model'] = options.embeddingModel!;
    }
    if (options.dimension != null) {
      request.fields['dimension'] = options.dimension.toString();
    }
    if (options.extractionTool != null) {
      request.fields['extraction_tool'] = options.extractionTool!;
    }
    request.files.add(
      http.MultipartFile.fromBytes('file', file.bytes, filename: file.filename),
    );

    final streamed = await _httpClient.send(request).timeout(_timeout);
    final response = await http.Response.fromStream(streamed);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      _throwApiError(response);
    }
    return UploadResponse.fromJson(_asJsonMap(_decodeBody(response)));
  }

  Future<List<SearchResult>> search(String query, [SearchParams params = const SearchParams()]) {
    return _getItems('/api/v1/search', SearchResult.fromJson,
        queryParameters: <String, Object?>{'query': query, ...params.toQueryParameters()});
  }

  Future<List<String>> tags([TagListParams params = const TagListParams()]) async {
    final payload = await _requestJson('GET', '/api/v1/tags', queryParameters: params.toQueryParameters());
    if (payload is List) {
      return payload.map((item) => item.toString()).toList(growable: false);
    }
    final items = _asJsonMap(payload)['items'];
    if (items is List) {
      return items.map((item) => item.toString()).toList(growable: false);
    }
    return const [];
  }

  Future<List<String>> searchTags(String query) async {
    final payload = await _requestJson('GET', '/api/v1/tags/search', queryParameters: <String, Object?>{'q': query});
    if (payload is List) {
      return payload.map((item) => item.toString()).toList(growable: false);
    }
    final items = _asJsonMap(payload)['items'];
    if (items is List) {
      return items.map((item) => item.toString()).toList(growable: false);
    }
    return const [];
  }

  Future<Tag> createTag(String name) {
    return _postObject('/api/v1/tags', Tag.fromJson, body: <String, Object?>{'name': name});
  }

  Future<DashboardStats> dashboardStats() {
    return _getObject('/api/v1/stats/dashboard', DashboardStats.fromJson);
  }

  Future<DashboardOverview> dashboardOverview() {
    return _getObject('/api/v1/stats/overview', DashboardOverview.fromJson);
  }

  Future<List<RecentActivity>> recentActivity([int limit = 5]) {
    return _getItems('/api/v1/stats/activity', RecentActivity.fromJson,
        queryParameters: <String, Object?>{'limit': limit});
  }

  Future<List<TopCollection>> topCollections([int limit = 5]) {
    return _getItems('/api/v1/stats/top-collections', TopCollection.fromJson,
        queryParameters: <String, Object?>{'limit': limit});
  }

  Future<List<UploadsPerDay>> uploadsPerDay([int days = 7]) {
    return _getItems('/api/v1/stats/uploads-per-day', UploadsPerDay.fromJson,
        queryParameters: <String, Object?>{'days': days});
  }

  Future<List<VectorsPerWeek>> vectorsPerWeek([int weeks = 6]) {
    return _getItems('/api/v1/stats/vectors-per-week', VectorsPerWeek.fromJson,
        queryParameters: <String, Object?>{'weeks': weeks});
  }

  Future<List<JobProgress>> activeJobs() {
    return _getAllItems('/api/v1/progress/active', JobProgress.fromJson);
  }

  Future<JobProgress> jobProgress(String documentId, int version) {
    return _getObject('/api/v1/progress/$documentId/versions/$version', JobProgress.fromJson);
  }

  Future<CancelIngestionResponse> cancelIngestion(String documentId, int version) {
    return _postObject(
      '/api/v1/progress/$documentId/versions/$version/cancel',
      CancelIngestionResponse.fromJson,
    );
  }

  Future<Stream<String>> streamProgress() async {
    final request = http.Request('GET', _buildUri('/api/v1/progress/stream'));
    request.headers.addAll(_headers());
    final streamed = await _httpClient.send(request).timeout(_timeout);
    if (streamed.statusCode < 200 || streamed.statusCode >= 300) {
      final response = await http.Response.fromStream(streamed);
      _throwApiError(response);
    }
    return utf8.decoder.bind(streamed.stream).transform(const LineSplitter());
  }

  Future<LogList> logs([LogListParams params = const LogListParams()]) {
    return _getObject('/api/v1/logs', LogList.fromJson,
        queryParameters: params.toQueryParameters());
  }

  Future<LogFacets> logFacets() {
    return _getObject('/api/v1/logs/facets', LogFacets.fromJson);
  }

  Future<LogSummary> logSummary({DateTime? fromTs, DateTime? toTs}) {
    return _getObject('/api/v1/logs/summary', LogSummary.fromJson,
        queryParameters: <String, Object?>{'from_ts': fromTs?.toIso8601String(), 'to_ts': toTs?.toIso8601String()});
  }

  Future<Uint8List> exportLogs([LogExportParams params = const LogExportParams()]) {
    return _requestBytes('/api/v1/logs/export', queryParameters: params.toQueryParameters());
  }

  Future<LogIngestResponse> ingestLogs(List<LogIngestItem> payload, {String? logSinkToken}) {
    return _postObject('/api/v1/logs/ingest', LogIngestResponse.fromJson,
        body: payload.map((item) => item.toJson()).toList(growable: false),
        extraHeaders: logSinkToken == null ? null : <String, String>{'X-Log-Sink-Token': logSinkToken});
  }

  Future<TokenUsageList> tokenUsage([TokenUsageParams params = const TokenUsageParams()]) {
    return _getObject('/api/v1/token-usage', TokenUsageList.fromJson,
        queryParameters: params.toQueryParameters());
  }

  void close() {
    _httpClient.close();
  }
}