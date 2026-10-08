<?php

namespace RedskyEnvision\ApiQueryBuilder\Concerns;

use RedskyEnvision\ApiQueryBuilder\Exceptions\InvalidFieldException;
use RedskyEnvision\ApiQueryBuilder\Registries\FieldDependencyRegistry;
use RedskyEnvision\ApiQueryBuilder\Registries\FieldRegistry;
use RedskyEnvision\ApiQueryBuilder\Support\FieldRequirement;
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
	 * @var array<string, string[]> List of fields that must always be selected (never exposed), keyed by table name
	 */
	private array $alwaysFields = [];
	
	/**
	 * @var array<string, FieldRequirement[]> Context-specific field requirements, keyed by table name
	 */
	private array $fieldRequirements = [];
	
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
	 * Sets the fields that must always be selected per table, so that the requested fields,
	 * the relations and the policies always find the values they rely on.
	 *
	 * These fields are only selected: they are exposed only if they are also requested and allowed.
	 *
	 * @param array<string, string[]> $fields Value must be ['table' => ['field1', 'field2']]
	 * @return $this
	 */
	public function alwaysFields(array $fields): self {
		$this->alwaysFields = $fields;
		
		return $this;
	}
	
	/**
	 * Declares the fields a table must select, optionally only when other fields are requested
	 * (possibly on other tables). Calling it several times for the same table accumulates the requirements.
	 *
	 * Like "alwaysFields", the required fields are only selected, never exposed, and they are ignored
	 * when the table selects all of its fields.
	 *
	 * @param string $tableName
	 * @param FieldRequirement ...$requirements
	 * @return $this
	 */
	public function requireFieldsFor(string $tableName, FieldRequirement ...$requirements): self {
		$this->fieldRequirements[$tableName] = array_merge($this->fieldRequirements[$tableName] ?? [], $requirements);
		
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
	 * and registers the result in FieldRegistry.
	 *
	 * The returned fields are the exposed ones (requested and allowed). The fields to select
	 * from the database are resolved separately by "resolveSelectedFields()".
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
		
		// Store the result in FieldRegistry for use in resources (e.g., ApiResource)
		
		app(FieldRegistry::class)->setFieldsFor($tableName, $fields);
		
		return $fields;
	}
	
	/**
	 * Returns the fields to select from the database for a table: the exposed fields plus
	 * everything the library needs to compute them correctly. These additional fields are never exposed.
	 *
	 * - the fields required through "requireFieldsFor()"
	 * - the "alwaysFields"
	 * - the dependencies of all of the above (see FieldDependencyRegistry), resolved transitively
	 *
	 * A wildcard selection is returned as is.
	 *
	 * @param string $tableName
	 * @param string[] $exposedFields Result of "parseFields()"
	 * @return string[]
	 */
	private function resolveSelectedFields(string $tableName, array $exposedFields): array {
		if ($exposedFields === ['*']) {
			return $exposedFields;
		}
		
		$fields = array_values(array_unique(array_merge(
			$exposedFields,
			$this->resolveRequiredFields($tableName),
			$this->alwaysFields[$tableName] ?? []
		)));
		
		return array_merge($fields, app(FieldDependencyRegistry::class)->resolve($tableName, $fields));
	}
	
	/**
	 * Returns the fields required for a table by the active "requireFieldsFor()" requirements.
	 *
	 * @param string $tableName
	 * @return string[]
	 */
	private function resolveRequiredFields(string $tableName): array {
		$required = [];
		
		foreach ($this->fieldRequirements[$tableName] ?? [] as $requirement) {
			if ($this->isRequirementActive($requirement)) {
				array_push($required, ...$requirement->fields);
			}
		}
		
		return array_values(array_unique($required));
	}
	
	/**
	 * A requirement without condition is always active. Otherwise it is active as soon as one of the
	 * listed fields is explicitly requested on its table: a table selecting all of its fields
	 * (no "fields" parameter) does not trigger it.
	 *
	 * @param FieldRequirement $requirement
	 * @return bool
	 */
	private function isRequirementActive(FieldRequirement $requirement): bool {
		if ($requirement->whenRequested === []) {
			return true;
		}
		
		foreach ($requirement->whenRequested as $sourceTable => $sourceFields) {
			if (array_intersect($sourceFields, $this->explodeRequestedFieldsForTable($sourceTable)) !== []) {
				return true;
			}
		}
		
		return false;
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
