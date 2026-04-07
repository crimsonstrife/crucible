<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BranchProtectionRule;
use App\Models\Organization;
use App\Models\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchProtectionApiController extends Controller
{
    /**
     * GET /{org}/{repo}/branch-protection
     */
    public function index(Organization $organization, Repository $repository): JsonResponse
    {
        abort_unless($repository->organization_id === $organization->id, 404);

        $this->authorize('update', $repository);

        $rules = $repository->branchProtectionRules()->orderBy('pattern')->get();

        return response()->json([
            'data' => $rules->map(fn (BranchProtectionRule $r) => $this->formatRule($r)),
        ]);
    }

    /**
     * POST /{org}/{repo}/branch-protection
     */
    public function store(Request $request, Organization $organization, Repository $repository): JsonResponse
    {
        abort_unless($repository->organization_id === $organization->id, 404);

        $this->authorize('update', $repository);

        $data = $request->validate([
            'pattern'                => ['required', 'string', 'max:255'],
            'require_pull_request'   => ['boolean'],
            'required_approvals'     => ['integer', 'min:0', 'max:10'],
            'require_status_checks'  => ['boolean'],
            'required_status_checks' => ['nullable', 'array'],
            'required_status_checks.*' => ['string', 'max:100'],
            'restrict_push_to_roles' => ['nullable', 'array'],
            'restrict_push_to_roles.*' => ['string', 'in:read,triage,write,maintain,admin'],
            'allow_force_push'       => ['boolean'],
            'allow_deletion'         => ['boolean'],
        ]);

        $rule = $repository->branchProtectionRules()->create($data);

        return response()->json(['data' => $this->formatRule($rule)], 201);
    }

    /**
     * PATCH /{org}/{repo}/branch-protection/{rule}
     */
    public function update(
        Request $request,
        Organization $organization,
        Repository $repository,
        BranchProtectionRule $rule,
    ): JsonResponse {
        abort_unless($repository->organization_id === $organization->id, 404);
        abort_unless($rule->repository_id === $repository->id, 404);

        $this->authorize('update', $repository);

        $data = $request->validate([
            'pattern'                => ['sometimes', 'string', 'max:255'],
            'require_pull_request'   => ['sometimes', 'boolean'],
            'required_approvals'     => ['sometimes', 'integer', 'min:0', 'max:10'],
            'require_status_checks'  => ['sometimes', 'boolean'],
            'required_status_checks' => ['sometimes', 'nullable', 'array'],
            'required_status_checks.*' => ['string', 'max:100'],
            'restrict_push_to_roles' => ['sometimes', 'nullable', 'array'],
            'restrict_push_to_roles.*' => ['string', 'in:read,triage,write,maintain,admin'],
            'allow_force_push'       => ['sometimes', 'boolean'],
            'allow_deletion'         => ['sometimes', 'boolean'],
        ]);

        $rule->update($data);

        return response()->json(['data' => $this->formatRule($rule->fresh())]);
    }

    /**
     * DELETE /{org}/{repo}/branch-protection/{rule}
     */
    public function destroy(
        Organization $organization,
        Repository $repository,
        BranchProtectionRule $rule,
    ): JsonResponse {
        abort_unless($repository->organization_id === $organization->id, 404);
        abort_unless($rule->repository_id === $repository->id, 404);

        $this->authorize('update', $repository);

        $rule->delete();

        return response()->json(null, 204);
    }

    private function formatRule(BranchProtectionRule $rule): array
    {
        return [
            'id'                     => $rule->id,
            'pattern'                => $rule->pattern,
            'require_pull_request'   => $rule->require_pull_request,
            'required_approvals'     => $rule->required_approvals,
            'require_status_checks'  => $rule->require_status_checks,
            'required_status_checks' => $rule->required_status_checks ?? [],
            'restrict_push_to_roles' => $rule->restrict_push_to_roles ?? [],
            'allow_force_push'       => $rule->allow_force_push,
            'allow_deletion'         => $rule->allow_deletion,
        ];
    }
}
