<?php

namespace App\DTOs;

use RedskyEnvision\ApiQueryBuilder\Contracts\ApiResourceable;
use Carbon\Carbon;

/**
 * Class WeatherDto
 *
 * Represents a weather report for a given location.
 * Used to demonstrate ApiFieldResolver with a DTO-backed resource.
 *
 * @package App\DTOs
 */
class WeatherDto implements ApiResourceable {
	public function __construct(
		public readonly string $location,
		public readonly string $condition,
		public readonly float $temperature_c,
		public readonly float $temperature_f,
		public readonly int $humidity,
		public readonly float $wind_kph,
		public readonly string $wind_direction,
		public readonly Carbon $recorded_at,
	) {
		//
	}

	/**
	 * @return string
	 */
	public function getTable(): string {
		return 'weather';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getAttributes(): array {
		return [
			'location' => $this->location,
			'condition' => $this->condition,
			'temperature_c' => $this->temperature_c,
			'temperature_f' => $this->temperature_f,
			'humidity' => $this->humidity,
			'wind_kph' => $this->wind_kph,
			'wind_direction' => $this->wind_direction,
			'recorded_at' => $this->recorded_at
		];
	}
}
