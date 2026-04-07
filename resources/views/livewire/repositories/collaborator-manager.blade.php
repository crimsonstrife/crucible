<div class="card shadow-sm mt-4">
    <div class="card-header">
        <h6 class="mb-0">Manage Collaborators</h6>
    </div>
    <div class="card-body">
        <form wire:submit="addCollaborator" class="row g-3 align-items-end mb-4">
            <div class="col-md-6">
                <label for="collaborator-user" class="form-label">Organization Member</label>
                <select id="collaborator-user" class="form-select @error('userId') is-invalid @enderror" wire:model="userId">
                    <option value="">Select a member</option>
                    @foreach ($availableUsers as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
                @error('userId')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="collaborator-role" class="form-label">Role</label>
                <select id="collaborator-role" class="form-select @error('role') is-invalid @enderror" wire:model="role">
                    @foreach ($roles as $collaboratorRole)
                        <option value="{{ $collaboratorRole->value }}">{{ $collaboratorRole->label() }}</option>
                    @endforeach
                </select>
                @error('role')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100" @disabled($availableUsers->isEmpty())>
                    Add
                </button>
            </div>
        </form>

        @if ($collaborators->isEmpty())
            <p class="text-muted mb-0">No explicit collaborators yet.</p>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($collaborators as $collaborator)
                            <tr>
                                <td>
                                    <div class="fw-medium">{{ $collaborator->name }}</div>
                                    <div class="text-muted small">{{ $collaborator->email }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-auto border text-uppercase">
                                        {{ $collaborator->pivot->role }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        wire:click="removeCollaborator('{{ $collaborator->id }}')"
                                        wire:confirm="Remove this collaborator?"
                                    >
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
