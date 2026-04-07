<?php

namespace App\Contracts;

interface LfsBackendInterface
{
    public function exists(string $oid): bool;

    public function store(string $oid, int $size, mixed $stream): void;

    public function delete(string $oid): void;

    public function size(string $oid): int;

    public function readStream(string $oid): mixed;
}
