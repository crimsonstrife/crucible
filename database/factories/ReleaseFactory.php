<?php

namespace Database\Factories;

use App\Models\Release;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Release>
 */
class ReleaseFactory extends Factory
{
    protected $model = Release::class;

    public function definition(): array
    {
        $tag = 'v'.fake()->numberBetween(0, 5).'.'.fake()->numberBetween(0, 20).'.'.fake()->numberBetween(0, 30);

        return [
            // repository_id and author_id must be supplied by the caller.
            'tag_name' => $tag,
            'commit_sha' => Str::lower(Str::random(40)),
            'name' => fake()->sentence(3),
            'body' => fake()->paragraphs(2, true),
            'is_draft' => false,
            'is_prerelease' => false,
            'is_latest' => false,
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['is_draft' => true, 'published_at' => null]);
    }

    public function prerelease(): static
    {
        return $this->state(['is_prerelease' => true]);
    }
}
