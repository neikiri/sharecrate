<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Thrown to produce a specific HTTP status page (404, 403, 410, 429, ...).
 */
class HttpException extends RuntimeException
{
    public function __construct(
        private readonly int $status = 500,
        string $message = '',
        private readonly ?string $translationKey = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $status, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function translationKey(): ?string
    {
        return $this->translationKey;
    }

    public static function notFound(?string $key = null): self
    {
        return new self(404, 'Not Found', $key);
    }

    public static function forbidden(?string $key = null): self
    {
        return new self(403, 'Forbidden', $key);
    }

    public static function gone(?string $key = null): self
    {
        return new self(410, 'Gone', $key);
    }

    public static function tooManyRequests(?string $key = null): self
    {
        return new self(429, 'Too Many Requests', $key);
    }

    public static function badRequest(?string $key = null): self
    {
        return new self(400, 'Bad Request', $key);
    }
}
