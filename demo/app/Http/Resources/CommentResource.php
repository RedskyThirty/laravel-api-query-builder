<?php

namespace App\Http\Resources;

use RedskyEnvision\ApiQueryBuilder\Resources\ApiResource;

/**
 * Class CommentResource
 *
 * @mixin \App\Models\Comment
 * @package App\Http\Resources
 */
class CommentResource extends ApiResource {
	/**
	 * @return list<string>
	 */
	protected function defaultFields(): array {
		return ['id', 'username', 'message'];
	}

	/**
	 * @return array
	 */
	protected function data(): array {
		return [
			'id' => $this->id,
			'username' => $this->username,
			'message' => $this->message,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at
		];
	}
}
