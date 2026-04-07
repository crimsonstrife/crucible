<?php

namespace App\Console\Commands;

use App\Models\SshKey;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * crucible:ssh-setup
 *
 * Prints the exact sshd configuration and shell commands needed to wire up
 * SSH git transport for this Crucible installation.  Makes NO changes itself —
 * it is a read-only diagnostic / setup guide.
 */
class SshSetupCommand extends Command
{
    protected $signature = 'crucible:ssh-setup
                            {--check : Verify prerequisites instead of printing setup instructions}';

    protected $description = 'Print sshd configuration required for SSH git transport';

    public function handle(): int
    {
        if ($this->option('check')) {
            return $this->runChecks();
        }

        return $this->printSetup();
    }

    private function printSetup(): int
    {
        $sshUser     = config('crucible.ssh.system_user', 'git');
        $phpBinary   = $this->resolvedPhpBinary();
        $artisanPath = $this->resolvedArtisanPath();
        $appUrl      = rtrim((string) config('app.url'), '/');
        $host        = parse_url($appUrl, PHP_URL_HOST) ?? 'your-server';

        $authorizedKeysCmd = "{$phpBinary} {$artisanPath} crucible:authorized-keys %f";
        $gitShellCmd       = "{$phpBinary} {$artisanPath} crucible:git-shell";

        $this->newLine();
        $this->line('<fg=yellow;options=bold>══════════════════════════════════════════════════════</>');
        $this->line('<fg=yellow;options=bold>  Crucible — SSH Git Transport Setup</>');
        $this->line('<fg=yellow;options=bold>══════════════════════════════════════════════════════</>');
        $this->newLine();

        // ── Step 1: system user ───────────────────────────────────────────────
        $this->line('<options=bold>Step 1 — Create the git system user</>');
        $this->newLine();
        $this->line("  <fg=cyan>useradd --system --create-home --shell /bin/bash --home-dir /home/{$sshUser} {$sshUser}</>");
        $this->newLine();
        $this->comment('  The git user needs read access to the application and repositories.');
        $this->line("  <fg=cyan>usermod -aG www-data {$sshUser}</>  # or whatever group owns the app");
        $this->newLine();

        // ── Step 2: sshd_config ───────────────────────────────────────────────
        $this->line('<options=bold>Step 2 — Add to /etc/ssh/sshd_config</>');
        $this->newLine();

        $sshdBlock = <<<SSHD
            Match User {$sshUser}
                AuthorizedKeysCommand {$authorizedKeysCmd}
                AuthorizedKeysCommandUser {$sshUser}
                AllowAgentForwarding no
                AllowTcpForwarding no
                X11Forwarding no
                PermitTTY no
            SSHD;

        foreach (explode("\n", $sshdBlock) as $line) {
            $this->line('  <fg=cyan>'.rtrim($line).'</>');
        }

        $this->newLine();
        $this->comment('  Then restart sshd:');
        $this->line('  <fg=cyan>systemctl restart sshd</>');
        $this->newLine();

        // ── Step 3: permissions ───────────────────────────────────────────────
        $this->line('<options=bold>Step 3 — Ensure the git user can read the artisan script</>');
        $this->newLine();
        $this->line("  <fg=cyan>chmod +x {$artisanPath}</>");
        $this->newLine();

        // ── Step 4: env vars ──────────────────────────────────────────────────
        $this->line('<options=bold>Step 4 — Set environment variables</>');
        $this->newLine();
        $this->line("  <fg=cyan>CRUCIBLE_SSH_USER={$sshUser}</>");
        if (PHP_BINARY !== $phpBinary) {
            $this->line("  <fg=cyan>CRUCIBLE_SSH_PHP_BINARY={$phpBinary}</>");
        }
        $this->newLine();

        // ── Step 5: verify ────────────────────────────────────────────────────
        $this->line('<options=bold>Step 5 — Verify</>');
        $this->newLine();
        $this->line('  <fg=cyan>php artisan crucible:ssh-setup --check</>');
        $this->newLine();

        // ── Clone URL format ──────────────────────────────────────────────────
        $this->line('<options=bold>Clone URL format</>');
        $this->newLine();
        $this->line("  <fg=green>git@{$host}:{<org>}/{<repo>}.git</>");
        $this->newLine();

        // ── How it works ──────────────────────────────────────────────────────
        $this->line('<options=bold>How it works</>');
        $this->newLine();
        $this->line('  1. Client runs: git clone git@'.$host.':org/repo.git');
        $this->line('  2. sshd calls:  AuthorizedKeysCommand with the key fingerprint (%f)');
        $this->line("  3. Crucible returns a forced-command entry pointing to crucible:git-shell");
        $this->line('  4. sshd execs the forced command instead of a real shell');
        $this->line("  5. crucible:git-shell checks permissions then execs git-upload-pack or git-receive-pack");
        $this->newLine();

        $this->line('<fg=yellow>Note:</> Keys registered before the SHA256 padding fix may need to be');
        $this->line('      re-registered via the SSH Keys settings page.');
        $this->newLine();

        return self::SUCCESS;
    }

