<?php

namespace Simple\Http;

use RuntimeException;
use Simple\Storage\Storage;

/**
 * A file from a multipart upload: $request->file('avatar').
 *
 * Never trust originalName() or clientMimeType(); they come from the client.
 * mimeType() and extension() are detected from the file's actual contents.
 */
class UploadedFile
{
    private ?string $detectedMime = null;

    public function __construct(
        private string $path,
        private string $originalName,
        private ?string $clientMimeType = null,
        private int $error = UPLOAD_ERR_OK,
        private bool $test = false,
    ) {
    }

    /**
     * From one $_FILES entry.
     */
    public static function fromArray(array $file): self
    {
        return new self(
            (string) ($file['tmp_name'] ?? ''),
            (string) ($file['name'] ?? ''),
            isset($file['type']) ? (string) $file['type'] : null,
            (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE),
        );
    }

    /**
     * A fake upload for tests, with the given contents.
     */
    public static function fake(string $name, string $contents = '', ?string $clientMimeType = null): self
    {
        $path = tempnam(sys_get_temp_dir(), 'upload_');
        file_put_contents($path, $contents);

        return new self($path, $name, $clientMimeType, UPLOAD_ERR_OK, test: true);
    }

    /**
     * A fake, valid 1x1 PNG image for tests.
     */
    public static function fakeImage(string $name = 'image.png'): self
    {
        return self::fake($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='
        ), 'image/png');
    }

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && ($this->test || is_uploaded_file($this->path));
    }

    public function error(): int
    {
        return $this->error;
    }

    public function errorMessage(): string
    {
        return match ($this->error) {
            UPLOAD_ERR_OK => $this->isValid() ? '' : 'The file was not uploaded via HTTP POST.',
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is larger than the server allows (upload_max_filesize).',
            UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'The server could not save the upload.',
            default => 'The upload failed.',
        };
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * The client's file name, reduced to a safe base name. Only for display.
     */
    public function originalName(): string
    {
        $name = basename(str_replace('\\', '/', $this->originalName));
        return preg_replace('/[\x00-\x1F\x7F]/', '', $name);
    }

    public function clientExtension(): string
    {
        return strtolower(pathinfo($this->originalName(), PATHINFO_EXTENSION));
    }

    public function clientMimeType(): ?string
    {
        return $this->clientMimeType;
    }

    /**
     * The MIME type detected from the file's contents.
     */
    public function mimeType(): string
    {
        return $this->detectedMime ??= MimeTypes::detect($this->path);
    }

    /**
     * A safe extension for the detected type ("jpg", "pdf", ...), or null if unknown.
     */
    public function extension(): ?string
    {
        return MimeTypes::extension($this->mimeType());
    }

    public function isImage(): bool
    {
        return in_array($this->mimeType(), MimeTypes::IMAGES, true);
    }

    /** Size in bytes. */
    public function size(): int
    {
        return is_file($this->path) ? (int) filesize($this->path) : 0;
    }

    public function contents(): string
    {
        return (string) file_get_contents($this->path);
    }

    /**
     * Store under a random name in $directory on $disk; returns the stored path.
     *   $path = $request->file('avatar')->store('avatars', 'public');
     */
    public function store(string $directory = '', ?string $disk = null): string
    {
        return Storage::disk($disk)->putFile($directory, $this);
    }

    /**
     * Store under a name you choose (not the client's!).
     */
    public function storeAs(string $directory, string $name, ?string $disk = null): string
    {
        return Storage::disk($disk)->putFileAs($directory, $this, $name);
    }

    /**
     * Move the upload to an absolute path. Used by Storage.
     */
    public function moveTo(string $target): void
    {
        if (!$this->isValid()) {
            throw new RuntimeException('Cannot move an invalid upload: ' . $this->errorMessage());
        }

        $moved = $this->test ? rename($this->path, $target) : move_uploaded_file($this->path, $target);

        if (!$moved) {
            throw new RuntimeException("Could not move the uploaded file to [$target].");
        }

        $this->path = $target;
    }
}
