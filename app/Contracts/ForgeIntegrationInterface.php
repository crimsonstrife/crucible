<?php

namespace App\Contracts;

interface ForgeIntegrationInterface
{
    public function isConfigured(): bool;

    public function getProjects(): array;

    public function getProject(string $id): ?array;
}
