<?php

namespace Simple\Http;

use JsonSerializable;

class Response
{
    public const STATUS_TEXT = [
        200 => 'OK',
        201 => 'Created',
        204 => 'No Content',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        409 => 'Conflict',
        422 => 'Unprocessable Content',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        503 => 'Service Unavailable',
    ];

    public function __construct(
        protected string $content = '',
        protected int $status = 200,
        protected array $headers = [],
    ) {
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): static
    {
        return new static(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'] + $headers,
        );
    }

    /**
     * Standard envelope: {"status": "success", "message": ..., "data": ..., "meta": ...}
     */
    public static function success(mixed $data = null, string $message = 'Success', int $status = 200, array $meta = []): static
    {
        return static::json(static::envelope('success', $message, $data, $meta), $status);
    }

    public static function created(mixed $data = null, string $message = 'Created'): static
    {
        return static::success($data, $message, 201);
    }

    public static function error(string $message, int $status = 400, mixed $errors = null): static
    {
        return static::json(static::envelope('error', $message, null, [], $errors), $status);
    }

    public static function noContent(): static
    {
        return new static('', 204);
    }

    /**
     * Turn whatever a controller returned into a Response.
     */
    public static function from(mixed $value): static
    {
        return match (true) {
            $value instanceof self => $value,
            $value === null => static::noContent(),
            is_string($value) => new static($value, 200, ['Content-Type' => 'text/plain; charset=utf-8']),
            is_array($value), $value instanceof JsonSerializable => static::json($value),
            default => static::json($value),
        };
    }

    protected static function envelope(string $status, string $message, mixed $data, array $meta, mixed $errors = null): array
    {
        $payload = ['status' => $status, 'message' => $message];

        if ($data !== null) {
            $payload['data'] = $data;
        }
        if ($errors !== null) {
            $payload['errors'] = $errors;
        }
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return $payload;
    }

    public function header(string $name, string $value): static
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header("$name: $value");
            }
        }

        echo $this->content;
    }
}
