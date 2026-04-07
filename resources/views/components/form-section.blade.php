@props(['submit'])

<div {{ $attributes->merge(['class' => 'row mb-4']) }}>
    <x-section-title>
        <x-slot name="title">{{ $title }}</x-slot>
        <x-slot name="description">{{ $description }}</x-slot>
    </x-section-title>

    <div class="col-md-8">
        <form wire:submit="{{ $submit }}">
            <div class="card shadow-sm {{ isset($actions) ? 'rounded-bottom-0' : '' }}">
                <div class="card-body p-4">
                    <div class="row g-3">
                        {{ $form }}
                    </div>
                </div>
            </div>

            @if (isset($actions))
                <div class="card border-top-0 rounded-top-0 shadow-sm">
                    <div class="card-footer bg-auto d-flex align-items-center justify-content-end gap-3 py-3 px-4">
                        {{ $actions }}
                    </div>
                </div>
            @endif
        </form>
    </div>
</div>
