import 'dart:typed_data';

typedef JsonMap = Map<String, dynamic>;

String _stringValue(Object? value, [String fallback = '']) => value?.toString() ?? fallback;

int _intValue(Object? value, [int fallback = 0]) {
  if (value is int) {
    return value;
  }
  if (value is num) {
    return value.toInt();
  }
  return int.tryParse(value?.toString() ?? '') ?? fallback;
}

double _doubleValue(Object? value, [double fallback = 0]) {
  if (value is double) {
    return value;
  }
  if (value is num) {
    return value.toDouble();
  }
  return double.tryParse(value?.toString() ?? '') ?? fallback;
}

bool _boolValue(Object? value, [bool fallback = false]) {
  if (value is bool) {
    return value;
  }
  if (value is String) {
    return value.toLowerCase() == 'true';
  }
  return fallback;
}

List<String> _stringList(Object? value) {
  if (value is List) {
    return value.map((item) => item.toString()).toList(growable: false);
  }
  return const [];
}

List<double> _doubleList(Object? value) {
  if (value is List) {
    return value.map((item) => _doubleValue(item)).toList(growable: false);
  }
  return const [];
}

JsonMap _jsonMap(Object? value) {
  if (value is Map<String, dynamic>) {
    return value;
  }
  if (value is Map) {
    return value.map((key, dynamic mapValue) => MapEntry(key.toString(), mapValue));
  }
  return <String, dynamic>{};
}

List<JsonMap> _jsonMapList(Object? value) {
  if (value is List) {
    return value.map((item) => _jsonMap(item)).toList(growable: false);
  }
  return const [];
}

enum ReprocessMode {
  replace('replace'),
  append('append');

  const ReprocessMode(this.value);
  final String value;
}

enum LogExportFormat {
  json('json'),
  csv('csv');

  const LogExportFormat(this.value);
  final String value;
}

class ApiError implements Exception {
  const ApiError(this.statusCode, this.body);

  final int statusCode;
  final String body;

  @override
  String toString() => 'ApiError($statusCode): $body';
}

class EmbeddingModelOption {
  const EmbeddingModelOption({
    required this.id,
    required this.name,
    required this.provider,
    required this.dimensions,
    required this.defaultDimension,
  });

  factory EmbeddingModelOption.fromJson(JsonMap json) => EmbeddingModelOption(
        id: _stringValue(json['id']),
        name: _stringValue(json['name']),
        provider: _stringValue(json['provider']),
        dimensions: (json['dimensions'] is List ? json['dimensions'] as List : const <dynamic>[])
            .map((item) => _intValue(item))
            .toList(growable: false),
        defaultDimension: _intValue(
          json['defaultDimension'],
          (json['dimensions'] is List && (json['dimensions'] as List).isNotEmpty)
              ? _intValue((json['dimensions'] as List).first)
              : 0,
        ),
      );

  final String id;
  final String name;
  final String provider;
  final List<int> dimensions;
  final int defaultDimension;
}

class Collection {
  const Collection({
    required this.id,
    required this.name,
    required this.alias,
    required this.description,
    required this.isPublic,
    required this.embeddingModel,
    required this.dimension,
    required this.chunkSize,
    required this.chunkOverlap,
    required this.createdAt,
    required this.documentCount,
    required this.userId,
    required this.projectId,
  });

  factory Collection.fromJson(JsonMap json) => Collection(
        id: _stringValue(json['id']),
        name: _stringValue(json['name']),
        alias: _stringValue(json['alias']),
        description: json['description']?.toString(),
        isPublic: _boolValue(json['is_public']),
        embeddingModel: _stringValue(json['embedding_model']),
        dimension: _intValue(json['dimension']),
        chunkSize: _intValue(json['chunk_size']),
        chunkOverlap: _intValue(json['chunk_overlap']),
        createdAt: _stringValue(json['created_at']),
        documentCount: _intValue(json['document_count']),
        userId: json['user_id']?.toString(),
        projectId: json['project_id']?.toString(),
      );

