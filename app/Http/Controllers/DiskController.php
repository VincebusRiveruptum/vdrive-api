<?php

namespace App\Http\Controllers;

use App\Http\Requests\Disk\StoreFileRequest;
use App\Http\Requests\Disk\StoreFolderRequest;
use App\Http\Resources\FileResource;
use App\Http\Resources\FolderResource;
use App\Models\File;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;

class DiskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $folderId = $request->input('folder');

        $folders = Folder::where('user_id', $user->id)
            ->where('parent_id', $folderId)
            ->get();

        $files = File::where('user_id', $user->id)
            ->where('folder_id', $folderId)
            ->get();

        // Calcular breadcrumbs
        $breadcrumbs = [];
        if ($folderId) {
            $current = Folder::find($folderId);
            while ($current) {
                array_unshift($breadcrumbs, [
                    'id' => $current->id,
                    'name' => $current->name
                ]);
                $current = $current->parent;
            }
        }

        // Calcular almacenamiento
        $storageUsed = File::where('user_id', $user->id)->sum('size');
        $storageLimit = 16 * 1024 * 1024 * 1024; // 16GB estático por ahora

        return response()->json([
            'folders' => FolderResource::collection($folders),
            'files' => FileResource::collection($files),
            'storage' => [
                'used' => (int) $storageUsed,
                'limit' => $storageLimit
            ],
            'breadcrumbs' => $breadcrumbs,
            'current_folder_id' => $folderId
        ]);
    }

    public function store(StoreFileRequest $request): JsonResponse
    {
        $user = $request->user();
        $upload = $request->file('file');
        
        $path = $upload->store("users/{$user->id}/files");

        $file = File::create([
            'name' => $upload->getClientOriginalName(),
            'path' => $path,
            'size' => $upload->getSize(),
            'mime_type' => $upload->getMimeType(),
            'user_id' => $user->id,
            'folder_id' => $request->input('folder_id'),
        ]);

        return response()->json([
            'message' => 'File uploaded successfully',
            'file' => new FileResource($file)
        ], 201);
    }

    public function download(File $file)
    {
        if ($file->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        return Storage::disk('local')->download($file->path, $file->name);
    }

    public function storeFolder(StoreFolderRequest $request): JsonResponse
    {
        $folder = Folder::create([
            'name' => $request->name,
            'parent_id' => $request->parent_id,
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Folder created successfully',
            'folder' => new FolderResource($folder)
        ], 201);
    }

    public function destroy(File $file): JsonResponse
    {
        if ($file->user_id !== auth()->id()) {
            abort(403);
        }

        Storage::delete($file->path);
        $file->delete();

        return response()->json([
            'message' => 'File deleted successfully'
        ], 200);
    }

    public function destroyFolder(Folder $folder): JsonResponse
    {
        if ($folder->user_id !== auth()->id()) {
            abort(403);
        }

        $this->recursiveDelete($folder);

        return response()->json([
            'message' => 'Folder deleted successfully'
        ], 200);
    }

    // The rename and copy features were present in API routes. 
    // Creating basic stubs here to prevent route compilation errors.
    public function renameFile(Request $request, File $file): JsonResponse
    {
        return response()->json(['message' => 'Not implemented in this API version yet'], 501);
    }

    public function renameFolder(Request $request, Folder $folder): JsonResponse
    {
        return response()->json(['message' => 'Not implemented in this API version yet'], 501);
    }

    public function copyItem(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented in this API version yet'], 501);
    }

    private function recursiveDelete(Folder $folder)
    {
        foreach ($folder->files as $file) {
            Storage::delete($file->path);
            $file->delete();
        }

        foreach ($folder->children as $subfolder) {
            $this->recursiveDelete($subfolder);
        }

        $folder->delete();
    }
}
