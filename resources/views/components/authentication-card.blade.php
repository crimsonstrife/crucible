<div class="min-vh-100 d-flex flex-column justify-content-center align-items-center py-5 bg-auto">
    <div class="mb-4">
        {{ $logo }}
    </div>

    <div class="card shadow-sm" style="width: 100%; max-width: 440px;">
        <div class="card-body p-4">
            {{ $slot }}
        </div>
    </div>
</div>