  final String id;
  final String name;
  final String alias;
  final String? description;
  final bool isPublic;
  final String embeddingModel;
  final int dimension;
  final int chunkSize;
  final int chunkOverlap;
  final String createdAt;
  final int documentCount;
  final String? userId;
  final String? projectId;
}

class DocumentMetadata {
  const DocumentMetadata({
    this.documentType = 'document',
    this.description,
    this.tags = const [],
    this.customFields = const [],
  });

  factory DocumentMetadata.fromJson(JsonMap json) => DocumentMetadata(
        documentType: _stringValue(json['document_type'], 'document'),
        description: json['description']?.toString(),
        tags: _stringList(json['tags']),
        customFields: _jsonMapList(json['custom_fields']),
      );

  final String documentType;
  final String? description;
  final List<String> tags;
  final List<JsonMap> customFields;

  JsonMap toJson() => <String, dynamic>{
        'document_type': documentType,
        'description': description ?? '',
        'tags': tags,
        'custom_fields': customFields,
      };
}

class DocumentVersion {
  const DocumentVersion({
    required this.version,
    required this.uploadedAt,
    required this.vectorCount,
    required this.checksum,
    required this.isActive,
  });

  factory DocumentVersion.fromJson(JsonMap json) => DocumentVersion(
        version: _intValue(json['version']),
        uploadedAt: _stringValue(json['uploaded_at']),
        vectorCount: _intValue(json['vector_count']),
        checksum: _stringValue(json['checksum']),
        isActive: _boolValue(json['is_active']),
      );

  final int version;
  final String uploadedAt;
  final int vectorCount;
  final String checksum;
  final bool isActive;
}

class Document {
  const Document({
    required this.id,
    required this.name,
    required this.size,
    required this.uploadedAt,
    required this.vectorCount,
    required this.chunkCount,
    required this.version,
    required this.collectionId,
    required this.tags,
    required this.versionCount,
  });

  factory Document.fromJson(JsonMap json) => Document(
        id: _stringValue(json['id']),
        name: _stringValue(json['name']),
        size: _stringValue(json['size']),
        uploadedAt: _stringValue(json['uploaded_at']),
        vectorCount: _intValue(json['vector_count']),
        chunkCount: _intValue(json['chunk_count']),
        version: _intValue(json['version']),
        collectionId: _stringValue(json['collection_id']),
        tags: _stringList(json['tags']),
        versionCount: _intValue(json['version_count']),
      );

  final String id;
  final String name;
  final String size;
  final String uploadedAt;
  final int vectorCount;
  final int chunkCount;
  final int version;
  final String collectionId;
  final List<String> tags;
  final int versionCount;
}

class DocumentDetail extends Document {
  const DocumentDetail({
    required super.id,
    required super.name,
    required super.size,
    required super.uploadedAt,
    required super.vectorCount,
    required super.chunkCount,
    required super.version,
    required super.collectionId,
    required super.tags,
    required super.versionCount,
    required this.checksum,
    required this.metadata,
    required this.versions,
  });

  factory DocumentDetail.fromJson(JsonMap json) => DocumentDetail(
        id: _stringValue(json['id']),
        name: _stringValue(json['name']),
        size: _stringValue(json['size']),
        uploadedAt: _stringValue(json['uploaded_at']),
        vectorCount: _intValue(json['vector_count']),
        chunkCount: _intValue(json['chunk_count']),
        version: _intValue(json['version']),
        collectionId: _stringValue(json['collection_id']),
        tags: _stringList(json['tags']),
        versionCount: _intValue(json['version_count']),
        checksum: _stringValue(json['checksum']),
        metadata: DocumentMetadata.fromJson(_jsonMap(json['metadata'])),
        versions: _jsonMapList(json['versions'])
            .map(DocumentVersion.fromJson)
            .toList(growable: false),
      );

  final String checksum;
  final DocumentMetadata metadata;
  final List<DocumentVersion> versions;
}

class ChunkMetadata {
  const ChunkMetadata({
    required this.documentPath,
    required this.pageNumber,
    required this.section,
    required this.startChar,
    required this.endChar,
    required this.chunkId,
    required this.collectionId,
    required this.createdAt,
    required this.model,
    required this.dimension,
  });

