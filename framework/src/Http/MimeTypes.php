<?php

namespace Simple\Http;

/**
 * Maps detected MIME types to safe file extensions. Anything not listed is
 * stored as ".bin", so an upload can never be saved as .php, .html, etc.
 */
final class MimeTypes
{
    public const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        'image/bmp' => 'bmp',
        'image/heic' => 'heic',
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'application/csv' => 'csv',
        'application/json' => 'json',
        'application/zip' => 'zip',
        'application/x-zip-compressed' => 'zip',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
        'audio/mpeg' => 'mp3',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/ogg' => 'ogg',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
    ];

    /** Raster images safe to display. SVG is excluded: it can contain scripts. */
    public const IMAGES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/bmp', 'image/heic'];

    public static function extension(string $mime): ?string
    {
        return self::EXTENSIONS[strtolower($mime)] ?? null;
    }

    public static function detect(string $path): string
    {
        $mime = is_file($path) ? (new \finfo(FILEINFO_MIME_TYPE))->file($path) : false;
        return $mime ?: 'application/octet-stream';
    }
}
