<?php

namespace App\Services;

use App\Contracts\PrivateFileStorage;
use Illuminate\Support\Facades\Storage;

class PrivateStorage implements PrivateFileStorage
{
    public function disk(): string
    {
        $disk = config('mortgage.private_disk', 'private');
        $settings = config('filesystems.disks.'.$disk, []);
        if (! in_array($settings['driver'] ?? null, ['local', 's3'], true) || ($settings['visibility'] ?? null) !== 'private' || ($settings['serve'] ?? false)) {
            throw new \RuntimeException('Private storage requires a nonpublic local or S3-compatible adapter');
        }
        if (($settings['driver'] ?? null) === 'local' && str_starts_with($settings['root'] ?? '', public_path())) {
            throw new \RuntimeException('Original evidence cannot use public storage');
        }

        return $disk;
    }

    public function put(string $key, $stream): void
    {
        if (Storage::disk($this->disk())->exists($key)) {
            throw new \LogicException('Original already exists.');
        } if (! Storage::disk($this->disk())->put($key, $stream, ['visibility' => 'private'])) {
            throw new \RuntimeException('Private original storage write failed');
        }
    }

    public function read(string $key)
    {
        return Storage::disk($this->disk())->readStream($key);
    }
}
