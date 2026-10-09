<?php

namespace Simple\Http;

use Simple\Validation\Validator;

class Request
{
    /** Values set by middleware, e.g. the authenticated user. */
    protected array $attributes = [];

    public function __construct(
        protected string $method,
        protected string $path,
        protected array $query = [],
        protected array $body = [],
        protected array $headers = [],
        protected array $files = [],
        protected array $server = [],
    ) {
        $this->method = strtoupper($method);
        $this->path = '/' . trim($path, '/');
    }

    public static function capture(): static
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headers[strtolower(str_replace('_', '-', $key))] = $value;
            }
        }

        $body = $_POST;
        if (str_contains($headers['content-type'] ?? '', 'application/json')) {
            $decoded = json_decode(file_get_contents('php://input') ?: '', true);
            $body = is_array($decoded) ? $decoded : [];
        } elseif (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['PUT', 'PATCH', 'DELETE'], true) && $body === []) {
            parse_str(file_get_contents('php://input') ?: '', $body);
        }

        return new static(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            static::resolvePath($_SERVER),
            $_GET,
            $body,
            $headers,
            $_FILES,
            $_SERVER,
        );
    }

    /**
     * Request path relative to where the app is installed, so the app works
     * from a sub-folder (e.g. https://host/my-app/users => /users).
     */
    protected static function resolvePath(array $server): string
    {
        $path = rawurldecode(parse_url($server['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $scriptDir = rtrim(str_replace('\\', '/', dirname($server['SCRIPT_NAME'] ?? '')), '/');

        // When the root .htaccess forwards into public/, the URL won't contain "/public".
        $candidates = [$scriptDir];
        if (str_ends_with($scriptDir, '/public')) {
            $candidates[] = substr($scriptDir, 0, -strlen('/public'));
        }

        foreach ($candidates as $base) {
            if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
                return substr($path, strlen($base)) ?: '/';
            }
        }

        return $path;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    /**
     * Body + query string merged (body wins). Pass a key to read one value.
     */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        $all = $this->all();
        return $key === null ? $all : ($all[$key] ?? $default);
    }

    public function all(): array
    {
        return array_replace($this->query, $this->body);
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    public function except(array $keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    /**
     * Add or overwrite input values, e.g. to normalise them before validation.
     */
    public function merge(array $values): void
    {
        $this->body = array_replace($this->body, $values);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->query : ($this->query[$key] ?? $default);
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('authorization', '');
        return str_starts_with($header, 'Bearer ') ? trim(substr($header, 7)) : null;
    }

    /**
     * An uploaded file, or null if none was sent. For inputs like "photos[]",
     * returns an array of files.
     *
     * @return UploadedFile|UploadedFile[]|null
     */
    public function file(string $key): UploadedFile|array|null
    {
        return $this->allFiles()[$key] ?? null;
    }

    public function hasFile(string $key): bool
    {
        return $this->file($key) !== null;
    }

    /**
     * Every uploaded file, keyed by input name. Empty file inputs are left out.
     *
     * @return array<string, UploadedFile|UploadedFile[]>
     */
    public function allFiles(): array
    {
        $files = [];

        foreach ($this->files as $key => $file) {
            if ($file instanceof UploadedFile) {
                $files[$key] = $file;
            } elseif (is_array($file) && $file !== [] && array_filter($file, fn ($f) => !$f instanceof UploadedFile) === []) {
                $files[$key] = array_values($file);
            } elseif (is_array($file) && is_array($file['name'] ?? null)) {
                // name="photos[]": $_FILES['photos'] = ['name' => [...], 'tmp_name' => [...], ...]
                $list = [];
                foreach (array_keys($file['name']) as $i) {
                    if (($file['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                        $list[] = UploadedFile::fromArray(array_map(fn ($field) => $field[$i] ?? null, $file));
                    }
                }
                if ($list !== []) {
                    $files[$key] = $list;
                }
            } elseif (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $files[$key] = UploadedFile::fromArray($file);
            }
        }

        return $files;
    }

    /**
     * The client's IP address.
     *
     * Behind a load balancer / CDN, REMOTE_ADDR is the proxy, not the client.
     * If REMOTE_ADDR is a trusted proxy (config app.trusted_proxies), the
     * X-Forwarded-For chain is read from the right, skipping trusted proxies,
     * and the first untrusted address is the client. Untrusted senders can't
     * spoof their IP, because their X-Forwarded-For is ignored.
     */
    public function ip(): ?string
    {
        $remote = $this->server['REMOTE_ADDR'] ?? null;

        if ($remote === null || !TrustedProxies::isTrusted($remote)) {
            return $remote;
        }

        $forwarded = array_filter(array_map('trim', explode(',', (string) $this->header('x-forwarded-for', ''))));

        foreach (array_reverse($forwarded) as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                break; // Malformed chain: stop rather than trust anything further left.
            }
            if (!TrustedProxies::isTrusted($ip)) {
                return $ip;
            }
            $remote = $ip;
        }

        return $remote;
    }

    /**
     * Whether the client connected over HTTPS (honours X-Forwarded-Proto from trusted proxies).
     */
    public function isSecure(): bool
    {
        $remote = $this->server['REMOTE_ADDR'] ?? null;

        if ($remote !== null && TrustedProxies::isTrusted($remote) && ($proto = $this->header('x-forwarded-proto')) !== null) {
            return strtolower(trim(explode(',', $proto)[0])) === 'https';
        }

        $https = $this->server['HTTPS'] ?? '';

        return $https !== '' && strtolower((string) $https) !== 'off';
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    /**
     * The authenticated user (set by the "auth" middleware), or null.
     */
    public function user(): ?\Simple\Database\Model
    {
        return $this->attributes['user'] ?? null;
    }

    public function setUser(?\Simple\Database\Model $user): void
    {
        $this->attributes['user'] = $user;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Validate input and return only the validated fields.
     * Throws a 422 ValidationException on failure.
     */
    public function validate(array $rules): array
    {
        return Validator::make(array_replace($this->all(), $this->allFiles()), $rules)->validate();
    }
}
