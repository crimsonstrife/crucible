<x-app-layout>
    <x-slot name="header">
        <h4 class="mb-0 fw-semibold">{{ __('Create Team') }}</h4>
    </x-slot>

    <div class="container-fluid">
        @livewire('teams.create-team-form')
    </div>
</x-app-layout>
