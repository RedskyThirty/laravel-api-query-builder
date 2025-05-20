<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Class ProfileFactory
 *
 * @package Database\Factories
 */
class ProfileFactory extends Factory {
	/**
	 * Define the model's default state.
	 *
	 * @return array<string, mixed>
	 */
	public function definition() {
		return [
			'firstname' => fake()->firstName(),
			'lastname' => fake()->lastName(),
			'summary' => fake()->text(255),
		];
	}
}
