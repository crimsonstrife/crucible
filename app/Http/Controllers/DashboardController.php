<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Repository;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

final class DashboardController extends Controller
{
    /**
     * Show the Crucible dashboard for the authenticated user.
     */
    public function __invoke(): View
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $ownedOrganizations = $user->ownedOrganizations()
            ->orderBy('name')
            ->get();

        $membershipOrganizations = $user->organizations()
            ->with('owner')
            ->orderBy('name')
            ->get();

        /** @var \Illuminate\Support\Collection<int,Organization> $memberOrganizations */
        $memberOrganizations = $membershipOrganizations->reject(
            static fn (Organization $organization): bool => $ownedOrganizations
                ->contains('id', $organization->id)
        )->values();

        $repositories = $user->repositories()
            ->with('organization')
            ->orderBy('name')
            ->get();

        return view('dashboard', [
            'ownedOrganizations'   => $ownedOrganizations,
            'memberOrganizations'  => $memberOrganizations,
            'repositories'         => $repositories,
        ]);
    }
}
