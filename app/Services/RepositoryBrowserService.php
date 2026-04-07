<?php

namespace App\Services;

use App\Contracts\RepositoryDriverInterface;
use App\Drivers\NativeGitDriver;
use App\Models\Repository;
use Illuminate\Support\Str;

class RepositoryBrowserService
{
    protected array $readmeCandidates = [
        'readme',
        'readme.md',
        'readme.markdown',
        'readme.mdown',
        'readme.mkdn',
        'readme.mdx',
    ];

    protected array $markdownExtensions = [
        'md',
        'markdown',
        'mdown',
        'mkdn',
        'mkd',
        'mdx',
    ];

    protected int $previewLimit = 256000;

    protected int $imagePreviewLimit = 2097152;

    public function __construct(
        protected RepositoryDriverInterface $driver,
        protected NativeGitRepositoryService $nativeGit,
        protected LfsService $lfsService,
        protected FileLockService $fileLockService,
    ) {}

    public function snapshot(
        Repository $repository,
        ?string $requestedRef = null,
        ?string $requestedPath = null,
        ?string $requestedPreview = null,
    ): array {
        $supported = $this->driver instanceof NativeGitDriver;
        $defaultRef = $this->driver->defaultBranch($repository);
        $ref = $this->resolveRef($repository, $requestedRef, $defaultRef);
        $path = $this->normalizePath($requestedPath);

        if (! $supported || ! $this->driver->exists($repository)) {
            return $this->emptySnapshot($supported, $defaultRef);
        }

        if (! $this->nativeGit->hasRevision($repository, $ref)) {
            return $this->emptySnapshot(true, $ref);
        }

        $tree = $this->nativeGit->recursiveTree($repository, $ref);
        $currentNode = $path === '' ? null : $this->findNodeByPath($tree, $path);
        $pathExists = $path === '' || $currentNode !== null;

        if (! $pathExists) {
            $path = '';
            $currentNode = null;
        }

        $isFile = $currentNode !== null && $currentNode['type'] === 'blob';
        $listingPath = '';
        $entries = $currentNode['children'] ?? $tree;

        if ($isFile) {
            $listingPath = Str::contains($path, '/')
                ? Str::beforeLast($path, '/')
                : '';

            $parentNode = $listingPath === ''
                ? null
                : $this->findNodeByPath($tree, $listingPath);

            $entries = $parentNode['children'] ?? $tree;
        } elseif ($currentNode !== null) {
            $listingPath = $currentNode['path'];
        }

        $lfsTrackedPaths = $this->nativeGit->lfsTrackedPaths(
            $repository,
            collect($entries)
                ->where('type', 'blob')
                ->pluck('path')
                ->when($isFile, fn ($paths) => $paths->push($currentNode['path']))
                ->unique()
                ->values()
                ->all(),
            $ref,
        );

        // Build a map of locked file paths -> lock owner display names.
        $lockedPaths = $repository->fileLocks()
            ->with('lockedBy')
            ->get()
            ->filter(fn (\App\Models\FileLock $lock) => ! $lock->isExpired())
            ->keyBy('path');

        $entries = collect($entries)
            ->map(function (array $entry) use ($lfsTrackedPaths, $lockedPaths): array {
                $entry['lfs_tracked'] = $entry['type'] === 'blob'
                    ? ($lfsTrackedPaths[$entry['path']] ?? false)
                    : false;

                $lock = $lockedPaths->get($entry['path']);
                $entry['is_locked'] = $lock !== null;
                $entry['locked_by'] = $lock?->owner_display_name;

                return $entry;
            })
            ->all();

        if ($isFile) {
            $currentNode['lfs_tracked'] = $lfsTrackedPaths[$currentNode['path']] ?? false;
        }

        $readmeNode = $isFile ? null : collect($entries)->first(
            fn (array $node) => $node['type'] === 'blob'
                && in_array(strtolower($node['name']), $this->readmeCandidates, true)
        );

        return [
            'supported' => true,
            'has_revision' => true,
            'ref' => $ref,
            'current_path' => $path,
            'listing_path' => $listingPath,
            'path_exists' => $pathExists,
            'is_file' => $isFile,
            'entries' => $entries,
            'breadcrumbs' => $this->breadcrumbs($path),
            'selected_file' => $isFile ? $this->fileSnapshot($repository, $ref, $currentNode, $requestedPreview) : null,
            'readme_path' => $readmeNode['path'] ?? null,
            'readme_html' => $readmeNode ? $this->renderMarkdownFile($repository, $ref, $readmeNode) : null,
        ];
    }

