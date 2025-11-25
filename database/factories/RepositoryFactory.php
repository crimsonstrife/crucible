<?php

namespace Database\Factories;

use App\Enums\RepositoryVisibility;
use App\Models\Organization;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Repository>
 */
class RepositoryFactory extends Factory
{
    protected $model = Repository::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->word().'-'.fake()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'organization_id' => Organization::factory(),
            'visibility' => fake()->randomElement(RepositoryVisibility::cases()),
            'default_branch' => 'main',
        ];
    }

    /**
     * Create a public repository.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => RepositoryVisibility::Public,
        ]);
    }

    /**
     * Create a private repository.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => RepositoryVisibility::Private,
        ]);
    }

    /**
     * Create an internal repository.
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => RepositoryVisibility::Internal,
        ]);
    }
}
