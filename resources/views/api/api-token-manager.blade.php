<div>
    {{-- Create API Token --}}
    <x-form-section submit="createApiToken">
        <x-slot name="title">
            {{ __('Create API Token') }}
        </x-slot>

        <x-slot name="description">
            {{ __('API tokens allow third-party services to authenticate with our application on your behalf.') }}
        </x-slot>

        <x-slot name="form">
            {{-- Token Name --}}
            <div class="col-12 col-md-8">
                <x-label for="name" value="{{ __('Token Name') }}" />
                <x-input id="name" type="text" class="mt-1" wire:model="createApiTokenForm.name" autofocus />
                <x-input-error for="name" class="mt-1" />
            </div>

            {{-- Token Permissions --}}
            @if (Laravel\Jetstream\Jetstream::hasPermissions())
                <div class="col-12">
                    <x-label value="{{ __('Permissions') }}" />

                    <div class="row mt-2">
                        @foreach (Laravel\Jetstream\Jetstream::$permissions as $permission)
                            <div class="col-6 col-md-4">
                                <div class="form-check">
                                    <x-checkbox wire:model="createApiTokenForm.permissions" :value="$permission" class="form-check-input" />
                                    <label class="form-check-label small">{{ $permission }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-slot>

        <x-slot name="actions">
            <x-action-message on="created">
                {{ __('Created.') }}
            </x-action-message>

            <x-button>
                {{ __('Create') }}
            </x-button>
        </x-slot>
    </x-form-section>

    @if ($this->user->tokens->isNotEmpty())
        <x-section-border />

        {{-- Manage API Tokens --}}
        <x-action-section>
            <x-slot name="title">
                {{ __('Manage API Tokens') }}
            </x-slot>

            <x-slot name="description">
                {{ __('You may delete any of your existing tokens if they are no longer needed.') }}
            </x-slot>

            <x-slot name="content">
                <ul class="list-group list-group-flush">
                    @foreach ($this->user->tokens->sortBy('name') as $token)
                        <li class="list-group-item d-flex align-items-center justify-content-between px-0">
                            <span class="text-break">{{ $token->name }}</span>

                            <div class="d-flex align-items-center gap-3 ms-3 flex-shrink-0">
                                @if ($token->last_used_at)
                                    <span class="text-muted small">
                                        {{ __('Last used') }} {{ $token->last_used_at->diffForHumans() }}
                                    </span>
                                @endif

                                @if (Laravel\Jetstream\Jetstream::hasPermissions())
                                    <button class="btn btn-link btn-sm text-muted p-0 text-decoration-underline"
                                        wire:click="manageApiTokenPermissions({{ $token->id }})">
                                        {{ __('Permissions') }}
                                    </button>
                                @endif

                                <button class="btn btn-link btn-sm text-danger p-0 text-decoration-none"
                                    wire:click="confirmApiTokenDeletion({{ $token->id }})">
                                    {{ __('Delete') }}
                                </button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-slot>
        </x-action-section>
    @endif

    {{-- Token Value Modal --}}
    <x-dialog-modal wire:model.live="displayingToken">
        <x-slot name="title">
            {{ __('API Token') }}
        </x-slot>

        <x-slot name="content">
            <p class="mb-3">{{ __('Please copy your new API token. For your security, it won\'t be shown again.') }}</p>

            <div class="input-group">
                <x-input type="text" readonly :value="$plainTextToken"
                    class="font-monospace small bg-auto"
                    autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                    x-ref="plaintextToken"
                    x-on:showing-token-modal.window="setTimeout(() => $refs.plaintextToken.select(), 250)"
                />
                <button class="btn btn-outline-secondary" type="button"
                    onclick="navigator.clipboard.writeText(document.querySelector('[x-ref=plaintextToken]').value)">
                    {{ __('Copy') }}
                </button>
            </div>
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="$set('displayingToken', false)" wire:loading.attr="disabled">
                {{ __('Close') }}
            </x-secondary-button>
        </x-slot>
    </x-dialog-modal>

    {{-- API Token Permissions Modal --}}
    <x-dialog-modal wire:model.live="managingApiTokenPermissions">
        <x-slot name="title">
            {{ __('API Token Permissions') }}
        </x-slot>

        <x-slot name="content">
            <div class="row">
                @foreach (Laravel\Jetstream\Jetstream::$permissions as $permission)
                    <div class="col-6 col-md-4">
                        <div class="form-check">
                            <x-checkbox wire:model="updateApiTokenForm.permissions" :value="$permission" class="form-check-input" />
                            <label class="form-check-label small">{{ $permission }}</label>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="$set('managingApiTokenPermissions', false)" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-button class="ms-2" wire:click="updateApiToken" wire:loading.attr="disabled">
                {{ __('Save') }}
            </x-button>
        </x-slot>
    </x-dialog-modal>

    {{-- Delete Token Confirmation Modal --}}
    <x-confirmation-modal wire:model.live="confirmingApiTokenDeletion">
        <x-slot name="title">
            {{ __('Delete API Token') }}
        </x-slot>

        <x-slot name="content">
            {{ __('Are you sure you would like to delete this API token?') }}
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="$toggle('confirmingApiTokenDeletion')" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-danger-button class="ms-2" wire:click="deleteApiToken" wire:loading.attr="disabled">
                {{ __('Delete') }}
            </x-danger-button>
        </x-slot>
    </x-confirmation-modal>
</div>
