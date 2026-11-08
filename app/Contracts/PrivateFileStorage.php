<?php

namespace App\Contracts;

interface PrivateFileStorage
{
    public function put(string $key, $stream): void;

    public function read(string $key);

    public function disk(): string;
}
