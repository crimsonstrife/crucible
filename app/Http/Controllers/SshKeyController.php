<?php

namespace App\Http\Controllers;

use App\Http\Requests\SshKeys\StoreSshKeyRequest;
use App\Models\Repository;
use App\Models\SshKey;
use App\Services\SshKeyService;

class SshKeyController extends Controller
{
    public function __construct(protected SshKeyService $service) {}

    public function index()
    {
        return view('ssh-keys.index');
    }

    public function store(StoreSshKeyRequest $request)
    {
        $deployRepository = $request->deploy_repo_id
            ? Repository::findOrFail($request->deploy_repo_id)
            : null;

        if ($deployRepository) {
            $this->authorize('update', $deployRepository);
        }

        $this->service->add(
            auth()->user(),
            $request->title,
            $request->public_key,
            (bool) $request->is_deploy_key,
            $deployRepository,
        );

        return back()->with('success', 'SSH key added.');
    }

    public function destroy(SshKey $sshKey)
    {
        $this->authorize('delete', $sshKey);
        $this->service->remove(auth()->user(), $sshKey);

        return back()->with('success', 'SSH key removed.');
    }
}
