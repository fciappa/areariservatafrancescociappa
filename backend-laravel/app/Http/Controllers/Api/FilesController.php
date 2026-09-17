<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FilesController extends Controller
{
    private const UPLOAD_DIRECTORY = 'admin-uploads';

    public function index()
    {
        $directory = storage_path('app/' . self::UPLOAD_DIRECTORY);

        if (!File::isDirectory($directory)) {
            return response()->json([]);
        }

        $files = collect(File::files($directory))
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'uploaded_at' => date(DATE_ATOM, $file->getMTime()),
            ])
            ->sortByDesc('uploaded_at')
            ->values();

        return response()->json($files);
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $uploadedFile = $request->file('file');
        $directory = storage_path('app/' . self::UPLOAD_DIRECTORY);
        File::ensureDirectoryExists($directory);

        $originalName = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $baseName = Str::slug($originalName) ?: 'file';
        $extension = Str::lower($uploadedFile->getClientOriginalExtension());
        $filename = Str::limit($baseName, 180, '') . '-' . bin2hex(random_bytes(6)) . ($extension !== '' ? '.' . $extension : '');
        $uploadedFile->move($directory, $filename);

        return response()->json([
            'name' => $filename,
            'size' => File::size($directory . DIRECTORY_SEPARATOR . $filename),
            'uploaded_at' => now()->toAtomString(),
        ], 201);
    }

    public function download(string $filename)
    {
        $path = $this->filePath($filename);

        if ($path === null || !File::isFile($path)) {
            return response()->json(['message' => 'File non trovato'], 404);
        }

        return response()->download($path, $filename);
    }

    public function destroy(string $filename)
    {
        $path = $this->filePath($filename);

        if ($path === null || !File::isFile($path)) {
            return response()->json(['message' => 'File non trovato'], 404);
        }

        File::delete($path);
        return response()->json(['message' => 'File eliminato']);
    }

    private function filePath(string $filename): ?string
    {
        if ($filename !== basename($filename)) {
            return null;
        }

        return storage_path('app/' . self::UPLOAD_DIRECTORY . DIRECTORY_SEPARATOR . $filename);
    }
}