<?php

namespace app\Helpers;

class ResponseHelper
{
    private function send(
        string $status,
        string $message,
        mixed $data = null,
        int $code = 200,
        array $meta = []
    ): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');

        $payload = [
            'status' => $status,
            'message' => $message,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit();
    }

    public function success(
        mixed $data = null,
        string $message = 'Success',
        int $code = 200,
        array $meta = []
    ): void {
        $this->send('success', $message, $data, $code, $meta);
    }

    public function created(
        mixed $data = null,
        string $message = 'Created',
        int $code = 201,
        array $meta = []
    ): void {
        $this->send('success', $message, $data, $code, $meta);
    }

    public function noContent(): void
    {
        http_response_code(204);
        exit();
    }

    public function error(
        string $message = 'Error',
        int $code = 400,
        mixed $errors = null,
        array $meta = []
    ): void {
        $payload = $errors !== null ? ['errors' => $errors] : null;
        $this->send('error', $message, $payload, $code, $meta);
    }

    public function validationError(
        array $errors,
        string $message = 'Validation failed'
    ): void {
        $this->error($message, 422, $errors);
    }

    public function notFound(
        string $message = 'Not found',
        mixed $data = null
    ): void {
        $this->send('error', $message, $data, 404);
    }

    public function unauthorized(
        string $message = 'Unauthorized'
    ): void {
        $this->send('error', $message, null, 401);
    }

    public function forbidden(
        string $message = 'Forbidden'
    ): void {
        $this->send('error', $message, null, 403);
    }

    public function requireMethod(string $method): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
            $this->error('Method not allowed', 405);
        }
    }
}
