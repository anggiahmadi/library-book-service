<?php

namespace Database\Factories;

use App\Models\Book;
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
            'author_id' => \App\Models\Author::factory(),
            'isbn' => fake()->unique()->numerify('978############'),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'replacement_price' => fake()->numberBetween(50000, 500000),
            'published_year' => fake()->numberBetween(2000, 2026),
        ];
    }
}
