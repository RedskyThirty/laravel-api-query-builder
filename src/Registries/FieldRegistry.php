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
	 * A map of table names to the list of allowed fields for each.
	 *
	 * Example:
	 *  [
	 *      'users' => ['id', 'name', 'email'],
	 *      'posts' => ['title', 'created_at']
	 *  ]
	 *
	 * @var array<string, string[]>
	 */
	protected array $tablesAllowedFields = [];
	
	/**
	 * A map of table names to the list of always-included fields for each.
	 * These fields will be automatically added to the requested fields if missing.
	 *
	 *  Example:
	 *   [
	 *       'posts' => ['author_id']
	 *   ]
	 *
	 * @var array<string, string[]>
	 */
	protected array $tablesAlwaysFields = [];
	
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
	 * Stores the list of allowed fields for a given table.
	 * Overrides any previously set values for that table.
	 *
	 * @param string $tableName
	 * @param string[] $fields
	 * @return void
	 */
	public function setAllowedFieldsFor(string $tableName, array $fields): void {
		$this->tablesAllowedFields[$tableName] = $fields;
	}
	
	/**
	 * Stores the list of always-included fields for a given table.
	 * These fields will be automatically injected into any requested fields.
	 *
	 * @param string $tableName
	 * @param string[] $fields
	 * @return void
	 */
	public function setAlwaysFieldsFor(string $tableName, array $fields): void {
		$this->tablesAlwaysFields[$tableName] = $fields;
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
	 * Returns the full map of all table allowed fields.
	 *
	 * @return array<string, string[]>
	 */
	public function getTablesAllowedFields(): array {
		return $this->tablesAllowedFields;
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
	 * Retrieves the list of allowed fields for a given table.
	 *
	 * @param string $tableName
	 * @return string[]|null
	 */
	public function getAllowedFieldsFor(string $tableName): ?array {
		if (array_key_exists($tableName, $this->tablesAllowedFields)) {
			return $this->tablesAllowedFields[$tableName];
		}
		
		return null;
	}
	
	/**
	 * Retrieves the list of fields that must always be selected for a given table.
	 *
	 * @param string $tableName
	 * @return string[]|null
	 */
	public function getAlwaysFieldsFor(string $tableName): ?array {
		if (array_key_exists($tableName, $this->tablesAlwaysFields)) {
			return $this->tablesAlwaysFields[$tableName];
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
	
	/**
	 * Determines whether the selection targets all fields (`*`).
	 *
	 * @param array|null $fields
	 * @return bool
	 */
	public static function isSelectingAll(?array $fields): bool {
		return $fields === null || (count($fields) === 1 && $fields[0] === '*');
	}
}
