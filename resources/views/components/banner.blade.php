@props(['style' => session('flash.bannerStyle', 'success'), 'message' => session('flash.banner')])

@if ($message)
@php
$alertClass = match($style) {
    'danger'  => 'alert-danger',
    'warning' => 'alert-warning',
    default   => 'alert-success',
};
@endphp
<div class="alert {{ $alertClass }} alert-dismissible fade show rounded-0 mb-0" role="alert"
    x-data="{{ json_encode(['show' => true, 'style' => $style, 'message' => $message]) }}"
    x-show="show && message"
    x-on:banner-message.window="
        style = event.detail.style;
        message = event.detail.message;
        show = true;
    "
    x-text="message">
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
</div>
@endif

{{-- Also handle live Livewire banner events when no initial message --}}
@if (!$message)
<div
    x-data="{ show: false, style: 'success', message: '' }"
    x-show="show && message"
    x-on:banner-message.window="
        style = event.detail.style;
        message = event.detail.message;
        show = true;
    "
    :class="{
        'alert alert-success alert-dismissible fade show rounded-0 mb-0': style === 'success',
        'alert alert-danger alert-dismissible fade show rounded-0 mb-0': style === 'danger',
        'alert alert-warning alert-dismissible fade show rounded-0 mb-0': style === 'warning',
    }"
    style="display: none;"
    role="alert"
    x-text="message">
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"
        x-on:click="show = false"></button>
</div>
@endif
