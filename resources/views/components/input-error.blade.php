@props(['for'])

@error($for)
    <p {{ $attributes->merge(['class' => 'text-danger small mt-1']) }}>{{ $message }}</p>
@enderror
