<x-form-section submit="updateTeamName">
    <x-slot name="title">
        {{ __('Team Name') }}
    </x-slot>

    <x-slot name="description">
        {{ __('The team\'s name and owner information.') }}
    </x-slot>

    <x-slot name="form">
        {{-- Team Owner --}}
        <div class="col-12">
            <x-label value="{{ __('Team Owner') }}" />

            <div class="d-flex align-items-center mt-2 gap-3">
                <img class="rounded-circle"
                     src="{{ $team->owner->profile_photo_url }}"
                     alt="{{ $team->owner->name }}"
                     style="width: 3rem; height: 3rem; object-fit: cover;">

                <div>
                    <p class="mb-0 fw-medium">{{ $team->owner->name }}</p>
                    <p class="mb-0 text-muted small">{{ $team->owner->email }}</p>
                </div>
            </div>
        </div>

        {{-- Team Name --}}
        <div class="col-12 col-md-8">
            <x-label for="name" value="{{ __('Team Name') }}" />
            <x-input id="name" type="text" class="mt-1" wire:model="state.name" :disabled="! Gate::check('update', $team)" />
            <x-input-error for="name" class="mt-1" />
        </div>
    </x-slot>

    @if (Gate::check('update', $team))
        <x-slot name="actions">
            <x-action-message on="saved">
                {{ __('Saved.') }}
            </x-action-message>

            <x-button>
                {{ __('Save') }}
            </x-button>
        </x-slot>
    @endif
</x-form-section>
