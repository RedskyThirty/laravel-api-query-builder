<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Class AddressFactory
 *
 * @package Database\Factories
 */
class AddressFactory extends Factory {
	/**
	 * Define the model's default state.
	 *
	 * @return array<string, mixed>
	 */
	public function definition() {
		return [
			'street' => fake()->streetAddress(),
			'zip' => fake()->postcode(),
			'locality' => fake()->city()
		];
	}
}