    protected function emptySnapshot(bool $supported, string $ref): array
    {
        return [
            'supported' => $supported,
            'has_revision' => false,
            'ref' => $ref,
            'current_path' => '',
            'listing_path' => '',
            'path_exists' => true,
            'is_file' => false,
            'entries' => [],
            'breadcrumbs' => $this->breadcrumbs(''),
            'selected_file' => null,
            'readme_path' => null,
            'readme_html' => null,
        ];
    }

    protected function resolveRef(Repository $repository, ?string $requestedRef, string $defaultRef): string
    {
        $requestedRef = trim((string) $requestedRef);

        if ($requestedRef === '') {
            return $defaultRef;
        }

        if (! $this->driver->exists($repository)) {
            return $defaultRef;
        }

        return in_array($requestedRef, $this->driver->branches($repository), true)
            ? $requestedRef
            : $defaultRef;
    }

    protected function normalizePath(?string $requestedPath): string
    {
        $path = trim((string) $requestedPath);
        $path = str_replace('\\', '/', $path);
        $path = trim($path, '/');

        if ($path === '' || $path === '.') {
            return '';
        }

        return collect(explode('/', $path))
            ->reject(fn (string $segment) => $segment === '' || $segment === '.')
            ->implode('/');
    }

    protected function findNodeByPath(array $nodes, string $path): ?array
    {
        if ($path === '') {
            return null;
        }

        $segments = explode('/', $path);
        $currentNodes = $nodes;
        $currentNode = null;

        foreach ($segments as $segment) {
            $currentNode = collect($currentNodes)->firstWhere('name', $segment);

            if ($currentNode === null) {
                return null;
            }

            $currentNodes = $currentNode['children'] ?? [];
        }

        return $currentNode;
    }

    protected function breadcrumbs(string $path): array
    {
        $breadcrumbs = [
            [
                'label' => 'root',
                'path' => '',
            ],
        ];

        $currentPath = '';

        foreach (array_filter(explode('/', $path)) as $segment) {
            $currentPath = ltrim($currentPath.'/'.$segment, '/');
            $breadcrumbs[] = [
                'label' => $segment,
                'path' => $currentPath,
            ];
        }

        return $breadcrumbs;
    }

    protected function fileSnapshot(
        Repository $repository,
        string $ref,
        array $node,
        ?string $requestedPreview = null,
    ): array {
        $gitContents = $this->nativeGit->fileContents($repository, $node['path'], $ref) ?? '';
        $payload = $this->resolvedFilePayload($repository, $node, $gitContents);
        $contents = $payload['contents'];
        $mimeType = $payload['mime_type'] ?? $this->detectMimeType($contents, $node['path']);
        $isBinary = str_contains($contents, "\0");
        $isMarkdown = $this->isMarkdownPath($node['path']);
        $isImage = $this->isImageMimeType($mimeType);
        $sourceTooLarge = ! $isBinary && strlen($contents) > $this->previewLimit;
        $imageTooLarge = $isImage && strlen($contents) > $this->imagePreviewLimit;
        $supportsSourcePreview = ! $isBinary && ! $sourceTooLarge;
        $canRenderMarkdown = $isMarkdown && $supportsSourcePreview && ! $payload['lfs_object_missing'];
        $canRenderImage = $isImage && ! $imageTooLarge && ! $payload['lfs_object_missing'];
        $previewMode = $this->resolvePreviewMode($requestedPreview, $canRenderMarkdown, $canRenderImage);

        return [
            'name' => $node['name'],
            'path' => $node['path'],
            'preview_mode' => $previewMode,
            'source_contents' => $supportsSourcePreview ? $contents : null,
            'rendered_html' => $previewMode === 'rendered' && $canRenderMarkdown
                ? $this->renderMarkdownContents($contents)
                : null,
            'image_data_uri' => $previewMode === 'rendered' && $canRenderImage
                ? $this->dataUri($contents, $mimeType)
                : null,
            'mime_type' => $mimeType,
            'supports_rendered_preview' => $canRenderMarkdown || $canRenderImage,
            'supports_source_preview' => $supportsSourcePreview,
            'can_toggle_preview' => $canRenderMarkdown,
            'is_markdown' => $isMarkdown,
            'is_image' => $isImage,
            'is_binary' => $isBinary,
            'is_too_large' => $sourceTooLarge,
            'image_too_large' => $imageTooLarge,
            'lfs_tracked' => (bool) ($node['lfs_tracked'] ?? false),
            'lfs_pointer_oid' => $payload['lfs_pointer_oid'],
            'lfs_object_missing' => $payload['lfs_object_missing'],
        ];
    }

