<?php

namespace App\Services;

use App\Models\Repository;
use App\Models\SshKey;
use App\Models\User;
use RuntimeException;

class SshKeyService
{
    public function add(User $user, string $title, string $publicKey, bool $isDeployKey = false, ?Repository $deployRepo = null): SshKey
    {
        if (!$this->isValidPublicKey($publicKey)) {
            throw new RuntimeException('Invalid SSH public key format.');
        }

        $fingerprint = $this->calculateFingerprint($publicKey);

        return $user->sshKeys()->create([
            'title'          => $title,
            'fingerprint'    => $fingerprint,
            'public_key'     => trim($publicKey),
            'key_type'       => $this->extractKeyType($publicKey),
            'is_deploy_key'  => $isDeployKey,
            'deploy_repo_id' => $deployRepo?->id,
        ]);
    }

    public function remove(User $user, SshKey $key): void
    {
        if ($key->user_id !== $user->id) {
            throw new RuntimeException('You do not own this SSH key.');
        }

        $key->delete();
    }

    public function calculateFingerprint(string $publicKey): string
    {
        $parts = explode(' ', trim($publicKey));
        $keyData = $parts[1] ?? '';

        if (empty($keyData)) {
            throw new RuntimeException('Cannot extract key data for fingerprint.');
        }

        $decoded = base64_decode($keyData, strict: true);

        if ($decoded === false) {
            throw new RuntimeException('Invalid base64 key data.');
        }

        // OpenSSH fingerprints use unpadded base64 (no trailing '=').
        return 'SHA256:' . rtrim(base64_encode(hash('sha256', $decoded, true)), '=');
    }

    public function isValidPublicKey(string $publicKey): bool
    {
        $parts = explode(' ', trim($publicKey));
        $validTypes = ['ssh-rsa', 'ssh-ed25519', 'ecdsa-sha2-nistp256', 'ecdsa-sha2-nistp384', 'ecdsa-sha2-nistp521', 'sk-ssh-ed25519@openssh.com'];

        return count($parts) >= 2 && in_array($parts[0], $validTypes, true);
    }

    protected function extractKeyType(string $publicKey): string
    {
        return explode(' ', trim($publicKey))[0] ?? 'unknown';
    }
}
