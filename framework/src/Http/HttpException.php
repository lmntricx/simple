<?php

namespace Simple\Http;

use RuntimeException;

/**
 * Throw from anywhere to end the request with an error response.
 */
class HttpException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $message = '',
        public readonly mixed $errors = null,
    ) {
        parent::__construct($message !== '' ? $message : (Response::STATUS_TEXT[$status] ?? 'Error'), $status);
    }
}
