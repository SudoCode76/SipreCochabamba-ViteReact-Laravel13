<?php

namespace App\Services\Repository;

use RuntimeException;

class RepositoryClientException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $endpoint,
        private readonly ?int $httpStatus = null,
        private readonly array|string|null $responsePayload = null,
        private readonly string $errorType = 'http_error',
    ) {
        parent::__construct($message);
    }

    public function endpoint(): string
    {
        return $this->endpoint;
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function responsePayload(): array|string|null
    {
        return $this->responsePayload;
    }

    public function errorType(): string
    {
        return $this->errorType;
    }
}
