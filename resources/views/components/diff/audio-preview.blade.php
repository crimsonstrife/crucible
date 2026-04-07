{{--
    Audio preview component for diff views.

    Props:
      $baseUrl    string|null  — URL to the base (old) version
      $headUrl    string|null  — URL to the head (new) version
      $fileName   string       — display file name
      $mimeType   string       — MIME type for the audio element
      $isNew      bool
      $isDeleted  bool
--}}
@props(['baseUrl' => null, 'headUrl' => null, 'fileName' => '', 'mimeType' => 'audio/mpeg', 'isNew' => false, 'isDeleted' => false])

<div class="audio-diff-container">
    <div class="row g-3">
        <div class="col-md-6">
            <div class="text-muted small fw-semibold mb-1">{{ $isNew ? '(new file)' : 'Base' }}</div>
            <div class="border rounded p-3 bg-light">
                @if ($baseUrl && ! $isNew)
                    <audio controls preload="metadata" class="w-100">
                        <source src="{{ $baseUrl }}" type="{{ $mimeType }}">
                        Your browser does not support audio playback.
                    </audio>
                @else
                    <span class="text-muted small">{{ $isNew ? 'No previous version' : 'Audio not available' }}</span>
                @endif
            </div>
        </div>
        <div class="col-md-6">
            <div class="text-muted small fw-semibold mb-1">{{ $isDeleted ? '(deleted)' : 'Head' }}</div>
            <div class="border rounded p-3 bg-light">
                @if ($headUrl && ! $isDeleted)
                    <audio controls preload="metadata" class="w-100">
                        <source src="{{ $headUrl }}" type="{{ $mimeType }}">
                        Your browser does not support audio playback.
                    </audio>
                @else
                    <span class="text-muted small">{{ $isDeleted ? 'File deleted' : 'Audio not available' }}</span>
                @endif
            </div>
        </div>
    </div>
</div>
