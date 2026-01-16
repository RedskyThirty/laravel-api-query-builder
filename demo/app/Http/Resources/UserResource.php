<?php

namespace App\Http\Resources;

use RedskyEnvision\ApiQueryBuilder\Resources\ApiResource;

/**
 * Class UserResource
 *
 * @mixin \App\Models\User
 * @package App\Http\Resources
 */
class UserResource extends ApiResource {
	/**
	 * @return list<string>
	 */
	protected function defaultFields(): array {
		return ['id', 'email', 'profile'];
	}

	/**
	 * @return array
	 */
	protected function data(): array {
		return [
			'id' => $this->id,
			'email' => $this->email,
			'profile' => $this->whenLoaded('profile', fn () => ProfileResource::make($this->profile)),
			'addresses' => $this->whenLoaded('addresses', fn () => AddressResource::collection($this->addresses)),
			'posts' => $this->whenLoaded('posts', fn () => PostResource::collection($this->posts)),
			'email_verified_at' => $this->email_verified_at,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at
		];
	}
}
