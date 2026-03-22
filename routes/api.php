<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DiskController;
use App\Http\Controllers\ProfileController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/disk', [DiskController::class, 'index'])->name('api.disk.index');
    Route::post('/disk/upload', [DiskController::class, 'store'])->name('api.disk.upload');
    Route::get('/disk/download/{file}', [DiskController::class, 'download'])->name('api.disk.download');
    Route::post('/disk/folder', [DiskController::class, 'storeFolder'])->name('api.disk.folder.store');
    Route::delete('/disk/file/{file}', [DiskController::class, 'destroy'])->name('api.disk.file.destroy');
    Route::delete('/disk/folder/{folder}', [DiskController::class, 'destroyFolder'])->name('api.disk.folder.destroy');
    
    Route::put('/disk/file/{file}/rename', [DiskController::class, 'renameFile'])->name('api.disk.file.rename');
    Route::put('/disk/folder/{folder}/rename', [DiskController::class, 'renameFolder'])->name('api.disk.folder.rename');
    Route::post('/disk/copy', [DiskController::class, 'copyItem'])->name('api.disk.copy');
    
});

require __DIR__.'/auth.php';
