@props(['align' => 'end', 'width' => '48', 'contentClasses' => '', 'dropdownClasses' => ''])

<div class="dropdown {{ $dropdownClasses }}">
    <span data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer;">
        {{ $trigger }}
    </span>

    <ul class="dropdown-menu dropdown-menu-{{ $align }} {{ $contentClasses }}">
        {{ $content }}
    </ul>
</div>
