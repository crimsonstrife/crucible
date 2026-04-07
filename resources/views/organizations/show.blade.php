<x-app-layout>
    <div class="container">

        {{-- Header --}}
        <div class="row mb-3">
            <div class="col">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('organizations.index') }}">Organizations</a></li>
                        <li class="breadcrumb-item active">{{ $organization->name }}</li>
                    </ol>
                </nav>
                <h1 class="h3 fw-bold mb-0">{{ $organization->name }}</h1>
                @if ($organization->description)
                    <p class="text-muted mt-1 mb-0">{{ $organization->description }}</p>
                @endif
            </div>
            <div class="col-auto d-flex align-items-start gap-2">
                @can('update', $organization)
                    <a href="{{ route('organizations.edit', $organization) }}" class="btn btn-outline-secondary btn-sm">
                        Settings
                    </a>
                @endcan
                <a href="{{ route('repositories.import', $organization) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
                    <x-octicon name="repo-clone" />
                    <span>Import</span>
                </a>
                <a href="{{ route('repositories.create', $organization) }}" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
                    <x-octicon name="repo" />
                    <span>New Repository</span>
                </a>
            </div>
        </div>

        {{-- Flash messages --}}
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ $errors->first() }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- Tabs --}}
        <ul class="nav nav-tabs mb-4" id="org-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="repos-tab" data-bs-toggle="tab" data-bs-target="#repos-pane"
                    type="button" role="tab">
                    Repositories
                </button>
            </li>
            @can('manageMember', $organization)
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="members-tab" data-bs-toggle="tab" data-bs-target="#members-pane"
                    type="button" role="tab">
                    Members
                    <span class="badge bg-secondary ms-1">{{ $organization->members->count() }}</span>
                </button>
            </li>
            @endcan
        </ul>

        <div class="tab-content" id="org-tab-content">

            {{-- Repositories tab --}}
            <div class="tab-pane fade show active" id="repos-pane" role="tabpanel">
                <livewire:repositories.repository-list :organization="$organization" />
            </div>

            {{-- Members tab --}}
            @can('manageMember', $organization)
            <div class="tab-pane fade" id="members-pane" role="tabpanel">

                {{-- Add member form --}}
                <div class="card mb-4">
                    <div class="card-header fw-semibold">Add Member</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('organizations.members.store', $organization) }}"
                              class="row g-2 align-items-end">
                            @csrf
                            <div class="col-sm-5">
                                <label for="member-email" class="form-label small fw-semibold">Email address</label>
                                <input type="email" id="member-email" name="email"
                                       class="form-control form-control-sm @error('email') is-invalid @enderror"
                                       placeholder="user@example.com"
                                       value="{{ old('email') }}" required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-sm-3">
                                <label for="member-role" class="form-label small fw-semibold">Role</label>
                                <select id="member-role" name="role" class="form-select form-select-sm">
                                    <option value="member" @selected(old('role') === 'member')>Member</option>
                                    <option value="admin"  @selected(old('role') === 'admin')>Admin</option>
                                </select>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary btn-sm">Add</button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Member list --}}
                <div class="card">
                    <div class="card-header fw-semibold">Current Members</div>
                    <div class="list-group list-group-flush">
                        @forelse ($organization->members as $member)
                            <div class="list-group-item d-flex align-items-center gap-3">
                                {{-- Avatar --}}
                                <div class="flex-shrink-0">
                                    @if ($member->profile_photo_url)
                                        <img src="{{ $member->profile_photo_url }}" alt="{{ $member->name }}"
                                             class="rounded-circle" width="36" height="36">
                                    @else
                                        <span class="d-inline-flex align-items-center justify-content-center
                                                     rounded-circle bg-secondary text-white fw-bold"
                                              style="width:36px;height:36px;font-size:.85rem;">
                                            {{ strtoupper(substr($member->name, 0, 1)) }}
                                        </span>
                                    @endif
                                </div>

                                {{-- Name + email --}}
                                <div class="flex-grow-1 min-width-0">
                                    <div class="fw-semibold text-truncate">{{ $member->name }}</div>
                                    <div class="text-muted small text-truncate">{{ $member->email }}</div>
                                </div>

                                {{-- Role badge --}}
                                <span class="badge
                                    {{ $member->pivot->role === 'owner' ? 'bg-warning ' :
                                       ($member->pivot->role === 'admin' ? 'bg-info ' : 'bg-secondary') }}">
                                    {{ ucfirst($member->pivot->role) }}
                                </span>

                                {{-- Actions (only for non-owner or when there are multiple owners) --}}
                                @php
                                    $isLastOwner = $member->pivot->role === 'owner'
                                        && $organization->members->where('pivot.role', 'owner')->count() <= 1;
                                    $isSelf = $member->id === auth()->id();
                                @endphp
                                @unless ($isLastOwner)
                                    <div class="d-flex gap-2 flex-shrink-0">
                                        {{-- Change role --}}
                                        @unless ($isSelf)
                                        <form method="POST"
                                              action="{{ route('organizations.members.update', [$organization, $member]) }}"
                                              class="d-flex gap-1">
                                            @csrf
                                            @method('PATCH')
                                            <select name="role" class="form-select form-select-sm"
                                                    style="width:auto" onchange="this.form.submit()">
                                                <option value="member" @selected($member->pivot->role === 'member')>Member</option>
                                                <option value="admin"  @selected($member->pivot->role === 'admin')>Admin</option>
                                                <option value="owner"  @selected($member->pivot->role === 'owner')>Owner</option>
                                            </select>
                                        </form>
                                        @endunless

                                        {{-- Remove --}}
                                        <form method="POST"
                                              action="{{ route('organizations.members.destroy', [$organization, $member]) }}"
                                              onsubmit="return confirm('Remove {{ $member->name }} from this organization?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Remove</button>
                                        </form>
                                    </div>
                                @endunless
                            </div>
                        @empty
                            <div class="list-group-item text-muted fst-italic">No members yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
            @endcan

        </div>
    </div>
</x-app-layout>
