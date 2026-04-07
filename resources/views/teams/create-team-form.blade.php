<x-form-section submit="createTeam">
    <x-slot name="title">
        {{ __('Team Details') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Create a new team to collaborate with others on projects.') }}
    </x-slot>

    <x-slot name="form">
        {{-- Team Owner --}}
        <div class="col-12">
            <x-label value="{{ __('Team Owner') }}" />

            <div class="d-flex align-items-center mt-2 gap-3">
                <img class="rounded-circle"
                     src="{{ $this->user->profile_photo_url }}"
                     alt="{{ $this->user->name }}"
                     style="width: 3rem; height: 3rem; object-fit: cover;">

                <div>
                    <p class="mb-0 fw-medium">{{ $this->user->name }}</p>
                    <p class="mb-0 text-muted small">{{ $this->user->email }}</p>
                </div>
            </div>
        </div>

        {{-- Team Name --}}
        <div class="col-12 col-md-8">
            <x-label for="name" value="{{ __('Team Name') }}" />
            <x-input id="name" type="text" class="mt-1" wire:model="state.name" autofocus />
            <x-input-error for="name" class="mt-1" />
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-button>
            {{ __('Create') }}
        </x-button>
    </x-slot>
</x-form-section>