    private function runChecks(): int
    {
        $this->newLine();
        $this->line('<options=bold>Crucible SSH Transport — Prerequisite Checks</>');
        $this->newLine();

        $pass = true;

        // PHP binary executable
        $phpBin = $this->resolvedPhpBinary();
        $pass   = $this->check('PHP binary is executable', is_executable($phpBin), $phpBin) && $pass;

        // artisan is readable
        $artisan = $this->resolvedArtisanPath();
        $pass    = $this->check('artisan script exists', file_exists($artisan), $artisan) && $pass;

        // pcntl_exec available (preferred exec method)
        $hasPcntl = function_exists('pcntl_exec');
        $this->check(
            'pcntl_exec available (preferred)',
            $hasPcntl,
            $hasPcntl ? 'yes' : 'no — will fall back to proc_open (still works)',
            ! $hasPcntl, // warn but don't fail
        );

        // proc_open available (fallback)
        $pass = $this->check('proc_open available', function_exists('proc_open'), 'yes') && $pass;

        // Repos path writable
        $reposPath = (string) config('crucible.git.repos_path', '');
        $pass      = $this->check(
            'CRUCIBLE_REPOS_PATH exists',
            is_dir($reposPath),
            $reposPath ?: '(not set)',
        ) && $pass;

        // SSH keys in DB
        try {
            $keyCount = SshKey::count();
            $this->check('DB reachable', true, "{$keyCount} SSH key(s) registered");
        } catch (\Throwable) {
            $this->check('DB reachable', false, 'cannot query ssh_keys table');
            $pass = false;
        }

        // git binary
        $gitBin = (new \Symfony\Component\Process\ExecutableFinder())->find('git');
        $pass   = $this->check('git binary found', $gitBin !== null, $gitBin ?? 'not found') && $pass;

        $this->newLine();

        if ($pass) {
            $this->info('All checks passed. Run without --check to see sshd setup instructions.');
        } else {
            $this->error('Some checks failed. Review the output above.');
        }

        $this->newLine();

        return $pass ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Print a single check result line.
     *
     * @param  bool  $warnOnly  Show as warning instead of failure when $result is false.
     */
    private function check(string $label, bool $result, string $detail = '', bool $warnOnly = false): bool
    {
        $icon   = $result ? '<fg=green>✓</>' : ($warnOnly ? '<fg=yellow>!</>' : '<fg=red>✗</>');
        $status = $result ? '<fg=green>PASS</>' : ($warnOnly ? '<fg=yellow>WARN</>' : '<fg=red>FAIL</>');
        $extra  = $detail !== '' ? "  <fg=gray>{$detail}</>" : '';

        $this->line("  {$icon} {$status}  {$label}{$extra}");

        return $result || $warnOnly;
    }

    private function resolvedPhpBinary(): string
    {
        $configured = (string) config('crucible.ssh.php_binary', '');

        return $configured !== '' ? $configured : PHP_BINARY;
    }

    private function resolvedArtisanPath(): string
    {
        $configured = (string) config('crucible.ssh.artisan_path', '');

        return $configured !== '' ? $configured : base_path('artisan');
    }
}
