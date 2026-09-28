<?php

namespace Database\Factories;

use App\Models\Genre;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Genre>
 */
class GenreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                '小説・文学',
                'ビジネス・経済',
                'コンピュータ・IT',
                '趣味・実用',
                'コミック・マンガ',
                '雑誌',
            ]),
        ];
    }
}
