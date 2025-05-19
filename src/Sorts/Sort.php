<?php

namespace RedskyEnvision\ApiQueryBuilder\Sorts;

use InvalidArgumentException;

/**
 * Class Sort
 *
 * @package RedskyEnvision\ApiQueryBuilder\Sorts
 */
class Sort {
	/**
	 * @param string $column
	 * @param string $direction asc | desc
	 */
	public function __construct(
		public readonly string $column,
		public readonly string $direction = 'asc'
	) {
		if (!in_array(strtolower($direction), ['asc', 'desc'])) {
			throw new InvalidArgumentException('Invalid sort direction "'.$direction.'". Must be "asc" or "desc".');
		}
	}

	/**
	 * @param string $column
	 * @param string $direction asc | desc
	 * @return self
	 */
	public static function make(string $column, string $direction = 'asc'): self {
		return new static($column, $direction);
	}
}