  factory ChunkMetadata.fromJson(JsonMap json) => ChunkMetadata(
        documentPath: _stringValue(json['document_path']),
        pageNumber: _intValue(json['page_number']),
        section: _stringValue(json['section']),
        startChar: _intValue(json['start_char']),
        endChar: _intValue(json['end_char']),
        chunkId: _stringValue(json['chunk_id']),
        collectionId: _stringValue(json['collection_id']),
        createdAt: _stringValue(json['created_at']),
        model: _stringValue(json['model']),
        dimension: _intValue(json['dimension']),
      );

  final String documentPath;
  final int pageNumber;
  final String section;
  final int startChar;
  final int endChar;
  final String chunkId;
  final String collectionId;
  final String createdAt;
  final String model;
  final int dimension;
}

class DocumentChunk {
  const DocumentChunk({
    required this.index,
    required this.content,
    required this.tokens,
    required this.embedding,
    required this.metadata,
  });

  factory DocumentChunk.fromJson(JsonMap json) => DocumentChunk(
        index: _intValue(json['index']),
        content: _stringValue(json['content']),
        tokens: _intValue(json['tokens']),
        embedding: _doubleList(json['embedding']),
        metadata: ChunkMetadata.fromJson(_jsonMap(json['metadata'])),
      );

  final int index;
  final String content;
  final int tokens;
  final List<double> embedding;
  final ChunkMetadata metadata;
}

class UploadResponse {
  const UploadResponse({
    required this.success,
    required this.documentId,
    required this.vectorCount,
    required this.version,
    required this.message,
  });

  factory UploadResponse.fromJson(JsonMap json) => UploadResponse(
        success: _boolValue(json['success']),
        documentId: _stringValue(json['document_id']),
        vectorCount: _intValue(json['vector_count']),
        version: _intValue(json['version']),
        message: json['message']?.toString(),
      );

  final bool success;
  final String documentId;
  final int vectorCount;
  final int version;
  final String? message;
}

class SearchResult {
  const SearchResult({
    required this.id,
    required this.type,
    required this.documentName,
    required this.collectionId,
    required this.collectionName,
    required this.chunkIndex,
    required this.content,
    required this.score,
    required this.metadata,
  });

  factory SearchResult.fromJson(JsonMap json) => SearchResult(
        id: _stringValue(json['id']),
        type: _stringValue(json['type'], 'chunk'),
        documentName: _stringValue(json['document_name']),
        collectionId: _stringValue(json['collection_id']),
        collectionName: _stringValue(json['collection_name']),
        chunkIndex: json['chunk_index'] == null ? null : _intValue(json['chunk_index']),
        content: _stringValue(json['content']),
        score: _doubleValue(json['score']),
        metadata: _jsonMap(json['metadata']),
      );

  final String id;
  final String type;
  final String documentName;
  final String collectionId;
  final String collectionName;
  final int? chunkIndex;
  final String content;
  final double score;
  final JsonMap metadata;
}

class Tag {
  const Tag({required this.id, required this.name});

  factory Tag.fromJson(JsonMap json) => Tag(
        id: _stringValue(json['id']),
        name: _stringValue(json['name']),
      );

  final String id;
  final String name;
}

class DashboardStats {
  const DashboardStats({
    required this.totalCollections,
    required this.totalDocuments,
    required this.totalVectors,
    required this.totalSizeMb,
  });

  factory DashboardStats.fromJson(JsonMap json) => DashboardStats(
        totalCollections: _intValue(json['total_collections']),
        totalDocuments: _intValue(json['total_documents']),
        totalVectors: _intValue(json['total_vectors']),
        totalSizeMb: _doubleValue(json['total_size_mb']),
      );

  final int totalCollections;
  final int totalDocuments;
  final int totalVectors;
  final double totalSizeMb;
}

class LogsOverview {
  const LogsOverview({
    required this.total,
    required this.byLevel,
    required this.byApp,
    required this.topEndpoints,
  });

  factory LogsOverview.fromJson(JsonMap json) => LogsOverview(
        total: _intValue(json['total']),
        byLevel: _jsonMap(json['by_level']).map((key, value) => MapEntry(key, _intValue(value))),
        byApp: _jsonMap(json['by_app']).map((key, value) => MapEntry(key, _intValue(value))),
        topEndpoints: _jsonMapList(json['top_endpoints']),
      );

