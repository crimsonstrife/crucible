<div class="col-md-4">
    <div class="pe-md-4">
        <h5 class="fw-semibold mb-1">{{ $title }}</h5>
        <p class="text-muted small mb-0">{{ $description }}</p>
    </div>

    @isset($aside)
        <div class="mt-2">
            {{ $aside }}
        </div>
    @endisset
</div>
