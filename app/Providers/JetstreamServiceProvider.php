<?php

namespace App\Providers;

use App\Actions\Jetstream\AddTeamMember;
use App\Actions\Jetstream\CreateTeam;
use App\Actions\Jetstream\DeleteTeam;
use App\Actions\Jetstream\DeleteUser;
use App\Actions\Jetstream\InviteTeamMember;
use App\Actions\Jetstream\RemoveTeamMember;
use App\Actions\Jetstream\UpdateTeamName;
use Illuminate\Support\ServiceProvider;
use Laravel\Jetstream\Jetstream;

class JetstreamServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->configurePermissions();

        Jetstream::createTeamsUsing(CreateTeam::class);
        Jetstream::updateTeamNamesUsing(UpdateTeamName::class);
        Jetstream::addTeamMembersUsing(AddTeamMember::class);
        Jetstream::inviteTeamMembersUsing(InviteTeamMember::class);
        Jetstream::removeTeamMembersUsing(RemoveTeamMember::class);
        Jetstream::deleteTeamsUsing(DeleteTeam::class);
        Jetstream::deleteUsersUsing(DeleteUser::class);
    }

    protected function configurePermissions(): void
    {
        Jetstream::defaultApiTokenPermissions(['repositories:read']);

        Jetstream::permissions([
            'repositories:read',
            'repositories:write',
            'repositories:delete',
            'lfs:read',
            'lfs:write',
        ]);

        Jetstream::role('admin', 'Administrator', [
            'repositories:read',
            'repositories:write',
            'repositories:delete',
            'lfs:read',
            'lfs:write',
        ])->description('Administrator users can manage all repositories.');

        Jetstream::role('editor', 'Editor', [
            'repositories:read',
            'repositories:write',
            'lfs:read',
            'lfs:write',
        ])->description('Editors can read and write to repositories.');

        Jetstream::role('viewer', 'Viewer', [
            'repositories:read',
            'lfs:read',
        ])->description('Viewers have read-only access.');
    }
}
