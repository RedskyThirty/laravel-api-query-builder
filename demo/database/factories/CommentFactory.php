<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Class CommentFactory
 *
 * @package Database\Factories
 */
class CommentFactory extends Factory {
	/**
	 * Define the model's default state.
	 *
	 * @return array<string, mixed>
	 */
	public function definition() {
		return [
			'username' => fake()->userName(),
			'message' => fake()->text(255)
		];
	}
}
