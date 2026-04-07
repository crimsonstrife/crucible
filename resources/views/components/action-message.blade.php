@props(['on'])

<div
    x-data="{ shown: false, timeout: null }"
    x-init="@this.on('{{ $on }}', () => { clearTimeout(timeout); shown = true; timeout = setTimeout(() => { shown = false }, 2000); })"
    x-show="shown"
    x-transition:leave="transition-opacity duration-1000"
    style="display: none;"
    {{ $attributes->merge(['class' => 'text-success small fw-medium']) }}>
    {{ $slot->isEmpty() ? 'Saved.' : $slot }}
</div>