  final int total;
  final Map<String, int> byLevel;
  final Map<String, int> byApp;
  final List<JsonMap> topEndpoints;
}

class DashboardOverview {
  const DashboardOverview({
    required this.summary,
    required this.recentActivity,
    required this.topCollections,
    required this.uploadsPerDay,
    required this.vectorsPerWeek,
    required this.logsOverview,
  });

  factory DashboardOverview.fromJson(JsonMap json) => DashboardOverview(
        summary: DashboardStats.fromJson(_jsonMap(json['summary'])),
        recentActivity: _jsonMapList(json['recent_activity']).map(RecentActivity.fromJson).toList(growable: false),
        topCollections: _jsonMapList(json['top_collections']).map(TopCollection.fromJson).toList(growable: false),
        uploadsPerDay: _jsonMapList(json['uploads_per_day']).map(UploadsPerDay.fromJson).toList(growable: false),
        vectorsPerWeek: _jsonMapList(json['vectors_per_week']).map(VectorsPerWeek.fromJson).toList(growable: false),
        logsOverview: LogsOverview.fromJson(_jsonMap(json['logs_overview'])),
      );

  final DashboardStats summary;
  final List<RecentActivity> recentActivity;
  final List<TopCollection> topCollections;
  final List<UploadsPerDay> uploadsPerDay;
  final List<VectorsPerWeek> vectorsPerWeek;
  final LogsOverview logsOverview;
}

class RecentActivity {
  const RecentActivity({
    required this.id,
    required this.action,
    required this.entity,
    required this.timestamp,
    required this.details,
  });

  factory RecentActivity.fromJson(JsonMap json) => RecentActivity(
        id: _stringValue(json['id']),
        action: _stringValue(json['action']),
        entity: _stringValue(json['entity']),
        timestamp: _stringValue(json['timestamp']),
        details: _jsonMap(json['details']),
      );

  final String id;
  final String action;
  final String entity;
  final String timestamp;
  final JsonMap details;
}

class TopCollection {
  const TopCollection({
    required this.id,
    required this.name,
    required this.documentCount,
    required this.vectorCount,
  });

  factory TopCollection.fromJson(JsonMap json) => TopCollection(
        id: _stringValue(json['id']),
        name: _stringValue(json['name']),
        documentCount: _intValue(json['document_count']),
        vectorCount: _intValue(json['vector_count']),
      );

  final String id;
  final String name;
  final int documentCount;
  final int vectorCount;
}

class UploadsPerDay {
  const UploadsPerDay({required this.date, required this.count});

  factory UploadsPerDay.fromJson(JsonMap json) => UploadsPerDay(
        date: _stringValue(json['date']),
        count: _intValue(json['count']),
      );

  final String date;
  final int count;
}

class VectorsPerWeek {
  const VectorsPerWeek({required this.weekStart, required this.count});

  factory VectorsPerWeek.fromJson(JsonMap json) => VectorsPerWeek(
        weekStart: _stringValue(json['week_start']),
        count: _intValue(json['count']),
      );

  final String weekStart;
  final int count;
}

class JobProgress {
  const JobProgress({
    required this.jobId,
    required this.documentId,
    required this.version,
    required this.status,
    required this.percent,
    required this.processedChunks,
    required this.totalChunks,
    required this.startedAt,
    required this.updatedAt,
    required this.etaSeconds,
    required this.message,
    required this.documentName,
    required this.collectionId,
    required this.error,
  });

  factory JobProgress.fromJson(JsonMap json) => JobProgress(
        jobId: _stringValue(json['job_id']),
        documentId: _stringValue(json['document_id']),
        version: _intValue(json['version']),
        status: _stringValue(json['status']),
        percent: _doubleValue(json['percent']),
        processedChunks: _intValue(json['processed_chunks']),
        totalChunks: _intValue(json['total_chunks']),
        startedAt: _doubleValue(json['started_at']),
        updatedAt: _doubleValue(json['updated_at']),
        etaSeconds: json['eta_seconds'] == null ? null : _doubleValue(json['eta_seconds']),
        message: _stringValue(json['message']),
        documentName: _stringValue(json['document_name']),
        collectionId: _stringValue(json['collection_id']),
        error: _stringValue(json['error']),
      );

