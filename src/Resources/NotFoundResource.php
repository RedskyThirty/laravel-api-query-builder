<?php

namespace RedskyEnvision\ApiQueryBuilder\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class NotFoundResource
 *
 * @package RedskyEnvision\ApiQueryBuilder\Resources
 */
class NotFoundResource extends JsonResource {
	/**
	 * @param ...$parameters
	 * @return self
	 */
	public static function make(...$parameters): self {
		return new static(null);
	}
	
	/**
	 * @param $request
	 * @return JsonResponse
	 */
	public function toResponse($request): JsonResponse {
		return parent::toResponse($request)
			->setStatusCode(404, 'Resource not found')
			->setData([
				'data' => null
			]);
	}
}
