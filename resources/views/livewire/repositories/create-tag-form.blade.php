<div class="mb-3">
    @can('push', $repository)
        <div class="d-flex justify-content-end mb-2">
            <button type="button" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1"
                    wire:click="toggle">
                <span>{{ $open ? 'Cancel' : '+ New tag' }}</span>
            </button>
        </div>

        @if ($open)
            <div class="card shadow-sm">
                <div class="card-body">
                    @if ($createdTag)
                        <div class="alert alert-success d-flex align-items-center justify-content-between mb-3">
                            <div>
                                Tag <code>{{ $createdTag }}</code> created.
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <a href="{{ route('repositories.releases.create', [$repository->organization, $repository]) }}?tag={{ urlencode($createdTag) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    Draft release from this tag
                                </a>
                                <button type="button" class="btn-close btn-close-sm" aria-label="Dismiss"
                                        wire:click="dismissSuccess"></button>
                            </div>
                        </div>
                    @endif

                    <form wire:submit="createTag" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="tag-name" class="form-label">Tag name</label>
                            <input id="tag-name" type="text"
                                   class="form-control font-monospace @error('tagName') is-invalid @enderror"
                                   wire:model="tagName" placeholder="v1.0.0" autocomplete="off">
                            @error('tagName')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label for="from-ref" class="form-label">From branch</label>
                            <select id="from-ref"
                                    class="form-select @error('fromRef') is-invalid @enderror"
                                    wire:model="fromRef">
                                @forelse ($this->branches as $branch)
                                    <option value="{{ $branch }}">{{ $branch }}</option>
                                @empty
                                    <option value="">No branches</option>
                                @endforelse
                            </select>
                            @error('fromRef')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label for="tag-message" class="form-label">Message <small class="text-muted">(optional, annotated tag)</small></label>
                            <input id="tag-message" type="text"
                                   class="form-control @error('message') is-invalid @enderror"
                                   wire:model="message" placeholder="Release 1.0">
                            @error('message')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Create tag</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endcan
</div>
