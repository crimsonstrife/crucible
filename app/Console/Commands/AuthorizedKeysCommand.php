<?php

namespace App\Console\Commands;

use App\Models\SshKey;
use Illuminate\Console\Command;

/**
 * crucible:authorized-keys {fingerprint}
 *
 * Called by sshd via AuthorizedKeysCommand when a client presents an SSH key.
 * Outputs a single authorized_keys entry that forces the connecting session
 * through crucible:git-shell instead of a real shell.
 *
 * sshd_config snippet:
 *   AuthorizedKeysCommand /usr/bin/php /path/to/artisan crucible:authorized-keys %f
 *   AuthorizedKeysCommandUser git
 *
 * The %f token is replaced by sshd with the SHA256 fingerprint of the
 * presented key (e.g. "SHA256:abc123...").
 *
 * STDOUT must contain only valid authorized_keys lines — any extra output
 * will cause SSH authentication to fail.
 */
class AuthorizedKeysCommand extends Command
{
    protected $signature = 'crucible:authorized-keys
                            {fingerprint : SHA256 fingerprint of the presented SSH key (passed by sshd %f)}';

    protected $description = 'Emit an authorized_keys entry for the given key fingerprint (called by sshd)';

    public function handle(): int
    {
        $fingerprint = $this->argument('fingerprint');

        // Normalize: OpenSSH omits base64 padding; stored keys may or may not
        // have it depending on when they were registered.  Strip from both sides
        // of the comparison so old and new keys both match.
        $normalized = rtrim($fingerprint, '=');

        $key = SshKey::with('user')
            ->where(function ($query) use ($fingerprint, $normalized) {
                $query->where('fingerprint', $fingerprint)
                      ->orWhere('fingerprint', $normalized)
                      ->orWhere('fingerprint', $normalized.'=')
                      ->orWhere('fingerprint', $normalized.'==');
            })
            ->first();

        if (! $key || ! $key->user) {
            // No match — output nothing so sshd falls through to next method.
            return self::SUCCESS;
        }

        // Build the forced-command that will run in place of a shell.
        $phpBinary   = $this->resolvedPhpBinary();
        $artisanPath = $this->resolvedArtisanPath();
        $command     = sprintf(
            '%s %s crucible:git-shell %s --key-id=%s',
            $phpBinary,
            $artisanPath,
            $key->user->id,
            $key->id,
        );

        $restrictions = implode(',', [
            'command="'.$command.'"',
            'no-port-forwarding',
            'no-X11-forwarding',
            'no-agent-forwarding',
            'no-pty',
        ]);

        // Output exactly one authorized_keys line to stdout.
        // Use echo rather than $this->line() to guarantee no ANSI decoration.
        echo $restrictions.' '.$key->public_key."\n";

        return self::SUCCESS;
    }

    protected function resolvedPhpBinary(): string
    {
        $configured = (string) config('crucible.ssh.php_binary', '');

        return $configured !== '' ? $configured : PHP_BINARY;
    }

    protected function resolvedArtisanPath(): string
    {
        $configured = (string) config('crucible.ssh.artisan_path', '');

        return $configured !== '' ? $configured : base_path('artisan');
    }
}
