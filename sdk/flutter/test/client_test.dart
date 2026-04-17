import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:http/http.dart' as http;
import 'package:ingestao_vetorial_flutter_sdk/ingestao_vetorial_flutter_sdk.dart';
import 'package:test/test.dart';

class RecordingClient extends http.BaseClient {
  RecordingClient(this._handler);

  final Future<http.StreamedResponse> Function(http.BaseRequest request) _handler;
  http.BaseRequest? lastRequest;

  @override
  Future<http.StreamedResponse> send(http.BaseRequest request) {
    lastRequest = request;
    return _handler(request);
  }
}

http.StreamedResponse jsonResponse(Object body, {int statusCode = 200}) {
  final bytes = utf8.encode(jsonEncode(body));
  return http.StreamedResponse(Stream<List<int>>.value(bytes), statusCode,
      headers: const <String, String>{'content-type': 'application/json'});
}

void main() {
  group('IngestaoVetorialClient', () {
    test('unwraps paginated collections and sends API key', () async {
      final client = RecordingClient(
        (request) async => jsonResponse(<String, Object?>{
          'items': <Object?>[
            <String, Object?>{
              'id': 'col-1',
              'name': 'My Collection',
              'alias': 'my-col',
              'description': null,
              'is_public': false,
              'embedding_model': 'text-embedding-3-small',
              'dimension': 1536,
              'chunk_size': 1400,
              'chunk_overlap': 250,
              'created_at': '2024-01-01T00:00:00Z',
              'document_count': 3,
              'user_id': null,
              'project_id': null,
            },
          ],
          'meta': <String, Object?>{'has_more': false},
        }),
      );

      final sdk = IngestaoVetorialClient(
        baseUrl: 'http://localhost:8000',
        apiKey: 'test-key',
        httpClient: client,
      );

      final result = await sdk.collections();

      expect(result.single.id, 'col-1');
      expect(client.lastRequest?.headers['X-API-Key'], 'test-key');
      expect(client.lastRequest?.url.path, '/api/v1/collections');
    });

    test('reprocessDocument sends params in query string', () async {
      final client = RecordingClient(
        (request) async => jsonResponse(<String, Object?>{
          'success': true,
          'document_id': 'doc-1',
          'vector_count': 0,
          'version': 2,
          'message': null,
        }),
      );

      final sdk = IngestaoVetorialClient(baseUrl: 'http://localhost:8000', httpClient: client);

      final response = await sdk.reprocessDocument('doc-1',
          mode: ReprocessMode.append, sourceVersion: 1);

      expect(response.version, 2);
      expect(client.lastRequest?.method, 'POST');
      expect(client.lastRequest?.url.queryParameters['mode'], 'append');
      expect(client.lastRequest?.url.queryParameters['source_version'], '1');
    });

    test('dashboardOverview returns the full payload', () async {
      final client = RecordingClient(
        (request) async => jsonResponse(<String, Object?>{
          'summary': <String, Object?>{
            'total_collections': 4,
            'total_documents': 20,
            'total_vectors': 1000,
            'total_size_mb': 50.5,
          },
          'recent_activity': const <Object?>[],
          'top_collections': const <Object?>[],
          'uploads_per_day': const <Object?>[],
          'vectors_per_week': const <Object?>[],
          'logs_overview': <String, Object?>{
            'total': 10,
            'by_level': <String, Object?>{'INFO': 8},
            'by_app': <String, Object?>{'api': 10},
            'top_endpoints': const <Object?>[],
          },
        }),
      );

      final sdk = IngestaoVetorialClient(baseUrl: 'http://localhost:8000', httpClient: client);
      final response = await sdk.dashboardOverview();

      expect(response.summary.totalVectors, 1000);
      expect(response.logsOverview.total, 10);
    });

    test('upload serializes metadata and collection id as multipart fields', () async {
      final client = RecordingClient(
        (request) async => jsonResponse(<String, Object?>{
          'success': true,
          'document_id': 'doc-1',
          'vector_count': 0,
          'version': 1,
          'message': null,
        }),
      );

      final sdk = IngestaoVetorialClient(baseUrl: 'http://localhost:8000', httpClient: client);

      await sdk.upload(
        UploadFile.fromBytes(filename: 'file.pdf', bytes: Uint8List.fromList(<int>[1, 2, 3])),
        const UploadOptions(
          collectionId: 'col-1',
          metadata: UploadMetadata(documentType: 'report', tags: <String>['demo']),
          overwriteExisting: true,
        ),
      );

      final request = client.lastRequest;
      expect(request, isA<http.MultipartRequest>());
      final multipart = request! as http.MultipartRequest;
      expect(multipart.fields['collection_id'], 'col-1');
      expect(multipart.fields['overwrite_existing'], 'true');
      final metadata = jsonDecode(multipart.fields['metadata']!) as Map<String, dynamic>;
      expect(metadata['document_type'], 'report');
      expect((metadata['tags'] as List<Object?>).single, 'demo');
      expect(multipart.files.single.filename, 'file.pdf');
    });

    test('activeJobs paginates while has_more is true', () async {
      var calls = 0;
      final client = RecordingClient((request) async {
        calls += 1;
        if (calls == 1) {
          return jsonResponse(<String, Object?>{
            'items': <Object?>[
              <String, Object?>{'job_id': 'job-1', 'document_id': 'doc-1', 'version': 1, 'status': 'chunking', 'percent': 50, 'processed_chunks': 1, 'total_chunks': 2, 'started_at': 1, 'updated_at': 2, 'eta_seconds': 3, 'message': 'running', 'document_name': 'a.pdf', 'collection_id': 'col-1', 'error': ''},
            ],
            'meta': <String, Object?>{'has_more': true},
          });
        }
        return jsonResponse(<String, Object?>{
          'items': <Object?>[
            <String, Object?>{'job_id': 'job-2', 'document_id': 'doc-2', 'version': 1, 'status': 'completed', 'percent': 100, 'processed_chunks': 2, 'total_chunks': 2, 'started_at': 1, 'updated_at': 2, 'eta_seconds': null, 'message': 'done', 'document_name': 'b.pdf', 'collection_id': 'col-1', 'error': ''},
          ],
          'meta': <String, Object?>{'has_more': false},
        });
      });

      final sdk = IngestaoVetorialClient(baseUrl: 'http://localhost:8000', httpClient: client);

      final jobs = await sdk.activeJobs();

      expect(jobs.length, 2);
      expect(jobs.first.jobId, 'job-1');
      expect(jobs.last.jobId, 'job-2');
    });

    test('streamProgress returns SSE lines', () async {
      final client = RecordingClient(
        (request) async => http.StreamedResponse(
          Stream<List<int>>.fromIterable(<List<int>>[
            utf8.encode('data: {"status":"chunking"}\n'),
            utf8.encode('\n'),
          ]),
          200,
        ),
      );

      final sdk = IngestaoVetorialClient(baseUrl: 'http://localhost:8000', httpClient: client);
      final stream = await sdk.streamProgress();
      final lines = await stream.toList();

      expect(lines, contains('data: {"status":"chunking"}'));
      expect(client.lastRequest?.url.path, '/api/v1/progress/stream');
    });

    test('exportLogs returns raw bytes', () async {
      final bytes = Uint8List.fromList(utf8.encode('timestamp,nivel\n2026-01-01,INFO\n'));
      final client = RecordingClient(
        (request) async => http.StreamedResponse(Stream<List<int>>.value(bytes), 200),
      );

      final sdk = IngestaoVetorialClient(baseUrl: 'http://localhost:8000', httpClient: client);
      final result = await sdk.exportLogs(const LogExportParams(format: LogExportFormat.csv));

      expect(utf8.decode(result), contains('timestamp,nivel'));
      expect(client.lastRequest?.url.queryParameters['format'], 'csv');
    });

    test('ingestLogs posts payload and optional token header', () async {
      final client = RecordingClient(
        (request) async => jsonResponse(<String, Object?>{'accepted': 1}),
      );

      final sdk = IngestaoVetorialClient(baseUrl: 'http://localhost:8000', httpClient: client);
      final response = await sdk.ingestLogs(
        const <LogIngestItem>[
          LogIngestItem(
            nivel: 'INFO',
            modulo: 'sdk',
            acao: 'startup',
            detalhes: <String, dynamic>{'app': 'external'},
          ),
        ],
        logSinkToken: 'sink-token',
      );

      expect(response.accepted, 1);
      expect(client.lastRequest?.headers['X-Log-Sink-Token'], 'sink-token');
      expect(client.lastRequest?.url.path, '/api/v1/logs/ingest');
    });

    test('tokenUsage returns summary payload', () async {
      final client = RecordingClient(
        (request) async => jsonResponse(<String, Object?>{
          'items': const <Object?>[],
          'meta': <String, Object?>{'page': 1, 'pageSize': 50, 'total': 0},
          'summary': <String, Object?>{
            'totalRecords': 0,
            'totalInputTokens': 0,
            'totalOutputTokens': 0,
            'totalTokens': 0,
            'providers': const <Object?>[],
          },
        }),
      );

      final sdk = IngestaoVetorialClient(baseUrl: 'http://localhost:8000', httpClient: client);
      final response = await sdk.tokenUsage(const TokenUsageParams(provider: 'openai', status: 'success'));

      expect(response.summary.totalTokens, 0);
      expect(client.lastRequest?.url.queryParameters['provider'], 'openai');
      expect(client.lastRequest?.url.queryParameters['status'], 'success');
    });
  });
}