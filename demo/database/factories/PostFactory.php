<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Class PostFactory
 *
 * @package Database\Factories
 */
class PostFactory extends Factory {
	/**
	 * Define the model's default state.
	 *
	 * @return array<string, mixed>
	 */
	public function definition() {
		return [
			'title' => fake()->sentence(5),
			'description' => fake()->sentences(rand(4, 8), true),
			'published_at' => Carbon::now()->subHours(rand(24, 480))
		];
	}
}