  final String jobId;
  final String documentId;
  final int version;
  final String status;
  final double percent;
  final int processedChunks;
  final int totalChunks;
  final double startedAt;
  final double updatedAt;
  final double? etaSeconds;
  final String message;
  final String documentName;
  final String collectionId;
  final String error;
}

class CancelIngestionResponse {
  const CancelIngestionResponse({required this.ok});

  factory CancelIngestionResponse.fromJson(JsonMap json) =>
      CancelIngestionResponse(ok: _boolValue(json['ok']));

  final bool ok;
}

class LogEntry {
  const LogEntry({
    required this.id,
    required this.timestamp,
    required this.requestId,
    required this.nivel,
    required this.modulo,
    required this.acao,
    required this.detalhes,
    required this.request,
    required this.response,
    required this.usuarioId,
    required this.projetoId,
    required this.tempoExecucao,
  });

  factory LogEntry.fromJson(JsonMap json) => LogEntry(
        id: _stringValue(json['id']),
        timestamp: _stringValue(json['timestamp']),
        requestId: json['requestId']?.toString(),
        nivel: _stringValue(json['nivel']),
        modulo: _stringValue(json['modulo']),
        acao: _stringValue(json['acao']),
        detalhes: _jsonMap(json['detalhes']),
        request: json['request'] == null ? null : _jsonMap(json['request']),
        response: json['response'] == null ? null : _jsonMap(json['response']),
        usuarioId: json['usuarioId']?.toString(),
        projetoId: json['projetoId']?.toString(),
        tempoExecucao: json['tempoExecucao'] == null ? null : _intValue(json['tempoExecucao']),
      );

  final String id;
  final String timestamp;
  final String? requestId;
  final String nivel;
  final String modulo;
  final String acao;
  final JsonMap detalhes;
  final JsonMap? request;
  final JsonMap? response;
  final String? usuarioId;
  final String? projetoId;
  final int? tempoExecucao;
}

class PageMeta {
  const PageMeta({required this.page, required this.pageSize, required this.total});

  factory PageMeta.fromJson(JsonMap json) => PageMeta(
        page: _intValue(json['page']),
        pageSize: _intValue(json['pageSize']),
        total: _intValue(json['total']),
      );

  final int page;
  final int pageSize;
  final int total;
}

class LogList {
  const LogList({required this.items, required this.meta});

  factory LogList.fromJson(JsonMap json) => LogList(
        items: _jsonMapList(json['items']).map(LogEntry.fromJson).toList(growable: false),
        meta: PageMeta.fromJson(_jsonMap(json['meta'])),
      );

  final List<LogEntry> items;
  final PageMeta meta;
}

class LogFacets {
  const LogFacets({
    required this.apps,
    required this.endpoints,
    required this.projects,
    required this.users,
  });

  factory LogFacets.fromJson(JsonMap json) => LogFacets(
        apps: _stringList(json['apps']),
        endpoints: _stringList(json['endpoints']),
        projects: _stringList(json['projects']),
        users: _stringList(json['users']),
      );

  final List<String> apps;
  final List<String> endpoints;
  final List<String> projects;
  final List<String> users;
}

class TopEndpoint {
  const TopEndpoint({required this.endpoint, required this.count});

  factory TopEndpoint.fromJson(JsonMap json) => TopEndpoint(
        endpoint: _stringValue(json['endpoint']),
        count: _intValue(json['c']),
      );

  final String endpoint;
  final int count;
}

class LogSummary {
  const LogSummary({
    required this.total,
    required this.byLevel,
    required this.byApp,
    required this.topEndpoints,
  });

  factory LogSummary.fromJson(JsonMap json) => LogSummary(
        total: _intValue(json['total']),
        byLevel: _jsonMap(json['byLevel']).map(
          (key, value) => MapEntry(key, _intValue(value)),
        ),
        byApp: _jsonMap(json['byApp']).map(
          (key, value) => MapEntry(key, _intValue(value)),
        ),
        topEndpoints: _jsonMapList(json['topEndpoints'])
            .map(TopEndpoint.fromJson)
            .toList(growable: false),
      );

