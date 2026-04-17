<?php

declare(strict_types=1);

namespace IngestaoVetorial\DTO;

final class LogEntry
{
    /**
     * @param array<string, mixed>|null $request
     * @param array<string, mixed>|null $response
     * @param array<string, mixed>      $detalhes
     */
    public function __construct(
        public readonly string  $id,
        public readonly string  $timestamp,
        public readonly ?string $requestId,
        public readonly string  $nivel,
        public readonly string  $modulo,
        public readonly string  $acao,
        public readonly array   $detalhes,
        public readonly ?array  $request,
        public readonly ?array  $response,
        public readonly ?string $usuarioId,
        public readonly ?string $projetoId,
        /** Execution time in milliseconds. */
        public readonly ?int    $tempoExecucao,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id:            (string) $data['id'],
            timestamp:     (string) $data['timestamp'],
            requestId:     isset($data['requestId']) ? (string) $data['requestId'] : null,
            nivel:         (string) ($data['nivel'] ?? ''),
            modulo:        (string) ($data['modulo'] ?? ''),
            acao:          (string) ($data['acao'] ?? ''),
            detalhes:      is_array($data['detalhes'] ?? null) ? $data['detalhes'] : [],
            request:       is_array($data['request'] ?? null) ? $data['request'] : null,
            response:      is_array($data['response'] ?? null) ? $data['response'] : null,
            usuarioId:     isset($data['usuarioId']) ? (string) $data['usuarioId'] : null,
            projetoId:     isset($data['projetoId']) ? (string) $data['projetoId'] : null,
            tempoExecucao: isset($data['tempoExecucao']) ? (int) $data['tempoExecucao'] : null,
        );
    }
}
