<?php

namespace RedskyEnvision\ApiQueryBuilder;

use RedskyEnvision\ApiQueryBuilder\Concerns\ResolvesFields;
use Illuminate\Http\Request;

/**
 * Class ApiFieldResolver
 *
 * Lightweight alternative to ApiQueryBuilder for DTO-backed resources.
 * Handles field resolution and FieldRegistry registration without requiring
 * an Eloquent model or executing any database queries.
 *
 * Typical usage:
 *   ApiFieldResolver::make($request)
 *       ->allowedFields(['users' => ['id', 'email', 'name']])
 *       ->prepare('users');
 *
 * @package RedskyEnvision\ApiQueryBuilder
 */
class ApiFieldResolver {
	use ResolvesFields;
	
	/**
	 * @param Request $request
	 * @param bool $strict
	 */
	public function __construct(
		private readonly Request $request,
		bool $strict = true
	) {
		$this->strictMode = $strict;
	}
	
	/**
	 * @param Request $request
	 * @param bool $strict
	 * @return static
	 */
	public static function make(Request $request, bool $strict = true): static {
		return new static($request, $strict);
	}
	
	/**
	 * Registers allowed fields in FieldRegistry and parses the requested fields
	 * for the given table from the current request.
	 *
	 * Must be called before returning the associated resource.
	 *
	 * @param string $tableName
	 * @return static
	 */
	public function prepare(string $tableName): static {
		$this->parseFields($this->request, $tableName);
		
		return $this;
	}
	
	/**
	 * Returns the parsed and filtered fields for a given table.
	 * When $allowedOnly is false, returns raw requested fields without filtering or registry writes.
	 *
	 * @param string $tableName
	 * @param bool $allowedOnly
	 * @return string[]
	 */
	public function getRequestedFieldsFor(string $tableName, bool $allowedOnly = true): array {
		if (!$allowedOnly) {
			$fields = $this->explodeRequestedFieldsForTable($tableName);
			
			return empty($fields) ? ['*'] : $fields;
		}
		
		return $this->parseFields($this->request, $tableName);
	}
	
	/**
	 * Returns whether a specific field is included in the resolved field list for a table.
	 * When wildcard is active, always returns true.
	 *
	 * @param string $tableName
	 * @param string $field
	 * @param bool $allowedOnly
	 * @return bool
	 */
	public function hasRequestedField(string $tableName, string $field, bool $allowedOnly = true): bool {
		$fields = $this->getRequestedFieldsFor($tableName, $allowedOnly);
		
		return $fields === ['*'] || in_array($field, $fields, true);
	}
}
