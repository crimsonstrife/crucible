<?php

namespace App\Providers;

use App\Models\FileLock;
use App\Models\Organization;
use App\Models\PermissionSet;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\SshKey;
use App\Models\User;
use App\Policies\FileLockPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\PullRequestPolicy;
use App\Policies\RepositoryPolicy;
use App\Policies\SshKeyPolicy;
use DB;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Repository::class   => RepositoryPolicy::class,
        Organization::class => OrganizationPolicy::class,
        SshKey::class       => SshKeyPolicy::class,
        FileLock::class     => FileLockPolicy::class,
        PullRequest::class  => PullRequestPolicy::class,
    ];

    public function register(): void {}

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('viewApiDocs', fn (?User $user) =>
            $user && $user->hasPermissionTo('is-super-admin', 'web')
        );

        Gate::before(static function (User $user, string $ability) {
            /** @var PermissionRegistrar $reg */
            $reg  = app(PermissionRegistrar::class);
            $prev = $reg->getPermissionsTeamId();

            $reg->setPermissionsTeamId(null);
            try {
                $isSuper = $user->hasPermissionTo('is-super-admin', 'web');
            } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist) {
                $isSuper = false;
            }
            $reg->setPermissionsTeamId($prev);

            if ($isSuper) {
                return true;
            }

            // Only evaluate mutes for permission-like abilities (contain a dot).
            if (!str_contains($ability, '.')) {
                return null;
            }

            $muted = Cache::remember(
                "auth:user:{$user->getKey()}:muted-perms:v1",
                now()->addMinutes(10),
                static function () use ($user): array {
                    $userSetIds = method_exists($user, 'permissionSets')
                        ? $user->permissionSets()->pluck('permission_sets.id')->all()
                        : [];

                    $roleIds    = $user->roles()->pluck('roles.id')->all();
                    $roleSetIds = empty($roleIds)
                        ? []
                        : PermissionSet::query()
                            ->whereHas('roles', fn ($q) => $q->whereIn('roles.id', $roleIds))
                            ->pluck('permission_sets.id')->all();

                    $allSetIds = array_values(array_unique(array_map('strval', array_merge($userSetIds, $roleSetIds))));
                    if (empty($allSetIds)) {
                        return [];
                    }

                    return DB::table('permission_set_mutes as m')
                        ->join('permissions as p', 'p.id', '=', 'm.permission_id')
                        ->whereIn('m.permission_set_id', $allSetIds)
                        ->pluck('p.name')
                        ->all();
                }
            );

            if (in_array($ability, $muted, true)) {
                return false;
            }

            return null;
        });
    }
}
