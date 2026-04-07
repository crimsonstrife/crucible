{{--
    Video preview component for diff views.

    Props:
      $baseUrl    string|null  — URL to the base (old) version
      $headUrl    string|null  — URL to the head (new) version
      $fileName   string       — display file name
      $mimeType   string       — MIME type for the video element
      $isNew      bool
      $isDeleted  bool
--}}
@props(['baseUrl' => null, 'headUrl' => null, 'fileName' => '', 'mimeType' => 'video/mp4', 'isNew' => false, 'isDeleted' => false])

<div class="video-diff-container">
    <div class="row g-3">
        <div class="col-md-6">
            <div class="text-muted small fw-semibold mb-1">{{ $isNew ? '(new file)' : 'Base' }}</div>
            <div class="border rounded p-2 bg-light text-center">
                @if ($baseUrl && ! $isNew)
                    <video controls preload="metadata" class="w-100" style="max-height: 400px;">
                        <source src="{{ $baseUrl }}" type="{{ $mimeType }}">
                        Your browser does not support video playback.
                    </video>
                @else
                    <span class="text-muted small d-block py-4">{{ $isNew ? 'No previous version' : 'Video not available' }}</span>
                @endif
            </div>
        </div>
        <div class="col-md-6">
            <div class="text-muted small fw-semibold mb-1">{{ $isDeleted ? '(deleted)' : 'Head' }}</div>
            <div class="border rounded p-2 bg-light text-center">
                @if ($headUrl && ! $isDeleted)
                    <video controls preload="metadata" class="w-100" style="max-height: 400px;">
                        <source src="{{ $headUrl }}" type="{{ $mimeType }}">
                        Your browser does not support video playback.
                    </video>
                @else
                    <span class="text-muted small d-block py-4">{{ $isDeleted ? 'File deleted' : 'Video not available' }}</span>
                @endif
            </div>
        </div>
    </div>
</div>
