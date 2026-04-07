{{--
    Binary metadata display card for diff views.

    Props:
      $metadata   array|null  — metadata from BinaryMetadataService::extract()
      $label      string      — "Base" or "Head"
--}}
@props(['metadata' => null, 'label' => ''])

@if ($metadata)
    <div class="card border-0 bg-light">
        <div class="card-body py-2 px-3 small">
            <div class="fw-semibold text-muted mb-1">{{ $label }} Metadata</div>
            <dl class="row mb-0" style="font-size: .8rem;">
                @if (isset($metadata['size']))
                    <dt class="col-5 text-muted">Size</dt>
                    <dd class="col-7 mb-1">{{ number_format($metadata['size']) }} bytes</dd>
                @endif

                @if (isset($metadata['width']) && isset($metadata['height']))
                    <dt class="col-5 text-muted">Dimensions</dt>
                    <dd class="col-7 mb-1">{{ $metadata['width'] }} x {{ $metadata['height'] }} px</dd>
                @endif

                @if (isset($metadata['format']))
                    <dt class="col-5 text-muted">Format</dt>
                    <dd class="col-7 mb-1">{{ $metadata['format'] }}</dd>
                @endif

                @if (isset($metadata['bits']) && $metadata['bits'])
                    <dt class="col-5 text-muted">Color depth</dt>
                    <dd class="col-7 mb-1">{{ $metadata['bits'] }}-bit</dd>
                @endif

                @if (isset($metadata['channels']))
                    <dt class="col-5 text-muted">Channels</dt>
                    <dd class="col-7 mb-1">{{ $metadata['channels'] === 1 ? 'Mono' : ($metadata['channels'] === 2 ? 'Stereo' : $metadata['channels'] . ' ch') }}</dd>
                @endif

                @if (isset($metadata['sample_rate']))
                    <dt class="col-5 text-muted">Sample rate</dt>
                    <dd class="col-7 mb-1">{{ number_format($metadata['sample_rate']) }} Hz</dd>
                @endif

                @if (isset($metadata['bits_per_sample']))
                    <dt class="col-5 text-muted">Bit depth</dt>
                    <dd class="col-7 mb-1">{{ $metadata['bits_per_sample'] }}-bit</dd>
                @endif

                @if (isset($metadata['duration_seconds']))
                    <dt class="col-5 text-muted">Duration</dt>
                    <dd class="col-7 mb-1">{{ gmdate('H:i:s', (int) $metadata['duration_seconds']) }}</dd>
                @endif

                @if (isset($metadata['engine']))
                    <dt class="col-5 text-muted">Engine</dt>
                    <dd class="col-7 mb-1">{{ $metadata['engine'] }}</dd>
                @endif

                @if (isset($metadata['asset_type']))
                    <dt class="col-5 text-muted">Asset type</dt>
                    <dd class="col-7 mb-1">{{ $metadata['asset_type'] }}</dd>
                @endif

                @if (isset($metadata['file_version']))
                    <dt class="col-5 text-muted">File version</dt>
                    <dd class="col-7 mb-1">{{ $metadata['file_version'] }}</dd>
                @endif

                @if (isset($metadata['version']))
                    <dt class="col-5 text-muted">Version</dt>
                    <dd class="col-7 mb-1">{{ $metadata['version'] }}</dd>
                @endif

                @if (isset($metadata['vertices']))
                    <dt class="col-5 text-muted">Vertices</dt>
                    <dd class="col-7 mb-1">{{ number_format($metadata['vertices']) }}</dd>
                @endif

                @if (isset($metadata['faces']))
                    <dt class="col-5 text-muted">Faces</dt>
                    <dd class="col-7 mb-1">{{ number_format($metadata['faces']) }}</dd>
                @endif

                @if (isset($metadata['meshes']))
                    <dt class="col-5 text-muted">Meshes</dt>
                    <dd class="col-7 mb-1">{{ $metadata['meshes'] }}</dd>
                @endif

                @if (isset($metadata['materials']))
                    <dt class="col-5 text-muted">Materials</dt>
                    <dd class="col-7 mb-1">{{ $metadata['materials'] }}</dd>
                @endif
            </dl>
        </div>
    </div>
@endif
