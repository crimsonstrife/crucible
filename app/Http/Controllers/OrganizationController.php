<?php

namespace App\Http\Controllers;

use App\Http\Requests\Organizations\CreateOrganizationRequest;
use App\Models\Organization;
use App\Services\OrganizationService;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function __construct(protected OrganizationService $service) {}

    public function index()
    {
        return view('organizations.index');
    }

    public function create()
    {
        return view('organizations.create');
    }

    public function store(CreateOrganizationRequest $request)
    {
        $org = $this->service->create(auth()->user(), $request->validated());
        return redirect()->route('organizations.show', $org)->with('success', 'Organization created.');
    }

    public function show(Organization $organization)
    {
        $this->authorize('view', $organization);
        $organization->load('members');
        return view('organizations.show', compact('organization'));
    }

    public function edit(Organization $organization)
    {
        $this->authorize('update', $organization);
        return view('organizations.edit', compact('organization'));
    }

    public function update(Request $request, Organization $organization)
    {
        $this->authorize('update', $organization);
        $organization->update($request->only(['name', 'description', 'website_url', 'forge_org_id', 'forge_org_slug']));
        return redirect()->route('organizations.show', $organization)->with('success', 'Organization updated.');
    }

    public function destroy(Organization $organization)
    {
        $this->authorize('delete', $organization);
        $organization->delete();
        return redirect()->route('organizations.index')->with('success', 'Organization deleted.');
    }
}
