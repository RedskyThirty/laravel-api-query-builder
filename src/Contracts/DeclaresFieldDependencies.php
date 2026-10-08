<?php

namespace RedskyEnvision\ApiQueryBuilder\Contracts;

/**
 * Interface DeclaresFieldDependencies
 *
 * Declares which fields must be selected alongside a requested field
 * (e.g. an accessor that reads other attributes).
 *
 * @package RedskyEnvision\ApiQueryBuilder\Contracts
 */
interface DeclaresFieldDependencies {
	/**
	 * Dependencies are resolved transitively: a required field may itself have dependencies.
	 *
	 * @return array<string, string[]> Requested field => fields it requires
	 */
	public static function fieldDependencies(): array;
}
