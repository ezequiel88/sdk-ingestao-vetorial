<?php

declare(strict_types=1);

namespace IngestaoVetorial\Exceptions;

use GuzzleHttp\Exception\RequestException;

final class ApiException extends \RuntimeException
{
    public function __construct(
        private readonly int    $statusCode,
        private readonly string $responseBody,
        \Throwable|null         $previous = null,
    ) {
        parent::__construct(
            "API error {$statusCode}: {$responseBody}",
            $statusCode,
            $previous,
        );
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function responseBody(): string
    {
        return $this->responseBody;
    }

    public static function fromGuzzle(\Throwable $e): self
    {
        if ($e instanceof RequestException && $e->hasResponse()) {
            $response = $e->getResponse();

            if ($response !== null) {
                return new self(
                    $response->getStatusCode(),
                    (string) $response->getBody(),
                    $e,
                );
            }
        }

        return new self(0, $e->getMessage(), $e);
    }
}
