<?php

namespace Simple\Testing;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Simple\Application;
use Simple\Auth\Auth;
use Simple\Database\Migrator;
use Simple\Database\Model;
use Simple\Database\Schema\Schema;
use Simple\Http\Request;
use Simple\Http\UploadedFile;
use Simple\Queue\Queue;
use Simple\Storage\Storage;
use Simple\Mail\Mail;
use Simple\Mail\Transport\ArrayTransport;
use Simple\Support\RateLimiter;

/**
 * Base class for application tests. Each test gets a freshly booted app, an
 * empty database with migrations run, and a fake mailer ($this->mail).
 *
 *   $this->postJson('/auth/register', [...])->assertCreated();
 *   $this->actingAs($user)->getJson('/auth/me')->assertOk();
 */
abstract class TestCase extends BaseTestCase
{
    protected Application $app;
    protected ArrayTransport $mail;

    /** Run database/migrations before each test. */
    protected bool $migrate = true;

    private array $defaultHeaders = [];
    private array $serverVariables = ['REMOTE_ADDR' => '127.0.0.1'];
    private ?string $rateLimitDir = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = require $this->basePath() . '/bootstrap/app.php';
        $this->mail = Mail::fake();
        Storage::reset();
        Queue::reset();

        $this->rateLimitDir = sys_get_temp_dir() . '/simple_rl_' . bin2hex(random_bytes(6));
        RateLimiter::$directory = $this->rateLimitDir;

        Schema::dropAllTables();
        if ($this->migrate) {
            (new Migrator($this->basePath() . '/database/migrations'))->migrate();
        }
    }

    protected function tearDown(): void
    {
        // Undo the error/exception handlers the Application installed while booting.
        restore_error_handler();
        restore_exception_handler();

        Auth::forget();
        Mail::useTransport(null);
        Storage::reset();
        Queue::reset();

        foreach (glob($this->rateLimitDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->rateLimitDir);

        parent::tearDown();
    }

    /**
     * The project root. Override if your tests live elsewhere.
     */
    protected function basePath(): string
    {
        return dirname(__DIR__, 2);
    }

    // ---- Requests ----------------------------------------------------------

    public function json(string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        [$path, $queryString] = array_pad(explode('?', $uri, 2), 2, '');
        parse_str($queryString, $query);

        $headers = array_change_key_case(
            array_replace(['content-type' => 'application/json', 'accept' => 'application/json'], $this->defaultHeaders, $headers),
        );

        // UploadedFile values (UploadedFile::fake(...)) are sent as file uploads.
        $files = array_filter($data, fn ($v) => $v instanceof UploadedFile
            || (is_array($v) && $v !== [] && array_filter($v, fn ($f) => !$f instanceof UploadedFile) === []));
        $data = array_diff_key($data, $files);

        $request = new Request($method, $path, $query, $data, $headers, $files, $this->serverVariables);
        $response = $this->app->handle($request);
        $this->app->terminate(); // run deferred work (e.g. emails) like a real request would

        return new TestResponse($response);
    }

    public function getJson(string $uri, array $headers = []): TestResponse
    {
        return $this->json('GET', $uri, [], $headers);
    }

    public function postJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->json('POST', $uri, $data, $headers);
    }

    public function putJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->json('PUT', $uri, $data, $headers);
    }

    public function patchJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->json('PATCH', $uri, $data, $headers);
    }

    public function deleteJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->json('DELETE', $uri, $data, $headers);
    }

    /**
     * Send "Authorization: Bearer $token" with the following requests.
     */
    public function withToken(?string $token): static
    {
        if ($token === null) {
            unset($this->defaultHeaders['authorization']);
        } else {
            $this->defaultHeaders['authorization'] = "Bearer $token";
        }
        return $this;
    }

    /**
     * Authenticate the following requests as $user (via a new API token).
     */
    public function actingAs(Model $user, array $abilities = ['*']): static
    {
        return $this->withToken($user->createToken('test', $abilities)->plainTextToken);
    }

    /**
     * Set $_SERVER values for the following requests, e.g. ['REMOTE_ADDR' => '203.0.113.5'].
     */
    public function withServerVariables(array $server): static
    {
        $this->serverVariables = array_replace($this->serverVariables, $server);
        return $this;
    }
}
