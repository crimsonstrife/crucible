@props(['active'])

@php
$classes = ($active ?? false)
    ? 'nav-link active fw-medium ps-3'
    : 'nav-link ps-3';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
