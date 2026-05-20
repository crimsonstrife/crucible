<?php

namespace Database\Factories;

use App\Enums\ReleaseLinkPlatform;
use App\Models\ReleaseLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReleaseLink>
 */
class ReleaseLinkFactory extends Factory
{
    protected $model = ReleaseLink::class;

    public function definition(): array
    {
        return [
            'label' => fake()->randomElement([
                'Buy on Steam',
                'Free demo on itch.io',
                'Join our Discord',
                'Support on Patreon',
                'Watch trailer',
            ]),
            'url' => fake()->url(),
            'platform' => fake()->randomElement(ReleaseLinkPlatform::cases())->value,
            'position' => 0,
        ];
    }
}
