<?php

namespace App\Console\Commands;

use App\Models\AppToken;
use Illuminate\Console\Command;

class AppTokenCreate extends Command
{
    protected $signature = 'app-token:create
                            {name : A descriptive name for the token (for example "Forge")}
                            {--abilities=* : Specific abilities to grant (default: all)}';

    protected $description = 'Generate a new system-level app token for server-to-server API access';

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $abilities = (array) $this->option('abilities') ?: ['*'];

        ['plaintext' => $plaintext, 'token' => $token] = AppToken::generate($name, $abilities);

        $this->newLine();
        $this->info("App token created: <comment>{$token->name}</comment>");
        $this->newLine();
        $this->line('  <fg=yellow>Token (copy now - this will not be shown again):</>');
        $this->line("  <fg=cyan>{$plaintext}</>");
        $this->newLine();
        $this->line('  Abilities : ' . implode(', ', $token->abilities));
        $this->line("  Token ID  : {$token->id}");
        $this->newLine();
        $this->comment('Set this value as CRUCIBLE_APP_TOKEN in your Forge .env file.');
        $this->newLine();

        return self::SUCCESS;
    }
}
