<?php

namespace App\Services;

use App\Jobs\ComputeRepositoryLanguageStatsJob;
use App\Models\Repository;
use App\Support\LanguageColors;
use App\Support\LanguageDetector;
use Illuminate\Support\Carbon;

class RepositoryLanguageStatsService
{
    private const TOP_N = 6;

    public function __construct(
        private readonly NativeGitRepositoryService $git,
    ) {}

    /**
     * Return a view-ready snapshot of language stats for the repo.
     *
     * Statuses:
     *   - 'ready'   — cached stats match current default-branch HEAD and are non-empty
     *   - 'stale'   — cached stats exist but HEAD has moved; a fresh compute was dispatched
     *   - 'pending' — no cached stats yet; a compute was dispatched
     *   - 'empty'   — nothing recognizable to render (no revision, empty repo, or zero recognized bytes)
     *
     * @return array{status: string, segments: array<int, array{language: string, bytes: int, percent: float, color: string}>, updated_at: ?Carbon}
     */
    public function snapshot(Repository $repository): array
    {
        if (! $this->git->exists($repository)) {
            return $this->emptyResponse();
        }

        $currentSha = $this->git->headSha($repository);

        $cachedStats = $repository->language_stats;
        $cachedSha = $repository->language_stats_head_sha;

        if ($currentSha === null) {
            return $this->emptyResponse();
        }

        $hasCache = is_array($cachedStats) && $cachedSha !== null;
        $isFresh = $hasCache && $cachedSha === $currentSha;

        if (! $isFresh) {
            ComputeRepositoryLanguageStatsJob::dispatch($repository);
        }

        if (! $hasCache) {
            return [
                'status' => 'pending',
                'segments' => [],
                'updated_at' => null,
            ];
        }

        $segments = $this->segmentsFromTotals($cachedStats);

        if ($segments === []) {
            return $this->emptyResponse();
        }

        return [
            'status' => $isFresh ? 'ready' : 'stale',
            'segments' => $segments,
            'updated_at' => $repository->language_stats_updated_at,
        ];
    }

    /**
     * Walk the default branch, aggregate bytes per language, and persist the result.
     */
    public function recompute(Repository $repository): void
    {
        $repository = $repository->fresh() ?? $repository;

        if (! $this->git->exists($repository)) {
            return;
        }

        $sha = $this->git->headSha($repository);

        if ($sha === null) {
            $this->persist($repository, [], null);

            return;
        }

        $totals = [];

        foreach ($this->git->blobSizes($repository) as $entry) {
            $path = $entry['path'];

            if (LanguageDetector::isExcludedPath($path)) {
                continue;
            }

            $language = LanguageDetector::forPath($path);

            if ($language === null) {
                continue;
            }

            $totals[$language] = ($totals[$language] ?? 0) + $entry['size'];
        }

        $this->persist($repository, $totals, $sha);
    }

    /**
     * @param  array<string, int>  $totals
     * @return array<int, array{language: string, bytes: int, percent: float, color: string}>
     */
    private function segmentsFromTotals(array $totals): array
    {
        $totals = array_filter($totals, fn ($bytes) => (int) $bytes > 0);

        if ($totals === []) {
            return [];
        }

        arsort($totals);

        $grandTotal = array_sum($totals);

        if ($grandTotal <= 0) {
            return [];
        }

        $top = array_slice($totals, 0, self::TOP_N, true);
        $rest = array_slice($totals, self::TOP_N, null, true);

        $segments = [];

        foreach ($top as $language => $bytes) {
            $segments[] = [
                'language' => (string) $language,
                'bytes' => (int) $bytes,
                'percent' => ((int) $bytes / $grandTotal) * 100,
                'color' => LanguageColors::for((string) $language),
            ];
        }

        if ($rest !== []) {
            $otherBytes = (int) array_sum($rest);

            if ($otherBytes > 0) {
                $segments[] = [
                    'language' => 'Other',
                    'bytes' => $otherBytes,
                    'percent' => ($otherBytes / $grandTotal) * 100,
                    'color' => LanguageColors::for('Other'),
                ];
            }
        }

        return $segments;
    }

    /**
     * @param  array<string, int>  $totals
     */
    private function persist(Repository $repository, array $totals, ?string $sha): void
    {
        $repository->forceFill([
            'language_stats' => $totals,
            'language_stats_head_sha' => $sha,
            'language_stats_updated_at' => now(),
        ])->saveWithoutTouch();
    }

    private function emptyResponse(): array
    {
        return [
            'status' => 'empty',
            'segments' => [],
            'updated_at' => null,
        ];
    }
}
