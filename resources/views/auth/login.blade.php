<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <x-validation-errors class="mb-3" />

        @session('status')
            <div class="alert alert-success mb-3" role="alert">
                {{ $value }}
            </div>
        @endsession

        @if (config('crucible.forge.enabled') && config('crucible.forge.url') && config('crucible.forge.client_id') && config('crucible.forge.client_secret'))
            <div class="mb-4">
                <a href="{{ route('auth.forge') }}" class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>
                    </svg>
                    {{ __('Sign in with Forge') }}
                </a>
            </div>

            <div class="d-flex align-items-center gap-3 mb-4">
                <hr class="flex-grow-1 m-0">
                <span class="text-body-secondary small">{{ __('or') }}</span>
                <hr class="flex-grow-1 m-0">
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="mb-3">
                <x-label for="email" value="{{ __('Email') }}" />
                <x-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            </div>

            <div class="mb-3">
                <x-label for="password" value="{{ __('Password') }}" />
                <x-input id="password" type="password" name="password" required autocomplete="current-password" />
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <x-checkbox id="remember_me" name="remember" class="form-check-input" />
                    <label class="form-check-label" for="remember_me">{{ __('Remember me') }}</label>
                </div>
            </div>

            <div class="d-flex align-items-center justify-content-end gap-3 mt-4">
                @if (Route::has('password.request'))
                    <a class="text-muted small text-decoration-none" href="{{ route('password.request') }}">
                        {{ __('Forgot your password?') }}
                    </a>
                @endif

                <x-button>
                    {{ __('Log in') }}
                </x-button>
            </div>
        </form>
    </x-authentication-card>
</x-guest-layout>
