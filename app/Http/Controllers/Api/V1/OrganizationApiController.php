<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationApiController extends Controller
{
    public function __construct(protected OrganizationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $orgs = $request->user()
            ->organizations()
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $orgs]);
    }

    public function show(Request $request, Organization $organization): JsonResponse
    {
        abort_unless($request->user()->can('view', $organization), 403);

        return response()->json(['data' => $organization->load('members')]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'website_url' => ['nullable', 'url', 'max:255'],
        ]);

        $org = $this->service->create($request->user(), $data);

        return response()->json(['data' => $org], 201);
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        abort_unless($request->user()->can('update', $organization), 403);

        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'website_url' => ['nullable', 'url', 'max:255'],
        ]);

        $organization->update($data);

        return response()->json(['data' => $organization->fresh()]);
    }

    public function destroy(Request $request, Organization $organization): JsonResponse
    {
        abort_unless($request->user()->can('delete', $organization), 403);

        $organization->delete();

        return response()->json(null, 204);
    }

    // ── Nested repositories ────────────────────────────────────────────────────

    public function repositories(Request $request, Organization $organization): JsonResponse
    {
        abort_unless($request->user()->can('view', $organization), 403);

        $repos = $organization->repositories()
            ->when(! $request->user()->hasPermissionTo('is-super-admin', 'web'), function ($q) use ($request, $organization) {
                $q->where(function ($inner) use ($request, $organization) {
                    $inner->where('visibility', 'public')
                          ->orWhere('owner_id', $request->user()->id)
                          ->orWhereHas('collaborators', fn ($c) => $c->where('users.id', $request->user()->id));
                });
            })
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $repos]);
    }
}
