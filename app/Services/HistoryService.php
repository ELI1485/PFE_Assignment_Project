<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class HistoryService
{
    protected string $basePath = 'history';

    /**
     * Save a snapshot to the local filesystem.
     */
    public function save(string $type, array $data): object
    {
        $now = now();
        $id = $now->format('YmdHis') . '_' . substr((string) $now->microsecond, 0, 6);
        $directory = "{$this->basePath}/{$type}";
        
        // Ensure directory exists
        if (!Storage::exists($directory)) {
            Storage::makeDirectory($directory);
        }

        $filename = "{$directory}/{$id}.json";
        
        $snapshot = [
            'id' => $id,
            'label' => $data['label'],
            'data' => $data['data'],
            'count' => $data['count'] ?? 0,
            'config' => $data['config'] ?? null,
            'created_at' => now()->toDateTimeString(),
        ];

        Storage::put($filename, json_encode($snapshot, JSON_PRETTY_PRINT));

        return (object) $this->formatSnapshot($snapshot, $type);
    }

    /**
     * Get all snapshots of a specific type.
     */
    public function all(string $type): Collection
    {
        $directory = "{$this->basePath}/{$type}";
        
        if (!Storage::exists($directory)) {
            return collect();
        }

        $files = Storage::files($directory);
        
        return collect($files)
            ->filter(fn($file) => str_ends_with($file, '.json'))
            ->map(function ($file) use ($type) {
                $content = json_decode(Storage::get($file), true);
                if (!$content) return null;
                return (object) $this->formatSnapshot($content, $type);
            })
            ->filter()
            ->sortByDesc('id')
            ->values();
    }

    /**
     * Find a snapshot by its ID.
     */
    public function find(string $type, string $id): ?object
    {
        $filename = "{$this->basePath}/{$type}/{$id}.json";
        
        if (!Storage::exists($filename)) {
            return null;
        }

        $content = json_decode(Storage::get($filename), true);
        if (!$content) return null;
        
        return (object) $this->formatSnapshot($content, $type);
    }

    /**
     * Get the latest snapshot of a type.
     */
    public function latest(string $type): ?object
    {
        return $this->all($type)->first();
    }

    /**
     * Format the snapshot array into an object that mimics Eloquent.
     */
    protected function formatSnapshot(array $snapshot, string $type): array
    {
        // Add compatibility fields for views
        $snapshot['created_at'] = Carbon::parse($snapshot['created_at']);
        $snapshot['config'] = $snapshot['config'] ?? null;

        if ($type === 'affectation') {
            $snapshot['etudiants_count'] = $snapshot['count'];
        } else {
            $snapshot['soutenances_count'] = $snapshot['count'];
        }

        return $snapshot;
    }
}
