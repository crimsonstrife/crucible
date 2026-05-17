<?php

namespace Database\Factories;

use App\Enums\ReleaseCategory;
use App\Models\ReleaseEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReleaseEntry>
 */
class ReleaseEntryFactory extends Factory
{
    protected $model = ReleaseEntry::class;

    public function definition(): array
    {
        return [
            'category' => fake()->randomElement(ReleaseCategory::cases())->value,
            'description' => fake()->sentence(),
            'position' => 0,
        ];
    }
}
