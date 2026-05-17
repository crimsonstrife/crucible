<?php

namespace Database\Factories;

use App\Models\ReleaseToken;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReleaseToken>
 */
class ReleaseTokenFactory extends Factory
{
    protected $model = ReleaseToken::class;

    public function definition(): array
    {
        $raw = ReleaseToken::TOKEN_PREFIX.Str::random(ReleaseToken::TOKEN_RANDOM_LENGTH);

        return [
            'name' => fake()->words(3, true),
            'token_hash' => hash('sha256', $raw),
            'token_prefix' => substr($raw, 0, ReleaseToken::DISPLAY_PREFIX_LENGTH),
            'last_used_at' => null,
            'expires_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }
}
