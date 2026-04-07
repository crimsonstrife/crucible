<x-form-section submit="updateProfileInformation">
    <x-slot name="title">
        {{ __('Profile Information') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Update your account\'s profile information and email address.') }}
    </x-slot>

    <x-slot name="form">
        {{-- Profile Photo --}}
        @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
            <div class="col-12">
                <x-label for="photo" value="{{ __('Profile Photo') }}" />

                <div class="mt-2 d-flex align-items-center gap-3">
                    {{-- Current Photo --}}
                    <div id="current-photo-container">
                        <img src="{{ $this->user->profile_photo_url }}"
                             alt="{{ $this->user->name }}"
                             class="rounded-circle"
                             style="width: 4rem; height: 4rem; object-fit: cover;">
                    </div>

                    {{-- New Photo Preview --}}
                    <div id="new-photo-container" style="display: none;">
                        <span id="new-photo-preview"
                              class="rounded-circle d-block"
                              style="width: 4rem; height: 4rem; background-size: cover; background-position: center;"></span>
                    </div>

                    <div>
                        <input type="file" id="photo" class="d-none"
                            wire:model.live="photo"
                            onchange="previewProfilePhoto(this)" />

                        <x-secondary-button type="button" onclick="document.getElementById('photo').click()">
                            {{ __('Select A New Photo') }}
                        </x-secondary-button>

                        @if ($this->user->profile_photo_path)
                            <x-secondary-button type="button" class="ms-2" wire:click="deleteProfilePhoto">
                                {{ __('Remove Photo') }}
                            </x-secondary-button>
                        @endif
                    </div>
                </div>

                <x-input-error for="photo" class="mt-2" />
            </div>
        @endif

        {{-- Name --}}
        <div class="col-12 col-md-8">
            <x-label for="name" value="{{ __('Name') }}" />
            <x-input id="name" type="text" class="mt-1" wire:model="state.name" required autocomplete="name" />
            <x-input-error for="name" class="mt-1" />
        </div>

        {{-- Email --}}
        <div class="col-12 col-md-8">
            <x-label for="email" value="{{ __('Email') }}" />
            <x-input id="email" type="email" class="mt-1" wire:model="state.email" required autocomplete="username" />
            <x-input-error for="email" class="mt-1" />

            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::emailVerification()) && ! $this->user->hasVerifiedEmail())
                <p class="text-muted small mt-2">
                    {{ __('Your email address is unverified.') }}

                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-underline" wire:click.prevent="sendEmailVerification">
                        {{ __('Click here to re-send the verification email.') }}
                    </button>
                </p>

                @if ($this->verificationLinkSent)
                    <p class="text-success small mt-1 fw-medium">
                        {{ __('A new verification link has been sent to your email address.') }}
                    </p>
                @endif
            @endif
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-action-message on="saved">
            {{ __('Saved.') }}
        </x-action-message>

        <x-button wire:loading.attr="disabled" wire:target="photo">
            {{ __('Save') }}
        </x-button>
    </x-slot>
</x-form-section>

<script>
function previewProfilePhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('current-photo-container').style.display = 'none';
            const preview = document.getElementById('new-photo-preview');
            preview.style.backgroundImage = 'url(' + e.target.result + ')';
            document.getElementById('new-photo-container').style.display = '';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
