<?php

namespace RedskyEnvision\ApiQueryBuilder\Registries;

/**
 * Class FieldRegistry
 *
 * Stores the list of requested fields for each table during the request lifecycle.
 *
 * This class acts as a centralized in-memory registry that allows
 * both query building and resources to access the same set of field constraints.
 *
 * @package RedskyEnvision\ApiQueryBuilder\Registries
 */
class FieldRegistry {
	/**
	 * A map of table names to the list of fields requested for each.
	 *
	 * Example:
	 *  [
	 *      'users' => ['id', 'name', 'email'],
	 *      'posts' => ['title', 'created_at']
	 *  ]
	 *
	 * @var array<string, string[]>
	 */
	protected array $tablesFields = [];

	/**
	 * Stores the list of allowed/requested fields for a given table.
	 * Overrides any previously set values for that table.
	 *
	 * @param string $tableName
	 * @param string[] $fields
	 * @return void
	 */
	public function setFieldsFor(string $tableName, array $fields): void {
		$this->tablesFields[$tableName] = $fields;
	}

	/**
	 * Returns the full map of all table field registrations.
	 *
	 * @return array<string, string[]>
	 */
	public function getTablesFields(): array {
		return $this->tablesFields;
	}

	/**
	 * Retrieves the list of fields registered for a given table.
	 *
	 * @param string $tableName
	 * @return string[]|null
	 */
	public function getFieldsFor(string $tableName): ?array {
		if ($this->hasFieldsFor($tableName)) {
			return $this->tablesFields[$tableName];
		}

		return null;
	}

	/**
	 * Checks if there are registered fields for a given table.
	 *
	 * @param string $tableName
	 * @return bool
	 */
	public function hasFieldsFor(string $tableName): bool {
		return array_key_exists($tableName, $this->tablesFields);
	}
}
