<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class SparseCheckoutProfile extends BaseModel
{
    use HasUuids, HasSlug;

    protected $fillable = [
        'repository_id',
        'name',
        'slug',
        'description',
        'include_paths',
        'exclude_paths',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'include_paths' => 'array',
        'exclude_paths' => 'array',
        'is_default'    => 'boolean',
        'sort_order'    => 'integer',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    /**
     * Generate the sparse-checkout file content for this profile.
     *
     * Format follows git sparse-checkout set --cone or --no-cone patterns.
     * Each include path is listed, each exclude path is prefixed with '!'.
     */
    public function toSparseCheckoutRules(): string
    {
        $lines = [
            "# Crucible sparse-checkout profile: {$this->name}",
            "# Auto-generated — do not edit manually",
            '',
        ];

        // Include paths
        foreach ($this->include_paths ?? [] as $path) {
            $lines[] = $this->normalizePath($path);
        }

        // Exclude paths (negation patterns)
        foreach ($this->exclude_paths ?? [] as $path) {
            $normalized = $this->normalizePath($path);
            // Ensure it starts with '!'
            if (! str_starts_with($normalized, '!')) {
                $normalized = '!' . $normalized;
            }
            $lines[] = $normalized;
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Generate the git clone + sparse-checkout commands for this profile.
     */
    public function toCloneCommands(string $repoUrl, ?string $branch = null): array
    {
        $branchArg = $branch ? " --branch {$branch}" : '';

        $commands = [
            "git clone --filter=blob:none --sparse{$branchArg} {$repoUrl}",
        ];

        $paths = $this->include_paths ?? [];

        if (! empty($paths)) {
            $pathsStr = implode(' ', array_map(fn ($p) => escapeshellarg($this->normalizePath($p)), $paths));
            $commands[] = "git sparse-checkout set {$pathsStr}";
        }

        return $commands;
    }

    /**
     * Estimate the percentage of the repository this profile covers.
     * Based on a provided full tree listing.
     *
     * @param  array  $allPaths  List of all file paths in the repository
     * @return float  Percentage (0-100)
     */
    public function estimateCoverage(array $allPaths): float
    {
        if (empty($allPaths)) {
            return 0.0;
        }

        $matched = 0;

        foreach ($allPaths as $path) {
            if ($this->matchesPath($path)) {
                $matched++;
            }
        }

        return round(($matched / count($allPaths)) * 100, 1);
    }

    /**
     * Check if a file path matches this profile's include/exclude rules.
     */
    public function matchesPath(string $filePath): bool
    {
        $filePath = '/' . ltrim($filePath, '/');

        // Check include paths — at least one must match
        $included = false;

        foreach ($this->include_paths ?? [] as $includePath) {
            $normalized = '/' . ltrim($includePath, '/');

            if (str_starts_with($filePath, $normalized) || fnmatch($normalized . '/*', $filePath)) {
                $included = true;
                break;
            }
        }

        if (! $included) {
            return false;
        }

        // Check exclude paths — any match removes the file
        foreach ($this->exclude_paths ?? [] as $excludePath) {
            $normalized = '/' . ltrim(ltrim($excludePath, '!'), '/');

            if (str_starts_with($filePath, $normalized) || fnmatch($normalized . '/*', $filePath)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Normalize a path for sparse-checkout format.
     */
    private function normalizePath(string $path): string
    {
        // Remove leading/trailing whitespace and normalize slashes
        $path = trim($path);
        $path = str_replace('\\', '/', $path);

        // Ensure leading slash for absolute patterns
        if (! str_starts_with($path, '/') && ! str_starts_with($path, '!')) {
            $path = '/' . $path;
        }

        return rtrim($path, '/');
    }
}
