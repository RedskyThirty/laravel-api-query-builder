<?php

namespace App\Http\Resources;

use RedskyEnvision\ApiQueryBuilder\Resources\ApiResource;

/**
 * Class AddressResource
 *
 * @mixin \App\Models\Address
 * @package App\Http\Resources
 */
class AddressResource extends ApiResource {
	/**
	 * @return list<string>
	 */
	protected function defaultFields(): array {
		return ['id', 'street', 'zip', 'locality'];
	}

	/**
	 * @return array
	 */
	protected function data(): array {
		return [
			'id' => $this->id,
			'street' => $this->street,
			'zip' => $this->zip,
			'locality' => $this->locality,
			'formatted_address' => $this->formatted_address,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at
		];
	}
}
