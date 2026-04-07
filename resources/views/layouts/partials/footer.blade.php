<footer class="bg-body border-top mt-auto py-4">
    <div class="container-fluid">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <span class="fw-semibold text-body-secondary small">
                <i class="fas fa-fire-flame-curved me-1"></i>
                {{ config('app.name', 'Crucible') }}
            </span>
            <div class="text-body-secondary small">
                &copy; {{ now()->year }} {{ config('app.name', 'Crucible') }}. {{ __('All rights reserved.') }}
            </div>
            <div>
                @include('layouts.partials.theme-toggle')
            </div>
        </div>
    </div>
</footer>
