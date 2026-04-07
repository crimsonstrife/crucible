@props(['id', 'maxWidth'])

@php
$id = $id ?? md5($attributes->wire('model'));

$maxWidthClass = match($maxWidth ?? '2xl') {
    'sm'  => 'modal-sm',
    'md'  => '',
    'lg'  => 'modal-lg',
    'xl'  => 'modal-xl',
    '2xl' => 'modal-xl',
    default => 'modal-xl',
};
@endphp

<div
    wire:ignore.self
    class="modal fade"
    id="{{ $id }}"
    tabindex="-1"
    aria-hidden="true"
    x-data="{ show: @entangle($attributes->wire('model')) }"
    x-init="
        $watch('show', value => {
            if (value) {
                bootstrap.Modal.getOrCreateInstance($el).show();
            } else {
                bootstrap.Modal.getOrCreateInstance($el).hide();
            }
        });
        $el.addEventListener('hidden.bs.modal', () => { show = false; });
    "
>
    <div class="modal-dialog {{ $maxWidthClass }}">
        <div class="modal-content">
            {{ $slot }}
        </div>
    </div>
</div>
