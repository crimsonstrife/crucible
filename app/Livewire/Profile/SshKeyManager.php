<?php

namespace App\Livewire\Profile;

use App\Services\SshKeyService;
use Livewire\Component;

class SshKeyManager extends Component
{
    public string $title = '';
    public string $publicKey = '';
    public bool $isDeployKey = false;

    public function addKey(SshKeyService $sshKeyService): void
    {
        $this->validate([
            'title'     => 'required|string|max:100',
            'publicKey' => 'required|string',
        ]);

        try {
            $sshKeyService->add(auth()->user(), $this->title, $this->publicKey, $this->isDeployKey);
            $this->reset(['title', 'publicKey', 'isDeployKey']);
            session()->flash('success', 'SSH key added.');
        } catch (\Exception $e) {
            $this->addError('publicKey', $e->getMessage());
        }
    }

    public function removeKey(string $keyId, SshKeyService $sshKeyService): void
    {
        $key = auth()->user()->sshKeys()->findOrFail($keyId);
        $sshKeyService->remove(auth()->user(), $key);
        session()->flash('success', 'SSH key removed.');
    }

    public function render()
    {
        $keys = auth()->user()->sshKeys()->latest()->get();
        return view('livewire.profile.ssh-key-manager', compact('keys'));
    }
}
