<x-app-layout>
    <x-slot name="header">
        <h4 class="mb-0 fw-semibold">{{ __('API Tokens') }}</h4>
    </x-slot>

    <div class="container-fluid">
        @livewire('api.api-token-manager')
    </div>
</x-app-layout>
