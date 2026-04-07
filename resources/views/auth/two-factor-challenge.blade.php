<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <x-validation-errors class="mb-3" />

        <form method="POST" action="{{ route('two-factor.login') }}" id="two-factor-form">
            @csrf

            {{-- Authentication code section --}}
            <div id="code-section">
                <p class="text-muted small mb-3">
                    {{ __('Please confirm access to your account by entering the authentication code provided by your authenticator application.') }}
                </p>

                <div class="mb-3">
                    <x-label for="code" value="{{ __('Code') }}" />
                    <x-input id="code" type="text" inputmode="numeric" name="code" autofocus autocomplete="one-time-code" />
                </div>
            </div>

            {{-- Recovery code section (hidden by default) --}}
            <div id="recovery-section" style="display: none;">
                <p class="text-muted small mb-3">
                    {{ __('Please confirm access to your account by entering one of your emergency recovery codes.') }}
                </p>

                <div class="mb-3">
                    <x-label for="recovery_code" value="{{ __('Recovery Code') }}" />
                    <x-input id="recovery_code" type="text" name="recovery_code" autocomplete="one-time-code" />
                </div>
            </div>

            <div class="d-flex align-items-center justify-content-end gap-3 mt-4">
                <button type="button" class="btn btn-link btn-sm text-muted p-0 text-decoration-none" id="toggle-recovery-btn"
                    onclick="toggleTwoFactorMode()">
                    {{ __('Use a recovery code') }}
                </button>

                <x-button>
                    {{ __('Log in') }}
                </x-button>
            </div>
        </form>
    </x-authentication-card>
</x-guest-layout>

<script>
function toggleTwoFactorMode() {
    const codeSection = document.getElementById('code-section');
    const recoverySection = document.getElementById('recovery-section');
    const toggleBtn = document.getElementById('toggle-recovery-btn');
    const usingRecovery = recoverySection.style.display !== 'none';

    if (usingRecovery) {
        codeSection.style.display = '';
        recoverySection.style.display = 'none';
        toggleBtn.textContent = '{{ __('Use a recovery code') }}';
        document.getElementById('code').focus();
    } else {
        codeSection.style.display = 'none';
        recoverySection.style.display = '';
        toggleBtn.textContent = '{{ __('Use an authentication code') }}';
        document.getElementById('recovery_code').focus();
    }
}
</script>
