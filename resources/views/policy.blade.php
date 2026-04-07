<x-guest-layout>
    <div class="min-vh-100 d-flex flex-column align-items-center py-5 bg-auto">
        <div class="mb-4">
            <x-authentication-card-logo />
        </div>

        <div class="card shadow-sm" style="width: 100%; max-width: 680px;">
            <div class="card-body p-4">
                <div class="prose">
                    {!! $policy !!}
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
