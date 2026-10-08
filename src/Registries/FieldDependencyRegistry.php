<?php

namespace RedskyEnvision\ApiQueryBuilder\Registries;

use Closure;
use Illuminate\Database\Eloquent\Model;
use RedskyEnvision\ApiQueryBuilder\Contracts\DeclaresFieldDependencies;

/**
 * Class FieldDependencyRegistry
 *
 * Holds the rules telling which extra fields must be selected alongside a requested field
 * (e.g. an accessor reading other attributes).
 *
 * Rules come from resolvers (generic conventions) and from per-table maps (typically declared by models).
 * Dependencies are always indexed by table name and resolved transitively.
 * The registry knows no field name by itself: the application provides its own rules.
 *
 * @package RedskyEnvision\ApiQueryBuilder\Registries
 */
class FieldDependencyRegistry {
	/**
	 * @var array<int, Closure(string, string): string[]>
	 */
	protected array $resolvers = [];
	
	/**
	 * @var array<string, array<string, string[]>> Required fields keyed by table name, then by requested field
	 */
	protected array $tableDependencies = [];
	
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
	 * Adds the dependencies of a table. Adding the same table several times merges the maps.
	 *
	 * @param string $tableName
	 * @param array<string, string[]> $dependencies Requested field => fields it requires
	 * @return void
	 */
	public function addDependencies(string $tableName, array $dependencies): void {
		foreach ($dependencies as $field => $requiredFields) {
			$this->tableDependencies[$tableName][$field] = array_values(array_unique(array_merge(
				$this->tableDependencies[$tableName][$field] ?? [],
				$requiredFields
			)));
		}
	}
	
	/**
	 * Registers the dependencies declared by a model, under the model's table.
	 * Does nothing for a model that declares none.
	 *
	 * @param Model $model
	 * @return void
	 */
	public function registerModel(Model $model): void {
		$tableName = $model->getTable();
		
		if ($model instanceof DeclaresFieldDependencies) {
			$this->addDependencies($tableName, $model::fieldDependencies());
		}
	}
	
	/**
	 * Returns the fields required by the given requested fields, without duplicates
	 * and without those that are already requested.
	 *
	 * Resolution is transitive, so a dependency may itself require other fields.
	 *
	 * @param string $tableName
	 * @param string[] $requestedFields
	 * @return string[]
	 */
	public function resolve(string $tableName, array $requestedFields): array {
		$seen = $requestedFields;
		$pending = $requestedFields;
		
		while ($pending !== []) {
			$field = array_shift($pending);
			
			foreach ($this->dependenciesOf($tableName, $field) as $dependency) {
				if (!in_array($dependency, $seen, true)) {
					$seen[] = $dependency;
					$pending[] = $dependency;
				}
			}
		}
		
		return array_values(array_diff($seen, $requestedFields));
	}
	
	/**
	 * Returns the direct dependencies of a field, from the table map and from every resolver.
	 *
	 * @param string $tableName
	 * @param string $field
	 * @return string[]
	 */
	private function dependenciesOf(string $tableName, string $field): array {
		$dependencies = $this->tableDependencies[$tableName][$field] ?? [];
		
		foreach ($this->resolvers as $resolver) {
			array_push($dependencies, ...$resolver($field, $tableName));
		}
		
		return $dependencies;
	}
}