  final int total;
  final Map<String, int> byLevel;
  final Map<String, int> byApp;
  final List<TopEndpoint> topEndpoints;
}

class LogIngestItem {
  const LogIngestItem({
    required this.nivel,
    required this.modulo,
    required this.acao,
    this.id,
    this.timestamp,
    this.requestId,
    this.detalhes = const <String, dynamic>{},
    this.request,
    this.response,
    this.usuarioId,
    this.projetoId,
    this.tempoExecucao,
  });

  final String? id;
  final DateTime? timestamp;
  final String? requestId;
  final String nivel;
  final String modulo;
  final String acao;
  final JsonMap detalhes;
  final JsonMap? request;
  final JsonMap? response;
  final String? usuarioId;
  final String? projetoId;
  final int? tempoExecucao;

  JsonMap toJson() => <String, dynamic>{
        if (id != null) 'id': id,
        if (timestamp != null) 'timestamp': timestamp!.toIso8601String(),
        if (requestId != null) 'request_id': requestId,
        'nivel': nivel,
        'modulo': modulo,
        'acao': acao,
        'detalhes': detalhes,
        if (request != null) 'request': request,
        if (response != null) 'response': response,
        if (usuarioId != null) 'usuario_id': usuarioId,
        if (projetoId != null) 'projeto_id': projetoId,
        if (tempoExecucao != null) 'tempo_execucao': tempoExecucao,
      };
}

class LogIngestResponse {
  const LogIngestResponse({required this.accepted});

  factory LogIngestResponse.fromJson(JsonMap json) =>
      LogIngestResponse(accepted: _intValue(json['accepted']));

  final int accepted;
}

class TokenUsageRecord {
  const TokenUsageRecord({
    required this.id,
    required this.timestamp,
    required this.provider,
    required this.modelId,
    required this.callType,
    required this.inputTokens,
    required this.outputTokens,
    required this.totalTokens,
    required this.latencyMs,
    required this.status,
    required this.errorCode,
    required this.userId,
    required this.collectionId,
    required this.operation,
    required this.extra,
  });

  factory TokenUsageRecord.fromJson(JsonMap json) => TokenUsageRecord(
        id: _stringValue(json['id']),
        timestamp: _stringValue(json['timestamp']),
        provider: _stringValue(json['provider']),
        modelId: _stringValue(json['modelId']),
        callType: _stringValue(json['callType']),
        inputTokens: _intValue(json['inputTokens']),
        outputTokens: json['outputTokens'] == null ? null : _intValue(json['outputTokens']),
        totalTokens: _intValue(json['totalTokens']),
        latencyMs: _intValue(json['latencyMs']),
        status: _stringValue(json['status']),
        errorCode: json['errorCode']?.toString(),
        userId: json['userId']?.toString(),
        collectionId: json['collectionId']?.toString(),
        operation: _stringValue(json['operation']),
        extra: _jsonMap(json['extra']),
      );

  final String id;
  final String timestamp;
  final String provider;
  final String modelId;
  final String callType;
  final int inputTokens;
  final int? outputTokens;
  final int totalTokens;
  final int latencyMs;
  final String status;
  final String? errorCode;
  final String? userId;
  final String? collectionId;
  final String operation;
  final JsonMap extra;
}

class TokenUsageSummary {
  const TokenUsageSummary({
    required this.totalRecords,
    required this.totalInputTokens,
    required this.totalOutputTokens,
    required this.totalTokens,
    required this.providers,
  });

  factory TokenUsageSummary.fromJson(JsonMap json) => TokenUsageSummary(
        totalRecords: _intValue(json['totalRecords']),
        totalInputTokens: _intValue(json['totalInputTokens']),
        totalOutputTokens: _intValue(json['totalOutputTokens']),
        totalTokens: _intValue(json['totalTokens']),
        providers: _jsonMapList(json['providers']),
      );

