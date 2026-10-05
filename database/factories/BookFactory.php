<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->realText(20),
            'author' => fake()->name(),
            'isbn' => str_replace('-', '', fake()->boolean() ? fake()->unique()->isbn13() : fake()->unique()->isbn10()),
            'published_date' => fake()->date(),
            'description' => fake()->optional()->realText(100),
            'image_url' => fake()->optional()->imageUrl(),
        ];
    }
}