    protected function renderMarkdownFile(Repository $repository, string $ref, array $node): ?string
    {
        $gitContents = $this->nativeGit->fileContents($repository, $node['path'], $ref);

        if ($gitContents === null) {
            return null;
        }

        $payload = $this->resolvedFilePayload($repository, $node, $gitContents);
        $contents = $payload['contents'];

        if ($payload['lfs_object_missing'] || str_contains($contents, "\0") || strlen($contents) > $this->previewLimit) {
            return null;
        }

        return $this->renderMarkdownContents($contents);
    }

    protected function renderMarkdownContents(string $contents): string
    {
        return (string) Str::markdown($contents, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    protected function resolvedFilePayload(Repository $repository, array $node, string $gitContents): array
    {
        $payload = [
            'contents' => $gitContents,
            'mime_type' => null,
            'lfs_pointer_oid' => null,
            'lfs_object_missing' => false,
        ];

        if (! ($node['lfs_tracked'] ?? false)) {
            return $payload;
        }

        $pointer = $this->parseLfsPointer($gitContents);

        if ($pointer === null) {
            return $payload;
        }

        $payload['lfs_pointer_oid'] = $pointer['oid'];

        $download = $this->lfsService->download($repository, $pointer['oid']);

        if ($download === null) {
            $payload['lfs_object_missing'] = true;

            return $payload;
        }

        $payload['mime_type'] = $download['object']->mime_type;

        if (! is_resource($download['stream'])) {
            return $payload;
        }

        $contents = stream_get_contents($download['stream']);
        fclose($download['stream']);

        if ($contents !== false) {
            $payload['contents'] = $contents;
        }

        return $payload;
    }

    protected function parseLfsPointer(string $contents): ?array
    {
        if (! preg_match(
            '/\\Aversion https:\\/\\/git-lfs\\.github\\.com\\/spec\\/v1\\s+oid sha256:(?<oid>[a-f0-9]{64})\\s+size (?<size>\\d+)\\s*\\z/i',
            trim($contents),
            $matches,
        )) {
            return null;
        }

        return [
            'oid' => strtolower($matches['oid']),
            'size' => (int) $matches['size'],
        ];
    }

    protected function resolvePreviewMode(?string $requestedPreview, bool $canRenderMarkdown, bool $canRenderImage): string
    {
        if ($canRenderImage) {
            return 'rendered';
        }

        if ($canRenderMarkdown) {
            return $requestedPreview === 'source' ? 'source' : 'rendered';
        }

        return 'source';
    }

    protected function isMarkdownPath(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $this->markdownExtensions, true);
    }

    protected function isImageMimeType(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/');
    }

    protected function detectMimeType(string $contents, string $path): string
    {
        if ($contents !== '') {
            $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);

            if (is_string($mimeType) && $mimeType !== '') {
                return $mimeType;
            }
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'gif' => 'image/gif',
            'jpeg', 'jpg' => 'image/jpeg',
            'md', 'markdown', 'mdown', 'mkdn', 'mkd', 'mdx' => 'text/markdown',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }

    protected function dataUri(string $contents, string $mimeType): string
    {
        return sprintf('data:%s;base64,%s', $mimeType, base64_encode($contents));
    }
}
