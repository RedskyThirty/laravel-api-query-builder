<?php

namespace App\Http\Resources;

use RedskyEnvision\ApiQueryBuilder\Resources\ApiResource;

/**
 * Class PostResource
 *
 * @mixin \App\Models\Post
 * @package App\Http\Resources
 */
class PostResource extends ApiResource {
	/**
	 * @return list<string>
	 */
	protected function defaultFields(): array {
		return ['id', 'title', 'description', 'excerpt', 'published_at'];
	}

	/**
	 * @return array
	 */
	protected function data(): array {
		return [
			'id' => $this->id,
			'title' => $this->title,
			'description' => $this->description,
			'excerpt' => $this->excerpt,
			'comments' => $this->whenLoaded('comments', fn () => CommentResource::collection($this->comments)),
			'published_at' => $this->published_at,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at
		];
	}
}
