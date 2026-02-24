<?php

namespace RedskyEnvision\ApiQueryBuilder\Concerns;

use RedskyEnvision\ApiQueryBuilder\Exceptions\InvalidFieldException;
use RedskyEnvision\ApiQueryBuilder\Registries\FieldRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Trait ResolvesFields
 *
 * Handles allowed field configuration, request field parsing, and FieldRegistry registration.
 * Shared between ApiQueryBuilder and ApiFieldResolver.
 *
 * @package RedskyEnvision\ApiQueryBuilder\Concerns
 */
trait ResolvesFields {
	/**
	 * Separator used in URI for AND conditions
	 */
	private const string URI_SEPARATOR_AND = ',';
	/**
	 * @var string Separator used in URI for OR conditions
	 */
	private const string URI_SEPARATOR_OR = '|';
	
	/**
	 * @var array<string, string[]>|string[] List of allowed fields per table or global wildcard
	 */
	private array $allowedFields = ['*'];
	
	/**
	 * @var array<string, string[]> List of fields that must always be selected, keyed by table name
	 */
	private array $alwaysFields = [];
	
	/**
	 * @var bool Whether to throw exceptions when something invalid is provided
	 */
	private bool $strictMode = true;
	
	/**
	 * @param array<string, string[]> | string[] $fields Value can be ['*'] or ['table' => ['*']] or ['table' => ['field1', 'field2']]
	 * @return $this
	 */
	public function allowedFields(array $fields): self {
		$this->allowedFields = $fields;
		
		$this->storeAllowedFields();
		
		return $this;
	}
	
	/**
	 * Sets always-included fields per table.
	 *
	 * @param array<string, string[]> $fields Value must be ['table' => ['field1', 'field2']]
	 * @return $this
	 */
	public function alwaysFields(array $fields): self {
		$this->alwaysFields = $fields;
		
		return $this;
	}
	
	/**
	 * @param bool $value
	 * @return $this
	 */
	public function strictMode(bool $value): static {
		$this->strictMode = $value;
		
		return $this;
	}
	
	/**
	 * Store allowed fields in FieldRegistry.
	 * Only registers when allowedFields is an associative array (per-table, non-wildcard).
	 *
	 * @return void
	 */
	private function storeAllowedFields(): void {
		// Determine whether allowedFields is an associative array (per table)
		
		$isAssoc = Arr::isAssoc($this->allowedFields);
		
		// Check if wildcard is present (global)
		
		$isAllowingAll = !$isAssoc && count($this->allowedFields) === 1 && $this->allowedFields[0] === '*';
		
		if (!$isAllowingAll && $isAssoc) {
			$fieldRegistry = app(FieldRegistry::class);
			
			foreach ($this->allowedFields as $table => $fields) {
				$fieldRegistry->setAllowedFieldsFor($table, $fields);
			}
		}
	}
	
	/**
	 * Parses fields from the request for a given table, filters them against allowedFields,
	 * merges alwaysFields, and registers the result in FieldRegistry.
	 *
	 * @param Request $request
	 * @param string $tableName
	 * @return string[]
	 */
	private function parseFields(Request $request, string $tableName): array {
		$fields = $request->input('fields.'.$tableName, '');
		$fields = array_filter(array_map('trim', explode(self::URI_SEPARATOR_AND, $fields)));
		
		if (!empty($fields)) {
			// Filter out disallowed fields according to "allowedFields()"
			
			$fields = $this->filterFields($tableName, $fields);
		} else {
			$fields = ['*'];
		}
		
		$fieldRegistry = app(FieldRegistry::class);
		
		// Merge "alwaysFields" when a specific field selection is active
		
		if ($fields !== ['*'] && array_key_exists($tableName, $this->alwaysFields)) {
			$fields = array_unique(array_merge($fields, $this->alwaysFields[$tableName]));
			
			// Store the fields in FieldRegistry
			
			$fieldRegistry->setAlwaysFieldsFor($tableName, $this->alwaysFields[$tableName]);
		}
		
		// Store the result in FieldRegistry for use in resources (e.g., ApiResource)
		
		$fieldRegistry->setFieldsFor($tableName, $fields);
		
		return $fields;
	}
	
	/**
	 * Filters the requested fields against the configured allowedFields.
	 * In strict mode, throws on any unauthorized field; otherwise silently removes it.
	 *
	 * @param string $tableName
	 * @param string[] $fields
	 * @return string[]
	 */
	private function filterFields(string $tableName, array $fields): array {
		// Determine whether allowedFields is an associative array (per table)
		
		$isAllowedAssoc = Arr::isAssoc($this->allowedFields);
		
		// Allow all fields if wildcard is present (global or per-table)
		
		if (
			(!$isAllowedAssoc && count($this->allowedFields) === 1 && $this->allowedFields[0] === '*') ||
			($isAllowedAssoc && array_key_exists($tableName, $this->allowedFields) && count($this->allowedFields[$tableName]) === 1 && $this->allowedFields[$tableName][0] === '*')
		) {
			return $fields;
		}
		
		// If no fields are allowed for this table, return an empty list
		
		if ($isAllowedAssoc && !array_key_exists($tableName, $this->allowedFields)) {
			return [];
		}
		
		// Get allowed fields, either global or specific to the table
		
		$allowedFields = $isAllowedAssoc ? $this->allowedFields[$tableName] : $this->allowedFields;
		
		// Remove disallowed fields or throw an exception if strict mode is enabled
		
		for ($i = 0; $i < count($fields); $i++) {
			$field = $fields[$i];
			
			if (in_array($field, $allowedFields, true)) {
				continue;
			}
			
			if ($this->strictMode) {
				throw new InvalidFieldException('Field "'.$field.'" is not allowed.');
			}
			
			array_splice($fields, $i, 1);
			
			$i--;
		}
		
		return $fields;
	}
	
	/**
	 * Returns the raw requested fields for a table directly from the request, without filtering or registry.
	 *
	 * @param string $tableName
	 * @return string[]
	 */
	private function explodeRequestedFieldsForTable(string $tableName): array {
		$raw = (string)$this->request->input('fields.'.$tableName, '');
		
		if ($raw === '') {
			return [];
		}
		
		return array_values(array_filter(array_map('trim', explode(self::URI_SEPARATOR_AND, $raw))));
	}
}