  final int totalRecords;
  final int totalInputTokens;
  final int totalOutputTokens;
  final int totalTokens;
  final List<JsonMap> providers;
}

class TokenUsageList {
  const TokenUsageList({required this.items, required this.meta, required this.summary});

  factory TokenUsageList.fromJson(JsonMap json) => TokenUsageList(
        items: _jsonMapList(json['items']).map(TokenUsageRecord.fromJson).toList(growable: false),
        meta: PageMeta.fromJson(_jsonMap(json['meta'])),
        summary: TokenUsageSummary.fromJson(_jsonMap(json['summary'])),
      );

  final List<TokenUsageRecord> items;
  final PageMeta meta;
  final TokenUsageSummary summary;
}

class CollectionListParams {
  const CollectionListParams({
    this.skip = 0,
    this.limit = 100,
    this.logic,
    this.userId,
    this.projectId,
    this.alias,
    this.query,
  });

  final int skip;
  final int limit;
  final String? logic;
  final String? userId;
  final String? projectId;
  final String? alias;
  final String? query;

  Map<String, Object?> toQueryParameters() => <String, Object?>{
        'skip': skip,
        'limit': limit,
        'logic': logic,
        'user_id': userId,
        'project_id': projectId,
        'alias': alias,
        'query': query,
      };
}

class CreateCollectionParams {
  const CreateCollectionParams({
    required this.name,
    required this.embeddingModel,
    required this.dimension,
    this.chunkSize,
    this.chunkOverlap,
    this.description,
    this.alias,
    this.isPublic,
    this.userId,
    this.projectId,
  });

  final String name;
  final String embeddingModel;
  final int dimension;
  final int? chunkSize;
  final int? chunkOverlap;
  final String? description;
  final String? alias;
  final bool? isPublic;
  final String? userId;
  final String? projectId;

  JsonMap toJson() => <String, dynamic>{
        'name': name,
        'embedding_model': embeddingModel,
        'dimension': dimension,
        if (chunkSize != null) 'chunk_size': chunkSize,
        if (chunkOverlap != null) 'chunk_overlap': chunkOverlap,
        if (description != null) 'description': description,
        if (alias != null) 'alias': alias,
        if (isPublic != null) 'is_public': isPublic,
        if (userId != null) 'user_id': userId,
        if (projectId != null) 'project_id': projectId,
      };
}

class UpdateCollectionParams {
  const UpdateCollectionParams({this.name, this.description, this.isPublic});

  final String? name;
  final String? description;
  final bool? isPublic;

  JsonMap toJson() => <String, dynamic>{
        if (name != null) 'name': name,
        if (description != null) 'description': description,
        if (isPublic != null) 'is_public': isPublic,
      };
}

class DocumentListParams {
  const DocumentListParams({this.skip = 0, this.limit = 100, this.collectionId});

  final int skip;
  final int limit;
  final String? collectionId;

  Map<String, Object?> toQueryParameters() => <String, Object?>{
        'skip': skip,
        'limit': limit,
        'collection_id': collectionId,
      };
}

class UploadMetadata {
  const UploadMetadata({
    this.documentType = 'document',
    this.description,
    this.tags = const [],
    this.customFields = const [],
  });

  final String documentType;
  final String? description;
  final List<String> tags;
  final List<JsonMap> customFields;

  JsonMap toJson() => <String, dynamic>{
        'document_type': documentType,
        'description': description ?? '',
        'tags': tags,
        'custom_fields': customFields,
      };
}

class UploadOptions {
  const UploadOptions({
    required this.collectionId,
    this.metadata,
    this.overwriteExisting,
    this.embeddingModel,
    this.dimension,
    this.extractionTool,
  });

  final String collectionId;
  final UploadMetadata? metadata;
  final bool? overwriteExisting;
  final String? embeddingModel;
  final int? dimension;
  final String? extractionTool;
}

class UploadFile {
  const UploadFile({
    required this.filename,
    required this.bytes,
    this.mediaType = 'application/octet-stream',
  });

  factory UploadFile.fromBytes({
    required String filename,
    required Uint8List bytes,
    String mediaType = 'application/octet-stream',
  }) =>
      UploadFile(filename: filename, bytes: bytes, mediaType: mediaType);

