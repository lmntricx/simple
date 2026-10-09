<?php

namespace Simple\Http;

use RuntimeException;

/**
 * Streams a file to the client. Use for files that need permission checks
 * (on the private "local" disk); public files are served by the web server.
 *
 *   return Storage::disk()->download('invoices/42.pdf', 'Invoice 42.pdf');
 */
class FileResponse extends Response
{
    public function __construct(private string $path, ?string $downloadName = null, bool $inline = false)
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("File [$path] is not readable.");
        }

        $headers = [
            'Content-Type' => MimeTypes::detect($path),
            'Content-Length' => (string) filesize($path),
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($downloadName !== null || !$inline) {
            $headers['Content-Disposition'] = self::disposition($inline ? 'inline' : 'attachment', $downloadName ?? basename($path));
        }

        parent::__construct('', 200, $headers);
    }

    public function content(): string
    {
        return (string) file_get_contents($this->path);
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header("$name: $value");
            }
        }

        readfile($this->path);
    }

    /**
     * attachment; filename="report.pdf"; filename*=UTF-8''r%C3%A9sum%C3%A9.pdf
     */
    private static function disposition(string $type, string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F"\\\\\/]/u', '', $name) ?: 'download';
        $ascii = preg_replace('/[^\x20-\x7E]/u', '_', $name);

        return sprintf('%s; filename="%s"; filename*=UTF-8\'\'%s', $type, $ascii, rawurlencode($name));
    }
}
