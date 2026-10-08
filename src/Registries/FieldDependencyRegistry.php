<?php

namespace RedskyEnvision\ApiQueryBuilder\Registries;

use Closure;

/**
 * Class FieldDependencyRegistry
 *
 * Holds the resolvers telling which extra fields must be selected alongside a requested field
 * (e.g. an accessor reading other attributes).
 *
 * @package RedskyEnvision\ApiQueryBuilder\Registries
 */
class FieldDependencyRegistry {
	/**
	 * @var array<int, Closure(string, string): string[]>
	 */
	protected array $resolvers = [];
	
	/**
	 * Registers a resolver called once per requested field and per table.
	 * It receives the field and the table name, and returns the fields it depends on (empty array when none).
	 *
	 * @param Closure $resolver
	 * @return void
	 */
	public function addResolver(Closure $resolver): void {
		$this->resolvers[] = $resolver;
	}
	
	/**
	 * Returns the fields required by the given requested fields, without duplicates
	 * and without those that are already requested.
	 *
	 * @param string $tableName
	 * @param string[] $requestedFields
	 * @return string[]
	 */
	public function resolve(string $tableName, array $requestedFields): array {
		$dependencies = [];
		
		foreach ($requestedFields as $field) {
			foreach ($this->resolvers as $resolver) {
				array_push($dependencies, ...$resolver($field, $tableName));
			}
		}
		
		return array_values(array_diff(array_unique($dependencies), $requestedFields));
	}
}
