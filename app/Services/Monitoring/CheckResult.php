<?php

namespace App\Services\Monitoring;

class CheckResult
{
    public function __construct(
        public bool $ok,
        public ?int $statusCode = null,
        public ?int $responseTimeMs = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public ?string $resolvedIp = null,
        public ?string $resolvedHost = null,
        public ?string $finalUrl = null,
        public array $meta = [],
    ) {}
}
