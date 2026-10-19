<?php

declare(strict_types=1);

namespace VmEngine\FileManager\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use VmEngine\FileManager\Config\FmConfig;
use VmEngine\FileManager\Enums\FmAction;
use VmEngine\FileManager\Models\FmFile;
use VmEngine\FileManager\Services\FileManagerService;

class FileController extends Controller
{
    public function __construct(
        private readonly FileManagerService $service
    ) {}

    /**
     * Handle file upload via API.
     */
    public function upload(Request $request): JsonResponse
    {
        $uploadConfig = FmConfig::getUploadConfig();
        $maxKb = (int) ($uploadConfig['max_size_kb'] ?? 10240);
        $extensions = implode(',', $uploadConfig['allowed_extensions'] ?? []);

        $request->validate([
            'file' => "required|file|max:{$maxKb}|mimes:{$extensions}",
            'folder_path' => 'required|string',
            'sub_path' => 'nullable|string',
        ]);

        $user = $request->user();

        if (! $user || ! FmConfig::canUserDo($user, $request->input('folder_path'), FmAction::Upload)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $fmFile = $this->service->upload(
            $request->file('file'),
            $request->input('folder_path'),
            $request->input('sub_path', ''),
            $user->getAuthIdentifier()
        );

        return response()->json([
            'id' => $fmFile->id,
            'filename' => $fmFile->filename,
            'url' => $fmFile->getUrl(),
            'thumbnail_url' => $fmFile->getThumbnailUrl(),
            'size' => $fmFile->size,
            'human_size' => $fmFile->getHumanSize(),
            'mime_type' => $fmFile->mime_type,
        ]);
    }

    /**
     * Stream a file by ID (for private/protected access).
     */
    public function stream(int $id): StreamedResponse
    {
        $file = FmFile::active()->findOrFail($id);

        $user = request()->user();

        if (! $user || ! FmConfig::canUserDo($user, $file->folder_path, FmAction::Read)) {
            abort(403);
        }

        $disk = Storage::disk($file->disk);

        if (! $disk->exists($file->getStoragePath())) {
            abort(404);
        }

        // Uploads are user content: sandbox them so an SVG/HTML file can't run
        // script on the app origin, and stop browsers from sniffing other types.
        return $disk->response($file->getStoragePath(), $file->filename, [
            'Content-Type' => $file->mime_type,
            'Content-Security-Policy' => 'sandbox',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
