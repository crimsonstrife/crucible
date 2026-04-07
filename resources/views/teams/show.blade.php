<x-app-layout>
    <x-slot name="header">
        <h4 class="mb-0 fw-semibold">{{ __('Team Settings') }}</h4>
    </x-slot>

    <div class="container-fluid">
        @livewire('teams.update-team-name-form', ['team' => $team])

        @livewire('teams.team-member-manager', ['team' => $team])

        @if (Gate::check('delete', $team) && ! $team->personal_team)
            <x-section-border />
            @livewire('teams.delete-team-form', ['team' => $team])
        @endif
    </div>
</x-app-layout>
