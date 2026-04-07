{{--
    Image diff component — side-by-side, overlay, and swipe comparison.

    Props:
      $baseUrl    string|null  — URL to the base (old) version of the image
      $headUrl    string|null  — URL to the head (new) version of the image
      $fileName   string       — display file name
      $isNew      bool         — true if file is newly added
      $isDeleted  bool         — true if file was deleted
      $id         string       — unique ID for this diff instance
--}}
@props(['baseUrl' => null, 'headUrl' => null, 'fileName' => '', 'isNew' => false, 'isDeleted' => false, 'id' => 'img-diff-0'])

<div class="image-diff-container" id="{{ $id }}">
    {{-- Mode switcher --}}
    <div class="d-flex gap-1 mb-2">
        <button type="button" class="btn btn-sm btn-outline-secondary active" data-mode="side-by-side"
                onclick="setImageDiffMode('{{ $id }}', 'side-by-side', this)">Side by side</button>
        @if ($baseUrl && $headUrl)
            <button type="button" class="btn btn-sm btn-outline-secondary" data-mode="overlay"
                    onclick="setImageDiffMode('{{ $id }}', 'overlay', this)">Overlay</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-mode="swipe"
                    onclick="setImageDiffMode('{{ $id }}', 'swipe', this)">Swipe</button>
        @endif
    </div>

    {{-- Side-by-side view (default) --}}
    <div class="image-diff-view image-diff-side-by-side" data-view="side-by-side">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="text-muted small fw-semibold mb-1">
                    {{ $isNew ? '(new file)' : 'Base' }}
                </div>
                <div class="border rounded p-2 text-center bg-light" style="min-height: 100px;">
                    @if ($baseUrl && ! $isNew)
                        <img src="{{ $baseUrl }}" alt="Base: {{ $fileName }}"
                             class="img-fluid" style="max-height: 400px; image-rendering: auto;"
                             loading="lazy"
                             onerror="this.parentNode.innerHTML='<span class=\'text-muted small\'>Unable to render preview</span>'">
                    @else
                        <span class="text-muted small d-block py-4">{{ $isNew ? 'No previous version' : 'File not found at base ref' }}</span>
                    @endif
                </div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small fw-semibold mb-1">
                    {{ $isDeleted ? '(deleted)' : 'Head' }}
                </div>
                <div class="border rounded p-2 text-center bg-light" style="min-height: 100px;">
                    @if ($headUrl && ! $isDeleted)
                        <img src="{{ $headUrl }}" alt="Head: {{ $fileName }}"
                             class="img-fluid" style="max-height: 400px; image-rendering: auto;"
                             loading="lazy"
                             onerror="this.parentNode.innerHTML='<span class=\'text-muted small\'>Unable to render preview</span>'">
                    @else
                        <span class="text-muted small d-block py-4">{{ $isDeleted ? 'File deleted' : 'File not found at head ref' }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Overlay view --}}
    @if ($baseUrl && $headUrl)
        <div class="image-diff-view image-diff-overlay d-none" data-view="overlay">
            <div class="mb-2">
                <label class="form-label small text-muted mb-1">Opacity</label>
                <input type="range" class="form-range" min="0" max="100" value="50"
                       oninput="document.getElementById('{{ $id }}-overlay-head').style.opacity = this.value / 100">
            </div>
            <div class="border rounded p-2 bg-light text-center position-relative" style="min-height: 100px;">
                <img src="{{ $baseUrl }}" alt="Base" class="img-fluid" style="max-height: 400px;" loading="lazy">
                <img src="{{ $headUrl }}" alt="Head" id="{{ $id }}-overlay-head"
                     class="img-fluid position-absolute top-50 start-50 translate-middle"
                     style="max-height: 400px; opacity: 0.5;" loading="lazy">
            </div>
        </div>

        {{-- Swipe view --}}
        <div class="image-diff-view image-diff-swipe d-none" data-view="swipe">
            <div class="border rounded bg-light position-relative overflow-hidden" style="min-height: 100px;">
                <div class="text-center">
                    <img src="{{ $headUrl }}" alt="Head" class="img-fluid" style="max-height: 400px;" loading="lazy">
                </div>
                <div class="position-absolute top-0 start-0 h-100 overflow-hidden" id="{{ $id }}-swipe-clip"
                     style="width: 50%; border-right: 2px solid #cf222e;">
                    <div class="text-center h-100 d-flex align-items-center justify-content-center">
                        <img src="{{ $baseUrl }}" alt="Base" class="img-fluid" style="max-height: 400px;" loading="lazy">
                    </div>
                </div>
            </div>
            <div class="mt-2">
                <input type="range" class="form-range" min="0" max="100" value="50"
                       oninput="document.getElementById('{{ $id }}-swipe-clip').style.width = this.value + '%'">
            </div>
        </div>
    @endif

    {{-- Metadata slots --}}
    <div class="row g-3 mt-2" id="{{ $id }}-metadata">
        @if ($baseUrl && ! $isNew)
            <div class="col-md-6">
                <div class="image-meta-slot" data-ref="base"
                     data-url="{{ str_replace('/asset-preview/', '/asset-metadata/', $baseUrl) }}"></div>
            </div>
        @endif
        @if ($headUrl && ! $isDeleted)
            <div class="{{ $baseUrl && ! $isNew ? 'col-md-6' : 'col-12' }}">
                <div class="image-meta-slot" data-ref="head"
                     data-url="{{ str_replace('/asset-preview/', '/asset-metadata/', $headUrl) }}"></div>
            </div>
        @endif
    </div>
</div>
