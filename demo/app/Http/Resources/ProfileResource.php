<?php

namespace App\Http\Resources;

use RedskyEnvision\ApiQueryBuilder\Resources\ApiResource;

/**
 * Class ProfileResource
 *
 * @mixin \App\Models\Profile
 * @package App\Http\Resources
 */
class ProfileResource extends ApiResource {
	/**
	 * @return list<string>
	 */
	protected function defaultFields(): array {
		return ['id', 'firstname', 'lastname', 'summary'];
	}

	/**
	 * @return array
	 */
	protected function data(): array {
		return [
			'id' => $this->id,
			'firstname' => $this->firstname,
			'lastname' => $this->lastname,
			'summary' => $this->summary,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at
		];
	}
}
