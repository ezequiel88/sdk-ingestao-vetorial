# ingestao_vetorial_flutter_sdk · Flutter / Dart

SDK oficial para Flutter e Dart da API do Ingestao Vetorial.

O pacote cobre colecoes, documentos, upload, busca semantica, tags, estatisticas, progresso de ingestao, logs e consulta de uso de tokens. Os endpoints paginados da API podem responder com `items` e `meta`, mas o SDK desempacota `items` automaticamente para manter uma interface simples no app.

## Requisitos

- Dart >= 3.4
- Flutter >= 3.22, se usado dentro de app Flutter

## Instalacao

```yaml
dependencies:
  ingestao_vetorial_flutter_sdk: ^0.1.1
```

## Inicio rapido

```dart
import 'dart:typed_data';

import 'package:ingestao_vetorial_flutter_sdk/ingestao_vetorial_flutter_sdk.dart';

Future<void> main() async {
  final client = IngestaoVetorialClient(
    baseUrl: 'http://localhost:8000',
    apiKey: 'sua-api-key',
  );

  final collection = await client.createCollection(
    const CreateCollectionParams(
      name: 'Documentos Juridicos',
      embeddingModel: 'text-embedding-3-small',
      dimension: 1536,
    ),
  );

  final upload = await client.upload(
    UploadFile.fromBytes(
      filename: 'contrato.pdf',
      bytes: Uint8List.fromList([1, 2, 3]),
    ),
    UploadOptions(collectionId: collection.id),
  );

  final results = await client.search(
    'clausula de rescisao',
    const SearchParams(limit: 5, minScore: 0.75),
  );

  print(upload.documentId);
  print(results.first.documentName);
}
```

## Upload em Flutter

O SDK recebe bytes, o que funciona bem com `image_picker`, `file_picker` e similares.

```dart
final file = UploadFile.fromBytes(
  filename: 'arquivo.pdf',
  bytes: bytesDoArquivo,
  mediaType: 'application/pdf',
);

await client.upload(
  file,
  const UploadOptions(
    collectionId: 'uuid-da-colecao',
    metadata: UploadMetadata(
      documentType: 'report',
      tags: ['financeiro', '2026'],
    ),
  ),
);
```

## Tratamento de erros

```dart
try {
  await client.getCollection('id-inexistente');
} on ApiError catch (error) {
  print('Erro ${error.statusCode}: ${error.body}');
}
```

## Endpoints adicionais

```dart
final overview = await client.dashboardOverview();
final stream = await client.streamProgress();
final usage = await client.tokenUsage(const TokenUsageParams(provider: 'openai'));

final ingest = await client.ingestLogs(
  const <LogIngestItem>[
    LogIngestItem(
      nivel: 'INFO',
      modulo: 'sdk',
      acao: 'startup',
      detalhes: <String, dynamic>{'app': 'external'},
    ),
  ],
  logSinkToken: 'token-opcional',
);

await for (final line in stream) {
  print(line);
}

print(overview.summary.totalVectors);
print(usage.summary.totalTokens);
print(ingest.accepted);
```

## Executar testes

```bash
cd sdk/flutter
dart pub get
dart analyze
dart test
```

## Licenca

MIT