<div>
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0">Add SSH Key</h6></div>
        <div class="card-body">
            <form wire:submit="addKey">
                <div class="mb-3">
                    <label class="form-label">Title</label>
                    <input wire:model="title" type="text" class="form-control @error('title') is-invalid @enderror"
                           placeholder="e.g. Work MacBook">
                    @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">Public Key</label>
                    <textarea wire:model="publicKey" class="form-control @error('publicKey') is-invalid @enderror"
                              rows="4" placeholder="ssh-ed25519 AAAA..."></textarea>
                    @error('publicKey') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <button type="submit" class="btn btn-primary">Add Key</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h6 class="mb-0">Your SSH Keys</h6></div>
        <ul class="list-group list-group-flush">
            @forelse ($keys as $key)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">{{ $key->title }}</div>
                        <small class="text-muted font-monospace">{{ $key->fingerprint }}</small>
                    </div>
                    <button wire:click="removeKey('{{ $key->id }}')"
                            wire:confirm="Remove this SSH key?"
                            class="btn btn-sm btn-outline-danger">Remove</button>
                </li>
            @empty
                <li class="list-group-item text-muted">No SSH keys added yet.</li>
            @endforelse
        </ul>
    </div>
</div>
