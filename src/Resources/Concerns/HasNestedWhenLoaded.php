<?php

namespace RedskyEnvision\ApiQueryBuilder\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Trait HasNestedWhenLoaded
 *
 * @package RedskyEnvision\ApiQueryBuilder\Resources\Concerns
 */
trait HasNestedWhenLoaded {
	/**
	 * Works like whenLoaded() but supports nested relations (dot notation).
	 *
	 * @param string $relation
	 * @param callable $callback
	 * @param mixed|null $default
	 * @return mixed
	 */
	protected function whenNestedLoaded(string $relation, callable $callback, mixed $default = null): mixed {
		$segments = explode('.', $relation);
		$current = $this->resource;
		
		foreach ($segments as $segment) {
			if (!$current instanceof Model || !$current->relationLoaded($segment)) {
				return value($default);
			}
			
			$current = $current->{$segment};
		}
		
		return $callback();
	}
}
