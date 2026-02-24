<?php

namespace App\Http\Resources;

use App\DTOs\WeatherDto;
use RedskyEnvision\ApiQueryBuilder\Resources\ApiDtoResource;

/**
 * Class WeatherDtoResource
 *
 * Resource for WeatherDto. Demonstrates ApiDtoResource usage with ApiFieldResolver.
 *
 * @mixin WeatherDto
 * @package App\Http\Resources
 */
class WeatherDtoResource extends ApiDtoResource {
	/**
	 * @return list<string>
	 */
	protected function defaultFields(): array {
		return ['location', 'condition', 'temperature_c', 'humidity', 'recorded_at'];
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function data(): array {
		return [
			'location' => $this->dto()->location,
			'condition' => $this->dto()->condition,
			'temperature_c' => $this->dto()->temperature_c,
			'temperature_f' => $this->dto()->temperature_f,
			'humidity' => $this->dto()->humidity,
			'wind_kph' => $this->dto()->wind_kph,
			'wind_direction' => $this->dto()->wind_direction,
			'recorded_at' => $this->dto()->recorded_at
		];
	}
}
