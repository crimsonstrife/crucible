<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Repository;
use App\Services\LfsService;
use App\Support\GameEngineTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LfsStorageController extends Controller
{
    public function __construct(
        protected LfsService $lfsService,
    ) {}

    /**
     * GET /{organization}/repositories/{repository}/lfs
     *
     * LFS storage dashboard showing usage stats, largest objects, and policies.
     */
    public function index(Request $request, Organization $organization, Repository $repository): View
    {
        $this->authorize('view', $repository);

        $stats = $this->lfsService->storageStats($repository);
        $largestObjects = $this->lfsService->largestObjects($repository);
        $policies = $repository->lfsPolicies()->orderBy('pattern')->get();
        $lockPolicies = $repository->lockPolicies()->orderBy('pattern')->get();
        $templates = GameEngineTemplates::available();

        return view('repositories.lfs.dashboard', compact(
            'organization',
            'repository',
            'stats',
            'largestObjects',
            'policies',
            'lockPolicies',
            'templates',
        ));
    }

    /**
     * POST /{organization}/repositories/{repository}/lfs/apply-template
     *
     * Apply an engine LFS policy template to the repository.
     */
    public function applyTemplate(Request $request, Organization $organization, Repository $repository): RedirectResponse
    {
        $this->authorize('update', $repository);

        $validated = $request->validate([
            'template' => ['required', 'string', 'in:'.implode(',', GameEngineTemplates::available())],
        ]);

        $result = $this->lfsService->applyTemplate($repository, $validated['template']);
        $createdCount = count($result['created']);

        return redirect()
            ->route('repositories.lfs.dashboard', [$organization, $repository])
            ->with('success', "Applied \"{$validated['template']}\" template: {$createdCount} created, {$result['skipped']} skipped.");
    }
}
