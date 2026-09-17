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

        $extension = $uploadedFile->getClientOriginalExtension();
        $filename = Str::uuid() . ($extension !== '' ? '.' . $extension : '');
        $uploadedFile->move($directory, $filename);

        return response()->json([
            'name' => $filename,
            'size' => File::size($directory . DIRECTORY_SEPARATOR . $filename),
            'uploaded_at' => now()->toAtomString(),
        ], 201);
    }
}