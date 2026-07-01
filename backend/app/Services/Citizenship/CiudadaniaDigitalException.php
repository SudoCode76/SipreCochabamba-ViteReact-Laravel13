<?php

namespace App\Services\Citizenship;

use RuntimeException;

class CiudadaniaDigitalException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $endpoint,
        private readonly ?int $httpStatus = null,
        private readonly array|string|null $requestPayload = null,
        private readonly array|string|null $responsePayload = null,
        private readonly string $phase = 'request',
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

    public function requestPayload(): array|string|null
    {
        return $this->requestPayload;
    }

    public function responsePayload(): array|string|null
    {
        return $this->responsePayload;
    }

    public function phase(): string
    {
        return $this->phase;
    }

    public function errorType(): string
    {
        return $this->errorType;
    }
}