  final String filename;
  final Uint8List bytes;
  final String mediaType;
}

class SearchParams {
  const SearchParams({
    this.collectionId,
    this.limit = 10,
    this.offset = 0,
    this.minScore = 0,
  });

  final String? collectionId;
  final int limit;
  final int offset;
  final double minScore;

  Map<String, Object?> toQueryParameters() => <String, Object?>{
        'collection_id': collectionId,
        'limit': limit,
        'offset': offset,
        'min_score': minScore,
      };
}

class TagListParams {
  const TagListParams({this.skip = 0, this.limit = 100});

  final int skip;
  final int limit;

  Map<String, Object?> toQueryParameters() => <String, Object?>{'skip': skip, 'limit': limit};
}

class LogListParams {
  const LogListParams({
    this.page = 1,
    this.pageSize = 50,
    this.orderBy = 'timestamp',
    this.orderDir = 'desc',
    this.fromTs,
    this.toTs,
    this.nivel,
    this.app,
    this.endpoint,
    this.statusCode,
    this.query,
    this.userId,
    this.sessionId,
    this.projectIds,
  });

  final int page;
  final int pageSize;
  final String orderBy;
  final String orderDir;
  final DateTime? fromTs;
  final DateTime? toTs;
  final String? nivel;
  final String? app;
  final String? endpoint;
  final int? statusCode;
  final String? query;
  final String? userId;
  final String? sessionId;
  final String? projectIds;

  Map<String, Object?> toQueryParameters() => <String, Object?>{
        'page': page,
        'page_size': pageSize,
        'order_by': orderBy,
        'order_dir': orderDir,
        'from_ts': fromTs?.toIso8601String(),
        'to_ts': toTs?.toIso8601String(),
        'nivel': nivel,
        'app': app,
        'endpoint': endpoint,
        'status_code': statusCode,
        'q': query,
        'user_id': userId,
        'session_id': sessionId,
        'project_ids': projectIds,
      };
}

class LogExportParams {
  const LogExportParams({
    this.format = LogExportFormat.json,
    this.limit = 10000,
    this.fromTs,
    this.toTs,
    this.nivel,
    this.app,
    this.endpoint,
    this.statusCode,
    this.query,
    this.userId,
    this.sessionId,
    this.projectIds,
  });

  final LogExportFormat format;
  final int limit;
  final DateTime? fromTs;
  final DateTime? toTs;
  final String? nivel;
  final String? app;
  final String? endpoint;
  final int? statusCode;
  final String? query;
  final String? userId;
  final String? sessionId;
  final String? projectIds;

  Map<String, Object?> toQueryParameters() => <String, Object?>{
        'format': format.value,
        'limit': limit,
        'from_ts': fromTs?.toIso8601String(),
        'to_ts': toTs?.toIso8601String(),
        'nivel': nivel,
        'app': app,
        'endpoint': endpoint,
        'status_code': statusCode,
        'q': query,
        'user_id': userId,
        'session_id': sessionId,
        'project_ids': projectIds,
      };
}

class TokenUsageParams {
  const TokenUsageParams({
    this.page = 1,
    this.pageSize = 50,
    this.orderBy = 'timestamp',
    this.orderDir = 'desc',
    this.provider,
    this.callType,
    this.operation,
    this.userId,
    this.collectionId,
    this.status,
    this.fromTs,
    this.toTs,
  });

  final int page;
  final int pageSize;
  final String orderBy;
  final String orderDir;
  final String? provider;
  final String? callType;
  final String? operation;
  final String? userId;
  final String? collectionId;
  final String? status;
  final DateTime? fromTs;
  final DateTime? toTs;

  Map<String, Object?> toQueryParameters() => <String, Object?>{
        'page': page,
        'page_size': pageSize,
        'order_by': orderBy,
        'order_dir': orderDir,
        'provider': provider,
        'call_type': callType,
        'operation': operation,
        'user_id': userId,
        'collection_id': collectionId,
        'status': status,
        'from_ts': fromTs?.toIso8601String(),
        'to_ts': toTs?.toIso8601String(),
      };
}