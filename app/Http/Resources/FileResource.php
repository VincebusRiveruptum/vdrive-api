<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => 'file',
            'mimetype' => $this->mimetype,
            'size' => $this->size,
            'formatted_size' => $this->formatBytes($this->size),
            'folder_id' => $this->folder_id,
            'path' => $this->path,
            'url' => route('api.disk.download', $this->id),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->diffForHumans(),
        ];
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
