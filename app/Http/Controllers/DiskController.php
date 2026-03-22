<?php

namespace App\Http\Controllers;

use App\Http\Requests\Disk\StoreFileRequest;
use App\Http\Requests\Disk\StoreFolderRequest;
use App\Http\Requests\Disk\RenameItemRequest;
use App\Http\Requests\Disk\CopyItemRequest;
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

    public function renameFile(RenameItemRequest $request, File $file): JsonResponse
    {
        if ($file->user_id !== auth()->id()) abort(403);
        $file->update(['name' => $request->name]);
        return response()->json(['message' => 'Archivo renombrado ok', 'file' => new FileResource($file)]);
    }

    public function renameFolder(RenameItemRequest $request, Folder $folder): JsonResponse
    {
        if ($folder->user_id !== auth()->id()) abort(403);
        $folder->update(['name' => $request->name]);
        return response()->json(['message' => 'Carpeta renombrada', 'folder' => new FolderResource($folder)]);
    }

    public function copyItem(CopyItemRequest $request): JsonResponse
    {
        $userId = auth()->id();
        $targetFolderId = $request->input('destination_folder_id');

        if ($targetFolderId) {
            $dest = Folder::findOrFail($targetFolderId);
            if ($dest->user_id !== $userId) abort(403);
        }

        if ($request->input('type') === 'file') {
            $file = File::findOrFail($request->input('item_id'));
            if ($file->user_id !== $userId) abort(403);

            $newPath = 'users/' . $userId . '/files/' . \Illuminate\Support\Str::uuid() . '.' . pathinfo($file->path, PATHINFO_EXTENSION);
            Storage::copy($file->path, $newPath);

            $newFile = $file->replicate();
            $newFile->path = $newPath;
            $newFile->folder_id = $targetFolderId;
            $newFile->name = $newFile->name . ' - copia';
            $newFile->save();

            return response()->json(['message' => 'Archivo copiado', 'item' => new FileResource($newFile)]);
        } else {
            $folder = Folder::findOrFail($request->input('item_id'));
            if ($folder->user_id !== $userId) abort(403);
            
            if ($this->isChildOf($targetFolderId, $folder->id)) {
                return response()->json(['message' => 'Carpeta destino inválida'], 422);
            }

            $newFolder = $this->recursiveCopy($folder, $targetFolderId, $userId);
            $newFolder->update(['name' => $folder->name . ' - copia']);

            return response()->json(['message' => 'Carpeta copiada completada', 'item' => new FolderResource($newFolder)]);
        }
    }

    private function isChildOf($targetId, $folderId) {
        if (!$targetId) return false;
        if ($targetId == $folderId) return true;
        
        $current = Folder::find($targetId);
        while ($current) {
            if ($current->parent_id == $folderId) return true;
            $current = $current->parent;
        }
        return false;
    }

    private function recursiveCopy(Folder $folder, $destinationId, $userId) {
        $newFolder = $folder->replicate();
        $newFolder->parent_id = $destinationId;
        $newFolder->save();

        foreach ($folder->files as $file) {
            $newPath = 'users/' . $userId . '/files/' . \Illuminate\Support\Str::uuid() . '.' . pathinfo($file->path, PATHINFO_EXTENSION);
            if (Storage::exists($file->path)) {
                Storage::copy($file->path, $newPath);
                $newFile = $file->replicate();
                $newFile->path = $newPath;
                $newFile->folder_id = $newFolder->id;
                $newFile->save();
            }
        }

        foreach ($folder->children as $subfolder) {
            $this->recursiveCopy($subfolder, $newFolder->id, $userId);
        }

        return $newFolder;
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
