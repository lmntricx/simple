<?php

namespace Simple\Testing;

use PHPUnit\Framework\Assert;
use Simple\Http\Response;

/**
 * Wraps a Response with assertions:
 *   $this->postJson('/auth/login', [...])->assertOk()->assertJsonPath('data.token_type', 'Bearer');
 */
class TestResponse
{
    private ?array $decoded = null;

    public function __construct(public readonly Response $response)
    {
    }

    public function status(): int
    {
        return $this->response->status();
    }

    /**
     * Decoded JSON body, or one value from it by dot path ("data.user.email").
     */
    public function json(?string $path = null): mixed
    {
        $this->decoded ??= json_decode($this->response->content(), true) ?? [];

        if ($path === null) {
            return $this->decoded;
        }

        $value = $this->decoded;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function header(string $name): ?string
    {
        foreach ($this->response->headers() as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }
        return null;
    }

    public function assertStatus(int $status): static
    {
        Assert::assertSame($status, $this->status(), "Expected status $status, got {$this->status()}. Body: " . $this->response->content());
        return $this;
    }

    public function assertOk(): static
    {
        return $this->assertStatus(200);
    }

    public function assertCreated(): static
    {
        return $this->assertStatus(201);
    }

    public function assertUnauthorized(): static
    {
        return $this->assertStatus(401);
    }

    public function assertForbidden(): static
    {
        return $this->assertStatus(403);
    }

    public function assertNotFound(): static
    {
        return $this->assertStatus(404);
    }

    public function assertUnprocessable(): static
    {
        return $this->assertStatus(422);
    }

    public function assertJsonPath(string $path, mixed $expected): static
    {
        Assert::assertSame($expected, $this->json($path), "JSON path [$path] mismatch.");
        return $this;
    }

    public function assertJsonMissingPath(string $path): static
    {
        $value = $this->json();
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                Assert::assertTrue(true);
                return $this;
            }
            $value = $value[$segment];
        }
        Assert::fail("JSON path [$path] should not exist.");
    }

    /**
     * @param string[] $fields fields that must have validation errors
     */
    public function assertJsonValidationErrors(array $fields): static
    {
        $this->assertUnprocessable();
        foreach ($fields as $field) {
            Assert::assertNotEmpty($this->json("errors.$field"), "Expected a validation error for [$field].");
        }
        return $this;
    }

    public function assertHeader(string $name, ?string $value = null): static
    {
        Assert::assertNotNull($this->header($name), "Header [$name] is missing.");
        if ($value !== null) {
            Assert::assertSame($value, $this->header($name));
        }
        return $this;
    }
}
