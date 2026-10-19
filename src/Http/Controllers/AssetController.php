<?php

declare(strict_types=1);

namespace VmEngine\FileManager\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves vm-engine/file-manager's package-owned assets (resources/dist, plus
 * the editor bridges in resources/js) without a build step.
 *
 * Only whitelisted files are served (lookup, never path joining), with a
 * one-year immutable cache; url() appends ?v={filemtime} for cache-busting.
 */
final class AssetController
{
    private const FILES = [
        'fm.css' => 'text/css; charset=UTF-8',
        'fm.js' => 'application/javascript; charset=UTF-8',
        // Vendored pdfjs-dist 6.3.289 (legacy build, Apache-2.0 — see pdfjs-LICENSE).
        'pdf.min.mjs' => 'text/javascript; charset=UTF-8',
        'pdf.worker.min.mjs' => 'text/javascript; charset=UTF-8',
        // Vendored @phosphor-icons/web 2.1.2, regular weight (MIT — see phosphor-LICENSE).
        'phosphor.css' => 'text/css; charset=UTF-8',
        'Phosphor.woff2' => 'font/woff2',
        // Editor bridges, imported by the host as ES modules.
        'fm-tinymce.js' => 'text/javascript; charset=UTF-8',
        'fm-jodit.js' => 'text/javascript; charset=UTF-8',
    ];

    public function __invoke(string $file): BinaryFileResponse
    {
        abort_unless(array_key_exists($file, self::FILES), 404);

        return response()->file(self::path($file), [
            'Content-Type' => self::FILES[$file],
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    public static function url(string $file): string
    {
        $version = (string) (@filemtime(self::path($file)) ?: '0');

        return route('filemanager.assets', ['file' => $file, 'v' => $version]);
    }

    private static function path(string $file): string
    {
        $directory = str_starts_with($file, 'fm-') ? 'js' : 'dist';

        return dirname(__DIR__, 3).'/resources/'.$directory.'/'.$file;
    }
}
