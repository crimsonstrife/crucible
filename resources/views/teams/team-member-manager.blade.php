<div>
    @if (Gate::check('addTeamMember', $team))
        <x-section-border />

        {{-- Add Team Member --}}
        <x-form-section submit="addTeamMember">
            <x-slot name="title">
                {{ __('Add Team Member') }}
            </x-slot>

            <x-slot name="description">
                {{ __('Add a new team member to your team, allowing them to collaborate with you.') }}
            </x-slot>

            <x-slot name="form">
                <div class="col-12">
                    <p class="text-muted small mb-0">
                        {{ __('Please provide the email address of the person you would like to add to this team.') }}
                    </p>
                </div>

                {{-- Member Email --}}
                <div class="col-12 col-md-8">
                    <x-label for="email" value="{{ __('Email') }}" />
                    <x-input id="email" type="email" class="mt-1" wire:model="addTeamMemberForm.email" />
                    <x-input-error for="email" class="mt-1" />
                </div>

                {{-- Role --}}
                @if (count($this->roles) > 0)
                    <div class="col-12 col-md-8">
                        <x-label value="{{ __('Role') }}" />
                        <x-input-error for="role" class="mt-1" />

                        <div class="list-group mt-2">
                            @foreach ($this->roles as $index => $role)
                                <button type="button"
                                    class="list-group-item list-group-item-action {{ isset($addTeamMemberForm['role']) && $addTeamMemberForm['role'] === $role->key ? 'active' : '' }}"
                                    wire:click="$set('addTeamMemberForm.role', '{{ $role->key }}')">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="fw-medium">{{ $role->name }}</span>
                                        @if (isset($addTeamMemberForm['role']) && $addTeamMemberForm['role'] === $role->key)
                                            <svg class="text-success" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.1rem; height: 1.1rem;">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        @endif
                                    </div>
                                    <small class="{{ isset($addTeamMemberForm['role']) && $addTeamMemberForm['role'] === $role->key ? 'text-white-50' : 'text-muted' }}">{{ $role->description }}</small>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </x-slot>

            <x-slot name="actions">
                <x-action-message on="saved">
                    {{ __('Added.') }}
                </x-action-message>

                <x-button>
                    {{ __('Add') }}
                </x-button>
            </x-slot>
        </x-form-section>
    @endif

    @if ($team->teamInvitations->isNotEmpty() && Gate::check('addTeamMember', $team))
        <x-section-border />

        {{-- Pending Invitations --}}
        <x-action-section>
            <x-slot name="title">
                {{ __('Pending Team Invitations') }}
            </x-slot>

            <x-slot name="description">
                {{ __('These people have been invited to your team and have been sent an invitation email. They may join the team by accepting the email invitation.') }}
            </x-slot>

            <x-slot name="content">
                <ul class="list-group list-group-flush">
                    @foreach ($team->teamInvitations as $invitation)
                        <li class="list-group-item d-flex align-items-center justify-content-between px-0">
                            <span class="text-muted">{{ $invitation->email }}</span>

                            @if (Gate::check('removeTeamMember', $team))
                                <button class="btn btn-link btn-sm text-danger p-0 text-decoration-none"
                                    wire:click="cancelTeamInvitation({{ $invitation->id }})">
                                    {{ __('Cancel') }}
                                </button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-slot>
        </x-action-section>
    @endif

    @if ($team->users->isNotEmpty())
        <x-section-border />

        {{-- Team Members List --}}
        <x-action-section>
            <x-slot name="title">
                {{ __('Team Members') }}
            </x-slot>

            <x-slot name="description">
                {{ __('All of the people that are part of this team.') }}
            </x-slot>

            <x-slot name="content">
                <ul class="list-group list-group-flush">
                    @foreach ($team->users->sortBy('name') as $user)
                        <li class="list-group-item d-flex align-items-center justify-content-between px-0">
                            <div class="d-flex align-items-center gap-3">
                                <img class="rounded-circle"
                                     src="{{ $user->profile_photo_url }}"
                                     alt="{{ $user->name }}"
                                     style="width: 2rem; height: 2rem; object-fit: cover;">
                                <span>{{ $user->name }}</span>
                            </div>

                            <div class="d-flex align-items-center gap-3">
                                {{-- Role --}}
                                @if (Gate::check('updateTeamMember', $team) && Laravel\Jetstream\Jetstream::hasRoles())
                                    <button class="btn btn-link btn-sm text-muted p-0 text-decoration-underline"
                                        wire:click="manageRole('{{ $user->id }}')">
                                        {{ Laravel\Jetstream\Jetstream::findRole($user->membership->role)->name }}
                                    </button>
                                @elseif (Laravel\Jetstream\Jetstream::hasRoles())
                                    <span class="text-muted small">
                                        {{ Laravel\Jetstream\Jetstream::findRole($user->membership->role)->name }}
                                    </span>
                                @endif

                                {{-- Leave / Remove --}}
                                @if ($this->user->id === $user->id)
                                    <button class="btn btn-link btn-sm text-danger p-0 text-decoration-none"
                                        wire:click="$toggle('confirmingLeavingTeam')">
                                        {{ __('Leave') }}
                                    </button>
                                @elseif (Gate::check('removeTeamMember', $team))
                                    <button class="btn btn-link btn-sm text-danger p-0 text-decoration-none"
                                        wire:click="confirmTeamMemberRemoval('{{ $user->id }}')">
                                        {{ __('Remove') }}
                                    </button>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-slot>
        </x-action-section>
    @endif

    {{-- Role Management Modal --}}
    <x-dialog-modal wire:model.live="currentlyManagingRole">
        <x-slot name="title">
            {{ __('Manage Role') }}
        </x-slot>

        <x-slot name="content">
            <div class="list-group">
                @foreach ($this->roles as $index => $role)
                    <button type="button"
                        class="list-group-item list-group-item-action {{ $currentRole === $role->key ? 'active' : '' }}"
                        wire:click="$set('currentRole', '{{ $role->key }}')">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="fw-medium">{{ $role->name }}</span>
                            @if ($currentRole === $role->key)
                                <svg class="text-success" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.1rem; height: 1.1rem;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            @endif
                        </div>
                        <small class="{{ $currentRole === $role->key ? 'text-white-50' : 'text-muted' }}">{{ $role->description }}</small>
                    </button>
                @endforeach
            </div>
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="stopManagingRole" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-button class="ms-2" wire:click="updateRole" wire:loading.attr="disabled">
                {{ __('Save') }}
            </x-button>
        </x-slot>
    </x-dialog-modal>

    {{-- Leave Team Confirmation Modal --}}
    <x-confirmation-modal wire:model.live="confirmingLeavingTeam">
        <x-slot name="title">
            {{ __('Leave Team') }}
        </x-slot>

        <x-slot name="content">
            {{ __('Are you sure you would like to leave this team?') }}
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="$toggle('confirmingLeavingTeam')" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-danger-button class="ms-2" wire:click="leaveTeam" wire:loading.attr="disabled">
                {{ __('Leave') }}
            </x-danger-button>
        </x-slot>
    </x-confirmation-modal>

    {{-- Remove Team Member Confirmation Modal --}}
    <x-confirmation-modal wire:model.live="confirmingTeamMemberRemoval">
        <x-slot name="title">
            {{ __('Remove Team Member') }}
        </x-slot>

        <x-slot name="content">
            {{ __('Are you sure you would like to remove this person from the team?') }}
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="$toggle('confirmingTeamMemberRemoval')" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-danger-button class="ms-2" wire:click="removeTeamMember" wire:loading.attr="disabled">
                {{ __('Remove') }}
            </x-danger-button>
        </x-slot>
    </x-confirmation-modal>
</div>
