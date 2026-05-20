<div>
    <form wire:submit="save" class="card shadow-sm">
        <div class="card-body">

            {{-- Tag --}}
            <div class="mb-3">
                <label for="release-tag" class="form-label">Tag</label>
                @if ($release === null && count($this->availableTags) > 0)
                    <select id="release-tag"
                            class="form-select font-monospace @error('tagName') is-invalid @enderror"
                            wire:model="tagName">
                        <option value="">Choose a tag…</option>
                        @foreach ($this->availableTags as $tag)
                            <option value="{{ $tag['name'] }}">
                                {{ $tag['name'] }} &mdash; {{ substr($tag['sha'], 0, 8) }}
                                @if ($tag['subject'])
                                    &middot; {{ Str::limit($tag['subject'], 60) }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                @else
                    <input id="release-tag" type="text"
                           class="form-control font-monospace @error('tagName') is-invalid @enderror"
                           wire:model="tagName" placeholder="v1.0.0" autocomplete="off"
                           @if ($release !== null) disabled @endif>
                @endif
                @error('tagName')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                @if ($release === null && count($this->availableTags) === 0)
                    <div class="form-text">
                        No unreleased tags found. Create one on the
                        <a href="{{ route('repositories.tags', [$repository->organization, $repository]) }}">Tags page</a>.
                    </div>
                @endif
            </div>

            {{-- Name --}}
            <div class="mb-3">
                <label for="release-name" class="form-label">Release title <small class="text-muted">(optional, defaults to the tag name)</small></label>
                <input id="release-name" type="text"
                       class="form-control @error('name') is-invalid @enderror"
                       wire:model="name" placeholder="e.g. Big Update">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            {{-- Body --}}
            <div class="mb-3">
                <label for="release-body" class="form-label">Description <small class="text-muted">(Markdown supported)</small></label>
                <textarea id="release-body" rows="6"
                          class="form-control font-monospace @error('body') is-invalid @enderror"
                          wire:model="body" placeholder="## Highlights&#10;..."></textarea>
                @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            {{-- Categorized entries --}}
            <div class="mb-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <label class="form-label mb-0">Changelog entries <small class="text-muted">(optional)</small></label>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Add entry">
                        @foreach ($this->categories as $cat)
                            <button type="button" class="btn btn-outline-secondary"
                                    wire:click="addEntry('{{ $cat->value }}')">
                                + {{ $cat->label() }}
                            </button>
                        @endforeach
                    </div>
                </div>

                @if (empty($entries))
                    <p class="text-muted small mb-0">No entries yet. Click a category above to add one.</p>
                @else
                    <div class="d-flex flex-column gap-2">
                        @foreach ($entries as $i => $entry)
                            <div class="input-group" wire:key="entry-{{ $i }}">
                                <select class="form-select form-select-sm flex-shrink-1" style="max-width: 140px;"
                                        wire:model="entries.{{ $i }}.category">
                                    @foreach ($this->categories as $cat)
                                        <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                                    @endforeach
                                </select>
                                <input type="text"
                                       class="form-control form-control-sm @error('entries.'.$i.'.description') is-invalid @enderror"
                                       wire:model="entries.{{ $i }}.description"
                                       placeholder="Describe the change…">
                                <button type="button" class="btn btn-outline-danger btn-sm"
                                        wire:click="removeEntry({{ $i }})">
                                    &times;
                                </button>
                                @error('entries.'.$i.'.description')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- External links --}}
            <div class="mb-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <label class="form-label mb-0">External links <small class="text-muted">(Steam, itch.io, Discord, etc.)</small></label>
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            wire:click="addLink('other')">
                        + Add link
                    </button>
                </div>

                @if (empty($links))
                    <p class="text-muted small mb-0">No links yet. Add buttons that point to where your build, demo, or community lives.</p>
                @else
                    <div class="d-flex flex-column gap-2">
                        @foreach ($links as $i => $link)
                            <div class="input-group" wire:key="link-{{ $i }}">
                                <select class="form-select form-select-sm flex-shrink-1" style="max-width: 140px;"
                                        wire:model="links.{{ $i }}.platform">
                                    @foreach ($this->platforms as $platform)
                                        <option value="{{ $platform->value }}">{{ $platform->label() }}</option>
                                    @endforeach
                                </select>
                                <input type="text"
                                       class="form-control form-control-sm @error('links.'.$i.'.label') is-invalid @enderror"
                                       wire:model="links.{{ $i }}.label"
                                       style="max-width: 220px;"
                                       placeholder="Button label">
                                <input type="url"
                                       class="form-control form-control-sm font-monospace @error('links.'.$i.'.url') is-invalid @enderror"
                                       wire:model="links.{{ $i }}.url"
                                       placeholder="https://">
                                <button type="button" class="btn btn-outline-danger btn-sm"
                                        wire:click="removeLink({{ $i }})">
                                    &times;
                                </button>
                                @error('links.'.$i.'.label')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                @error('links.'.$i.'.url')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Toggles --}}
            <div class="mb-3 d-flex gap-4">
                <div class="form-check">
                    <input id="is-draft" type="checkbox" class="form-check-input"
                           wire:model="isDraft">
                    <label for="is-draft" class="form-check-label">
                        Save as draft
                        <small class="d-block text-muted">Drafts are not visible publicly or in the changelog API.</small>
                    </label>
                </div>
                <div class="form-check">
                    <input id="is-prerelease" type="checkbox" class="form-check-input"
                           wire:model="isPrerelease">
                    <label for="is-prerelease" class="form-check-label">
                        Pre-release
                        <small class="d-block text-muted">Pre-releases are listed but never marked as the latest.</small>
                    </label>
                </div>
            </div>
        </div>

        <div class="card-footer d-flex justify-content-between align-items-center">
            <div>
                @if ($release !== null)
                    <button type="button" class="btn btn-sm btn-outline-danger"
                            wire:click="delete"
                            wire:confirm="Permanently delete this release?">
                        Delete release
                    </button>
                @endif
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('repositories.releases.index', [$repository->organization, $repository]) }}"
                   class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    {{ $release === null ? 'Create release' : 'Save changes' }}
                </button>
            </div>
        </div>
    </form>
</div>
