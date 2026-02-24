<?php

namespace RedskyEnvision\ApiQueryBuilder\Resources;

use RedskyEnvision\ApiQueryBuilder\Contracts\ApiResourceable;

/**
 * Class ApiDtoResource
 *
 * Abstract resource class for DTO-backed resources.
 * Extends ApiResource and overrides Eloquent-specific resolution methods
 * to delegate to the ApiResourceable contract instead.
 *
 * Subclasses must still implement defaultFields() and data().
 *
 * @package RedskyEnvision\ApiQueryBuilder\Resources
 */
abstract class ApiDtoResource extends ApiResource {
	/**
	 * @return string
	 */
	protected function resolveTable(): string {
		return $this->dto()->getTable();
	}
	
	/**
	 * @return array<string, mixed>
	 */
	protected function resolveAttributes(): array {
		return $this->dto()->getAttributes();
	}
	
	/**
	 * Returns the underlying DTO, typed via the ApiResourceable contract.
	 *
	 * @return ApiResourceable
	 */
	protected function dto(): ApiResourceable {
		/** @var ApiResourceable */
		return $this->resource;
	}
}
