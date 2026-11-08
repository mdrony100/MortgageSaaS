<?php

namespace App\Services;

use App\Contracts\PrivateFileStorage;
use Illuminate\Support\Facades\Storage;

class PrivateStorage implements PrivateFileStorage
{
    public function disk(): string
    {
        return config('mortgage.private_disk', 'private');
    }

    public function put(string $key, $stream): void
    {
        if (Storage::disk($this->disk())->exists($key)) {
            throw new \LogicException('Original already exists.');
        } Storage::disk($this->disk())->put($key, $stream, ['visibility' => 'private']);
    }

    public function read(string $key)
    {
        return Storage::disk($this->disk())->readStream($key);
    }
}
