{{--
    Shared diff-files partial.

    Required variables:
      $diffFiles        array   — output of DiffParser::parse()
      $diffTotalAdds    int     — total addition lines
      $diffTotalDels    int     — total deletion lines

    Optional:
      $diffEmptyMessage string  — shown when $diffFiles is empty
      $diffOrganization object  — Organization model (for asset preview URLs)
      $diffRepository   object  — Repository model (for asset preview URLs)
      $diffBaseRef      string  — base git ref for asset comparison (target branch / parent commit)
      $diffHeadRef      string  — head git ref for asset comparison (source branch / commit SHA)
--}}
@php
    $diffEmptyMessage  ??= 'No changes in this diff.';
    $diffOrganization  ??= $organization ?? null;
    $diffRepository    ??= $repository ?? null;
    $diffBaseRef       ??= null;
    $diffHeadRef       ??= null;
    $canPreviewAssets = $diffOrganization && $diffRepository && $diffBaseRef && $diffHeadRef;
@endphp

@if (! empty($diffFiles))
    {{-- Summary bar + expand / collapse controls --}}
    <div class="d-flex align-items-center gap-3 mb-3 small text-muted">
        <span>
            <strong class="text-body">{{ count($diffFiles) }}</strong>
            {{ Str::plural('file', count($diffFiles)) }} changed
        </span>
        @if ($diffTotalAdds > 0)
            <span class="text-success fw-semibold">+{{ number_format($diffTotalAdds) }}</span>
        @endif
        @if ($diffTotalDels > 0)
            <span class="text-danger fw-semibold">-{{ number_format($diffTotalDels) }}</span>
        @endif
        <span class="ms-auto d-flex gap-1">
            <button type="button" class="btn btn-sm btn-outline-secondary"
                    onclick="diffExpandAll(this)">Expand all</button>
            <button type="button" class="btn btn-sm btn-outline-secondary"
                    onclick="diffCollapseAll(this)">Collapse all</button>
        </span>
    </div>

    {{-- Per-file diff cards --}}
    @foreach ($diffFiles as $fileIdx => $file)
        @php
            $collapseId = 'diff-collapse-' . ($loop->depth ?? 1) . '-' . $fileIdx;
            $total      = $file['additions'] + $file['deletions'];
            $addPct     = $total > 0 ? round(($file['additions'] / $total) * 100) : 0;
            $delPct     = $total > 0 ? round(($file['deletions'] / $total) * 100) : 0;
            $diffCat    = $file['diff_category'] ?? null;
        @endphp
        <div class="card shadow-sm mb-3 diff-file-card" id="diff-file-{{ $fileIdx }}">

            {{-- File header (toggle) --}}
            <div class="card-header py-2 px-3 d-flex align-items-center gap-2 diff-file-toggle"
                 role="button"
                 data-bs-toggle="collapse"
                 data-bs-target="#{{ $collapseId }}"
                 aria-expanded="true"
                 style="cursor:pointer; user-select:none;">

                <span class="diff-chevron text-muted flex-shrink-0" style="font-size:.7rem;">&#9660;</span>

                @if ($file['is_new'])
                    <span class="badge bg-success bg-opacity-75 flex-shrink-0" style="font-size:.65rem;">NEW</span>
                @elseif ($file['is_deleted'])
                    <span class="badge bg-danger bg-opacity-75 flex-shrink-0" style="font-size:.65rem;">DEL</span>
                @elseif ($file['is_renamed'])
                    <span class="badge bg-warning flex-shrink-0" style="font-size:.65rem;">REN</span>
                @elseif ($file['is_binary'])
                    <span class="badge bg-secondary flex-shrink-0" style="font-size:.65rem;">BIN</span>
                @endif

                {{-- Visual diff type indicator --}}
                @if ($diffCat === 'image')
                    <span class="badge bg-info bg-opacity-50 flex-shrink-0" style="font-size:.6rem;">IMG</span>
                @elseif ($diffCat === 'audio')
                    <span class="badge bg-info bg-opacity-50 flex-shrink-0" style="font-size:.6rem;">AUD</span>
                @elseif ($diffCat === 'video')
                    <span class="badge bg-info bg-opacity-50 flex-shrink-0" style="font-size:.6rem;">VID</span>
                @elseif ($diffCat === 'model3d')
                    <span class="badge bg-info bg-opacity-50 flex-shrink-0" style="font-size:.6rem;">3D</span>
                @elseif ($diffCat === 'engine_asset')
                    <span class="badge bg-purple bg-opacity-50 flex-shrink-0" style="font-size:.6rem;">ASSET</span>
                @endif

                <span class="font-monospace small fw-semibold flex-grow-1 text-truncate"
                      title="{{ $file['file_name'] }}">{{ $file['file_name'] }}</span>

                @if (! $file['is_binary'])
                    <span class="d-flex align-items-center gap-2 flex-shrink-0 small">
                        <span class="text-success">+{{ $file['additions'] }}</span>
                        <span class="text-danger">-{{ $file['deletions'] }}</span>
                        <span style="width:60px;height:8px;border-radius:4px;background:#dee2e6;overflow:hidden;display:inline-flex;flex-shrink:0;">
                            <span style="width:{{ $addPct }}%;background:#2da44e;"></span>
                            <span style="width:{{ $delPct }}%;background:#cf222e;"></span>
                        </span>
                    </span>
                @endif
            </div>

            {{-- Collapsible diff body --}}
            <div class="collapse show" id="{{ $collapseId }}">
                @if ($file['is_binary'] && $canPreviewAssets && $diffCat)
                    {{-- ════ Visual diff for supported binary types ════ --}}
                    @php
                        $baseUrl = $file['is_new'] ? null : route('repositories.asset-preview', [$diffOrganization, $diffRepository, $diffBaseRef, $file['file_name']]);
                        $headUrl = $file['is_deleted'] ? null : route('repositories.asset-preview', [$diffOrganization, $diffRepository, $diffHeadRef, $file['file_name']]);
                    @endphp

                    <div class="card-body py-3 px-3">
                        @if ($diffCat === 'image')
                            <x-diff.image-diff
                                :baseUrl="$baseUrl"
                                :headUrl="$headUrl"
                                :fileName="$file['file_name']"
                                :isNew="$file['is_new']"
                                :isDeleted="$file['is_deleted']"
                                :id="'img-diff-' . $fileIdx"
                            />

                        @elseif ($diffCat === 'audio')
                            <x-diff.audio-preview
                                :baseUrl="$baseUrl"
                                :headUrl="$headUrl"
                                :fileName="$file['file_name']"
                                :mimeType="$file['mime_type'] ?? 'audio/mpeg'"
                                :isNew="$file['is_new']"
                                :isDeleted="$file['is_deleted']"
                            />

                        @elseif ($diffCat === 'video')
                            <x-diff.video-preview
                                :baseUrl="$baseUrl"
                                :headUrl="$headUrl"
                                :fileName="$file['file_name']"
                                :mimeType="$file['mime_type'] ?? 'video/mp4'"
                                :isNew="$file['is_new']"
                                :isDeleted="$file['is_deleted']"
                            />

                        @elseif ($diffCat === 'engine_asset' || $diffCat === 'model3d')
                            {{-- Engine assets and 3D models: show metadata cards --}}
                            <div class="text-muted small mb-2">
                                @if ($diffCat === 'engine_asset')
                                    Game engine asset — metadata comparison:
                                @else
                                    3D model — metadata comparison:
                                @endif
                            </div>
                            <div class="row g-3">
                                @if ($baseUrl && ! $file['is_new'])
                                    <div class="col-md-6">
                                        <div class="asset-meta-card" data-url="{{ str_replace('/asset-preview/', '/asset-metadata/', $baseUrl) }}" data-label="Base">
                                            <div class="text-center py-3 text-muted small">Loading metadata...</div>
                                        </div>
                                    </div>
                                @endif
                                @if ($headUrl && ! $file['is_deleted'])
                                    <div class="{{ ($baseUrl && ! $file['is_new']) ? 'col-md-6' : 'col-12' }}">
                                        <div class="asset-meta-card" data-url="{{ str_replace('/asset-preview/', '/asset-metadata/', $headUrl) }}" data-label="Head">
                                            <div class="text-center py-3 text-muted small">Loading metadata...</div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                @elseif ($file['is_binary'])
                    <div class="card-body py-2 px-3 text-muted small">Binary file — no diff available.</div>
                @elseif (empty($file['hunks']))
                    <div class="card-body py-2 px-3 text-muted small">No textual changes.</div>
                @else
                    <div class="diff-table-wrap">
                        <table class="diff-table w-100">
                            @foreach ($file['hunks'] as $hunk)
                                <tr class="diff-hunk-row">
                                    <td colspan="3" class="diff-hunk-header">{{ $hunk['header'] }}</td>
                                </tr>
                                @foreach ($hunk['lines'] as $line)
                                    @php
                                        $rowClass = match($line['type']) {
                                            'add'  => 'diff-row-add',
                                            'del'  => 'diff-row-del',
                                            'meta' => 'diff-row-meta',
                                            default => '',
                                        };
                                        $sign = match($line['type']) {
                                            'add'  => '+',
                                            'del'  => '-',
                                            default => ' ',
                                        };
                                    @endphp
                                    <tr class="diff-row {{ $rowClass }}">
                                        <td class="diff-ln">{{ $line['old'] ?? '' }}</td>
                                        <td class="diff-ln">{{ $line['new'] ?? '' }}</td>
                                        <td class="diff-code"><span class="diff-sign">{{ $sign }}</span>{{ $line['content'] }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endforeach

@else
    <div class="card shadow-sm">
        <div class="card-body text-muted small">{{ $diffEmptyMessage }}</div>
    </div>
@endif

{{-- Styles --}}
<style>
    .diff-table-wrap { overflow-x: auto; }
    .diff-table {
        font-family: var(--bs-font-monospace);
        font-size: .8rem;
        line-height: 1.5;
        border-collapse: collapse;
        width: 100%;
    }
    .diff-table td { padding: 1px 0; white-space: pre; }

    .diff-ln {
        width: 1%;
        min-width: 44px;
        padding: 1px 8px;
        text-align: right;
        color: #8b949e;
        background: #f6f8fa;
        border-right: 1px solid #dee2e6;
        user-select: none;
        vertical-align: top;
    }
    .diff-code { padding: 1px 4px 1px 8px; width: 100%; }
    .diff-sign { display: inline-block; width: 14px; color: #6c757d; user-select: none; }

    .diff-row-add .diff-ln   { background: #ccffd8; }
    .diff-row-add .diff-code { background: #e6ffec; }
    .diff-row-add .diff-sign { color: #2da44e; font-weight: 600; }

    .diff-row-del .diff-ln   { background: #ffd7d5; }
    .diff-row-del .diff-code { background: #ffebe9; }
    .diff-row-del .diff-sign { color: #cf222e; font-weight: 600; }

    .diff-row-meta .diff-code { color: #8b949e; font-style: italic; }

    .diff-hunk-row .diff-hunk-header {
        background: #ddf4ff;
        color: #0969da;
        padding: 3px 10px;
        font-family: var(--bs-font-monospace);
        font-size: .8rem;
    }

    .diff-file-toggle[aria-expanded="false"] .diff-chevron { transform: rotate(-90deg); }
    .diff-chevron { transition: transform .15s ease; }
    .min-width-0 { min-width: 0; }

    /* Badge for engine assets */
    .bg-purple { background-color: #8957e5 !important; color: #fff; }

    /* Image diff styling */
    .image-diff-container .image-diff-view { margin-bottom: 0; }
    .image-diff-container img { background: repeating-conic-gradient(#f0f0f0 0% 25%, #fff 0% 50%) 50% / 16px 16px; }
</style>

<script>
(function () {
    // Sync chevron direction when Bootstrap collapses/expands
    if (window._diffListenersAttached) return;
    window._diffListenersAttached = true;

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.diff-file-toggle').forEach(function (toggle) {
            const collapseEl = document.querySelector(toggle.getAttribute('data-bs-target'));
            if (!collapseEl) return;
            collapseEl.addEventListener('hidden.bs.collapse', () => toggle.setAttribute('aria-expanded', 'false'));
            collapseEl.addEventListener('shown.bs.collapse',  () => toggle.setAttribute('aria-expanded', 'true'));
        });

        // Load metadata cards for engine assets / 3D models
        document.querySelectorAll('.asset-meta-card').forEach(function (card) {
            const url = card.dataset.url;
            const label = card.dataset.label || '';
            if (!url) return;

            fetch(url, { credentials: 'same-origin' })
                .then(r => r.json())
                .then(json => {
                    if (!json.data) { card.innerHTML = '<span class="text-muted small">No metadata</span>'; return; }
                    const meta = json.data;
                    let html = '<div class="card border-0 bg-light"><div class="card-body py-2 px-3 small">';
                    html += '<div class="fw-semibold text-muted mb-1">' + label + ' Metadata</div>';
                    html += '<dl class="row mb-0" style="font-size:.8rem;">';
                    if (meta.size) html += '<dt class="col-5 text-muted">Size</dt><dd class="col-7 mb-1">' + meta.size.toLocaleString() + ' bytes</dd>';
                    if (meta.width && meta.height) html += '<dt class="col-5 text-muted">Dimensions</dt><dd class="col-7 mb-1">' + meta.width + ' x ' + meta.height + ' px</dd>';
                    if (meta.format) html += '<dt class="col-5 text-muted">Format</dt><dd class="col-7 mb-1">' + meta.format + '</dd>';
                    if (meta.engine) html += '<dt class="col-5 text-muted">Engine</dt><dd class="col-7 mb-1">' + meta.engine + '</dd>';
                    if (meta.asset_type) html += '<dt class="col-5 text-muted">Asset type</dt><dd class="col-7 mb-1">' + meta.asset_type + '</dd>';
                    if (meta.file_version) html += '<dt class="col-5 text-muted">File version</dt><dd class="col-7 mb-1">' + meta.file_version + '</dd>';
                    if (meta.vertices) html += '<dt class="col-5 text-muted">Vertices</dt><dd class="col-7 mb-1">' + meta.vertices.toLocaleString() + '</dd>';
                    if (meta.faces) html += '<dt class="col-5 text-muted">Faces</dt><dd class="col-7 mb-1">' + meta.faces.toLocaleString() + '</dd>';
                    if (meta.channels) html += '<dt class="col-5 text-muted">Channels</dt><dd class="col-7 mb-1">' + (meta.channels == 1 ? 'Mono' : meta.channels == 2 ? 'Stereo' : meta.channels + ' ch') + '</dd>';
                    if (meta.sample_rate) html += '<dt class="col-5 text-muted">Sample rate</dt><dd class="col-7 mb-1">' + meta.sample_rate.toLocaleString() + ' Hz</dd>';
                    html += '</dl></div></div>';
                    card.innerHTML = html;
                })
                .catch(() => { card.innerHTML = '<span class="text-muted small">Metadata unavailable</span>'; });
        });

        // Load image metadata slots
        document.querySelectorAll('.image-meta-slot').forEach(function (slot) {
            const url = slot.dataset.url;
            const ref = slot.dataset.ref || '';
            if (!url) return;

            fetch(url, { credentials: 'same-origin' })
                .then(r => r.json())
                .then(json => {
                    if (!json.data) return;
                    const meta = json.data;
                    let html = '<div class="small" style="font-size:.75rem;">';
                    if (meta.width && meta.height) html += '<span class="text-muted">' + meta.width + ' x ' + meta.height + '</span> ';
                    if (meta.format) html += '<span class="badge text-bg-secondary" style="font-size:.65rem;">' + meta.format + '</span> ';
                    if (meta.size) html += '<span class="text-muted">' + (meta.size / 1024).toFixed(1) + ' KB</span>';
                    html += '</div>';
                    slot.innerHTML = html;
                })
                .catch(() => {});
        });
    });
})();

// Image diff mode switcher
function setImageDiffMode(containerId, mode, btn) {
    const container = document.getElementById(containerId);
    if (!container) return;

    container.querySelectorAll('.image-diff-view').forEach(v => v.classList.add('d-none'));
    const target = container.querySelector('[data-view="' + mode + '"]');
    if (target) target.classList.remove('d-none');

    container.querySelectorAll('[data-mode]').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

function diffExpandAll(btn) {
    const container = btn.closest('.tab-pane') || document;
    container.querySelectorAll('.diff-file-card .collapse').forEach(function (el) {
        bootstrap.Collapse.getOrCreateInstance(el).show();
    });
}

function diffCollapseAll(btn) {
    const container = btn.closest('.tab-pane') || document;
    container.querySelectorAll('.diff-file-card .collapse').forEach(function (el) {
        bootstrap.Collapse.getOrCreateInstance(el).hide();
    });
}
</script>
