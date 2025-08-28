<?php

namespace RedskyEnvision\ApiQueryBuilder;

use RedskyEnvision\ApiQueryBuilder\Exceptions\InvalidFieldException;
use RedskyEnvision\ApiQueryBuilder\Exceptions\InvalidFilterException;
use RedskyEnvision\ApiQueryBuilder\Exceptions\InvalidRelationException;
use RedskyEnvision\ApiQueryBuilder\Exceptions\InvalidSortException;
use RedskyEnvision\ApiQueryBuilder\Registries\FieldRegistry;
use RedskyEnvision\ApiQueryBuilder\Sorts\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use LogicException;

/**
 * Class ApiQueryBuilder
 *
 * ApiQueryBuilder dynamically applies filters, field selection, relations and sorting
 * based on HTTP request parameters. This is used to build advanced REST API behavior
 * similar to GraphQL: only request what you need.
 *
 * @package RedskyEnvision\ApiQueryBuilder
 */
class ApiQueryBuilder {
	/**
	 * @var string Filter type for LIKE queries
	 */
	private const string FILTER_TYPE_LIKE = 'like';
	/**
	 * @var string Filter type for standard WHERE queries
	 */
	private const string FILTER_TYPE_WHERE = 'where';
	
	/**
	 * @var string Separator used in URI for AND conditions
	 */
	private const string URI_SEPARATOR_AND = ',';
	/**
	 * @var string Separator used in URI for OR conditions
	 */
	private const string URI_SEPARATOR_OR = '|';
	
	/**
	 * @var string NOT operator prefix
	 */
	private const string URI_OPERATOR_NOT = '!';
	/**
	 * @var string Less than operator prefix
	 */
	private const string URI_OPERATOR_LESS_THAN = 'lt:';
	/**
	 * @var string Less than or equal operator prefix
	 */
	private const string URI_OPERATOR_LESS_THAN_OR_EQUAL = 'lte:';
	/**
	 * @var string Greater than operator prefix
	 */
	private const string URI_OPERATOR_GREATHER_THAN = 'gt:';
	/**
	 * @var string Greater than or equal operator prefix
	 */
	private const string URI_OPERATOR_GREATHER_THAN_OR_EQUAL = 'gte:';
	
	/**
	 * @var Builder The Eloquent query builder instance
	 */
	private Builder $query;
	
	/**
	 * @var Request The current HTTP request
	 */
	private Request $request;
	
	/**
	 * @var string[] List of allowed relation names
	 */
	private array $allowedRelations = [];
	
	/**
	 * @var array<string, string[]> | string[] List of allowed fields per table or global
	 */
	private array $allowedFields = ['*'];
	
	/**
	 * @var array<string, string[]> List of fields that must always be selected per table
	 */
	private array $alwaysFields = [];
	
	/**
	 * @var string[] List of allowed filter fields
	 */
	private array $allowedFilters = [];
	
	/**
	 * @var Sort[] Default sort definitions
	 */
	private array $defaultSorts = [];
	
	/**
	 * @var string[] List of allowed sortable fields
	 */
	private array $allowedSorts = ['*'];
	
	/**
	 * @var int Default number of results per page
	 */
	private int $defaultPerPage = 25;
	
	/**
	 * @var bool Indicates if prepare() was called
	 */
	private bool $hasBeenPrepared = false;
	
	/**
	 * @var bool Whether to throw exceptions when something invalid is provided
	 */
	private bool $strictMode = true;
	
	/**
	 * ApiQueryBuilder constructor
	 *
	 * @param class-string $modelClass
	 * @param Request $request
	 * @param bool $strict
	 */
	public function __construct(string $modelClass, Request $request, bool $strict = true) {
		// Ensure the given class exists
		
		if (!class_exists($modelClass)) {
			throw new InvalidArgumentException('Model class "'.$modelClass.'" does not exist.');
		}
		
		// Ensure the class is a subclass of Eloquent Model
		
		if (!is_subclass_of($modelClass, Model::class)) {
			throw new InvalidArgumentException('Class "'.$modelClass.'" must extend Eloquent Model.');
		}
		
		$this->query = $modelClass::query();
		$this->request = $request;
		$this->strictMode = $strict;
	}
	
	/**
	 * @param class-string $modelClass
	 * @param Request $request
	 * @param bool $strict
	 * @return ApiQueryBuilder
	 */
	public static function make(string $modelClass, Request $request, bool $strict = true): self {
		return new static($modelClass, $request, $strict);
	}
	
	/**
	 * Factory method to create an instance of ApiQueryBuilder
	 *
	 * @return Builder
	 */
	public function query(): Builder {
		if (!$this->hasBeenPrepared) {
			throw new LogicException('You must call prepare() before accessing the query.');
		}
		
		return $this->query;
	}
	
	/**
	 * @param string[] $relations
	 * @return $this
	 */
	public function allowedRelations(array $relations): self {
		$this->allowedRelations = $relations;
		
		return $this;
	}
	
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
	 * Sets always-included fields per table (e.g. for internal checks).
	 *
	 * @param array<string, string[]> $fields Value must be ['table' => ['field1', 'field2']]
	 * @return $this
	 */
	public function alwaysFields(array $fields): self {
		$this->alwaysFields = $fields;
		return $this;
	}
	
	/**
	 * @param string[] $fields Value can be ['*'] or ['column'] or ['relation.*'] or ['relation.column']
	 * @return $this
	 */
	public function allowedFilters(array $filters): self {
		$this->allowedFilters = $filters;
		
		return $this;
	}
	
	/**
	 * @param Sort[] $sorts
	 * @return $this
	 */
	public function defaultSorts(array $sorts): self {
		$this->defaultSorts = $sorts;
		
		return $this;
	}
	
	/**
	 * @param string[] $sorts
	 * @return $this
	 */
	public function allowedSorts(array $sorts): self {
		$this->allowedSorts = array_map('strtolower', $sorts);
		
		return $this;
	}
	
	/**
	 * @param int $count
	 * @return $this
	 */
	public function defaultPerPage(int $count): self {
		$this->defaultPerPage = $count;
		
		return $this;
	}
	
	/**
	 * @param bool $value
	 * @return $this
	 */
	public function strictMode(bool $value): self {
		$this->strictMode = $value;
		
		return $this;
	}
	
	/**
	 * @param array $fields
	 * @return bool
	 */
	public function isSelectingAll(array $fields): bool {
		return count($fields) === 1 && $fields[0] === '*';
	}
	
	/**
	 * Applies allowed field selection and relations to the query.
	 * Applies allowed field selection, eager loads relations,
	 * and applies filters and sorting to the base query.
	 *
	 * This method must be called before retrieving results with get(), paginate(), or fetch().
	 *
	 * @return self
	 */
	public function prepare(): self {
		$model = $this->query->getModel();
		$modelTable = $model->getTable();
		
		// Extract the requested fields for the root model from fields[model]
		
		$fields = $this->parseFields($this->request, $modelTable);
		
		if (!$this->isSelectingAll($fields)) {
			// Always include 'id' to ensure entity identification
			
			if (!in_array('id', $fields)) {
				$fields[] = 'id';
			}
			
			// Add required foreign keys for allowed BelongsTo relations (to avoid missing data)
			
			foreach ($this->allowedRelations as $relation) {
				$explodedRelations = explode('.', $relation);
				$directRelation = $explodedRelations[0];
				
				if (method_exists($model, $directRelation)) {
					$relationInstance = $model->$directRelation();
					
					if ($relationInstance instanceof BelongsTo) {
						// Inject the necessary foreign key into the fields list
						
						$this->addRelationForeignKeys($relationInstance, $fields);
					}
				}
				
				$explodedRelations = null;
				$directRelation = null;
			}
			
			// Apply final filtered selection of fields to the query
			
			$this->selectSelectableColumns($this->query, $modelTable, $fields);
		}
		
		$model = null;
		$modelTable = null;
		$fields = null;
		
		// Load requested and allowed relations recursively
		
		$relations = array_filter(array_map('trim', explode(self::URI_SEPARATOR_AND, $this->request->input('relations', ''))));
		
		foreach ($relations as $relation) {
			if ($this->isAllowedRelation($relation)) {
				$relationSegments = explode('.', $relation);
				
				// Apply with() + field selection on nested relations
				
				$this->applyNestedWith($this->query, $relationSegments);
				
				$relationSegments = null;
			}
		}
		
		$relations = null;
		
		// Apply filtering and sorting to the query
		
		$this->filter();
		$this->sort();
		
		$this->hasBeenPrepared = true;
		
		return $this;
	}
	
	/**
	 * Executes the query and returns all matching results as a collection.
	 * Ensures the query is prepared before execution.
	 *
	 * @param Builder|null $query
	 * @return Collection
	 */
	public function get(?Builder $query = null): Collection {
		$this->preFetch($query);
		
		return $this->query->get();
	}
	
	/**
	 * Executes the query with pagination.
	 * Falls back to default per-page value if none is provided in the request.
	 *
	 * @param Builder|null $query
	 * @return LengthAwarePaginator
	 */
	public function paginate(?Builder $query = null): LengthAwarePaginator {
		$this->preFetch($query);
		
		$perPage = (int)$this->request->input('per_page', $this->defaultPerPage);
		
		return $this->doPaginate($perPage);
	}
	
	/**
	 * Returns paginated results if `per_page` is specified in the request.
	 * Otherwise, returns the full result set as a collection.
	 *
	 * @return Collection|LengthAwarePaginator
	 */
	public function fetch(?Builder $query = null): Collection | LengthAwarePaginator {
		$this->preFetch($query);
		
		$perPage = (int)$this->request->input('per_page', 0);
		
		return $perPage > 0 ? $this->doPaginate($perPage) : $this->query->get();
	}
	
	/**
	 * Parses and registers fields from the request without executing any database queries.
	 * Useful for preparing API resources or validating field input independently of query building.
	 *
	 * @return self
	 */
	public function prepareWithoutQuery(): self {
		$model = $this->query->getModel();
		$modelTable = $model->getTable();
		
		// Parse and register fields for the root model
		
		$this->parseFields($this->request, $modelTable);
		
		// Parse and register fields for allowed relations
		
		foreach ($this->allowedRelations as $relation) {
			$relationSegments = explode('.', $relation);
			$currentModel = $model;
			
			foreach ($relationSegments as $segment) {
				if (!method_exists($currentModel, $segment)) {
					break;
				}
				
				$relationInstance = $currentModel->$segment();
				
				if (!($relationInstance instanceof Relation)) {
					break;
				}
				
				$relatedModel = $relationInstance->getRelated();
				$currentModel = $relatedModel;
				
				$this->parseFields($this->request, $relatedModel->getTable());
			}
		}
		
		return $this;
	}
	
	/**
	 * Applies nested `with()` eager loading and selects fields for each related model.
	 *
	 *  This method recursively traverses the relation chain and ensures:
	 *  - Relations are allowed
	 *  - Proper fields (including foreign keys) are selected
	 *  - Nested relations are also processed
	 *
	 * @param Builder|Relation $builder The current query or relation builder
	 * @param string[] $relationSegments The segments of the relation path
	 * @param string $prefix Used to rebuild full relation path recursively
	 * @param null $model The current model being evaluated (used for recursion)
	 * @return void
	 */
	private function applyNestedWith(
		Builder | Relation $builder,
		array $relationSegments,
		string $prefix = '',
		$model = null
	): void {
		$relationName = array_shift($relationSegments);
		$fullRelationKey = $prefix ? $prefix.'.'.$relationName : $relationName;
		
		// Skip if the relation is not allowed (in strict mode this will throw)
		
		if (!$this->isAllowedRelation($fullRelationKey)) {
			return;
		}
		
		$model = $model ?? $builder->getModel();
		
		// Skip if the relation method does not exist on the model
		
		if (!method_exists($model, $relationName)) {
			return;
		}
		
		$relationInstance = $model->$relationName();
		
		$model = null;
		
		// Abort if the resolved method is not a valid Eloquent relation
		
		if (!($relationInstance instanceof Relation)) {
			return;
		}
		
		$relatedModel = $relationInstance->getRelated();
		$relatedTable = $relatedModel->getTable();
		
		// Apply eager loading with a closure to constrain fields and nested relations
		
		$builder->with([$relationName => function ($q) use ($relationSegments, $fullRelationKey, $relatedModel, $relationInstance, $relatedTable) {
			$fields = $this->parseFields($this->request, $relatedTable);
			
			if (!$this->isSelectingAll($fields)) {
				// Always include 'id' field
				
				if (!in_array('id', $fields)) {
					$fields[] = 'id';
				}
				
				// Add required foreign key if the relation is HasOne or HasMany
				
				if ($relationInstance instanceof HasOneOrMany) {
					$this->addRelationForeignKeys($relationInstance, $fields);
				}
				
				// Apply the field selection to the relation query
				
				$this->selectSelectableColumns($q, $relatedTable, $fields);
			}
			
			$fields = null;
			
			// Recursively apply nested with() to deeper relations
			
			if (!empty($relationSegments)) {
				$this->applyNestedWith($q, $relationSegments, $fullRelationKey, $relatedModel);
			}
		}]);
		
		$relationName = null;
		$fullRelationKey = null;
		$relationInstance = null;
	}
	
	/**
	 * Store allowed fields in FieldRegistry
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
			
			foreach ($this->allowedFields as $table => $field) {
				$fieldRegistry->setAllowedFieldsFor($table, $field);
			}
		}
	}
	
	/**
	 * Checks if a relation is allowed.
	 *
	 * @param string $relation
	 * @return bool
	 */
	private function isAllowedRelation(string $relation): bool {
		$ok = in_array($relation, $this->allowedRelations, true);
		
		if ($this->strictMode && !$ok) {
			throw new InvalidRelationException('Relation "'.$relation.'" is not allowed.');
		}
		
		return $ok;
	}
	
	/**
	 * Checks if a filter is allowed.
	 *
	 * @param string $relation
	 * @return bool
	 */
	private function isAllowedFilter(string $filter): bool {
		$filterSegments = explode('.', $filter);
		$filterSegments[count($filterSegments) - 1] = '*';
		$wildcard = implode('.', $filterSegments);
		
		$ok = in_array($filter, $this->allowedFilters, true) || in_array($wildcard, $this->allowedFilters, true);
		
		$filterSegments = null;
		$wildcard = null;
		
		if ($this->strictMode && !$ok) {
			throw new InvalidFilterException('Filter "'.$filter.'" is not allowed.');
		}
		
		return $ok;
	}
	
	/**
	 * Checks if a sort key is allowed.
	 *
	 * @param string $sort
	 * @return bool
	 */
	private function isAllowedSort(string $sort): bool {
		// Remove the "-" if present to normalize the field name
		$normalized = ltrim($sort, '-');
		
		// Check against allowed list (or wildcard)
		$ok = (count($this->allowedSorts) === 1 && $this->allowedSorts[0] === '*') || in_array($normalized, $this->allowedSorts, true);
		
		if ($this->strictMode && !$ok) {
			throw new InvalidSortException('Sort "'.$normalized.'" is not allowed.');
		}
		
		return $ok;
	}
	
	/**
	 * Parses a comma-separated list of requested fields from the HTTP request for a given table.
	 *
	 * The method also filters out unauthorized fields (if applicable) and stores
	 * the result in the FieldRegistry for later use in the resource layer.
	 *
	 * @param Request $request
	 * @param string $tableName
	 * @return string[]
	 */
	private function parseFields(Request $request, string $tableName): array {
		$fields = $request->input('fields.'.$tableName, '');
		$fields = array_filter(array_map('trim', explode(self::URI_SEPARATOR_AND, $fields)));
		
		if (!empty($fields)) {
			// Filter out disallowed fields according to allowedFields()
			
			$fields = $this->filterFields($tableName, $fields);
		} else {
			$fields = ['*'];
		}
		
		$fieldRegistry = app(FieldRegistry::class);
		
		// Append "alwaysFields" if set and current selection is not wildcard
		
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
	 * Filters a list of requested fields by checking them against the allowed fields.
	 *
	 * In strict mode, an exception is thrown if any unauthorized field is found.
	 * In non-strict mode, unauthorized fields are silently removed.
	 *
	 * @param string $tableName
	 * @param array $fields
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
	 * Filters the requested fields to only include those that exist in the database schema.
	 * Then applies them to the query as a select clause, fully qualified with table name.
	 *
	 * This ensures you don't try to select non-existent or virtual attributes.
	 *
	 * @param Builder|Relation $builder The query or relation builder to modify
	 * @param string $tableName The name of the table to verify columns against
	 * @param string[] $fields The list of fields requested for selection
	 * @return void
	 */
	private function selectSelectableColumns(Builder | Relation $builder, string $tableName, array $fields): void {
		// Only keep fields that actually exist in the database table
		
		$selectableFields = array_filter($fields, fn ($f) => Schema::hasColumn($tableName, $f));
		
		// If valid fields exist, apply them to the SELECT clause
		
		if (!empty($selectableFields)) {
			$builder->select(array_map(fn ($f) => "$tableName.$f", $selectableFields));
		}
		
		$selectableFields = null;
	}
	
	/**
	 * Ensures foreign key columns required for relation resolution are included in the selected fields.
	 *
	 * This is especially important when eager loading relationships like belongsTo or polymorphic relations.
	 *
	 * @param BelongsTo|HasOneOrMany $relationInstance The relation to extract foreign key from
	 * @param string[] $fields The reference to the list of fields to be updated
	 * @return void
	 */
	private function addRelationForeignKeys(BelongsTo | HasOneOrMany $relationInstance, array &$fields): void {
		$foreignKey = $relationInstance->getForeignKeyName();
		
		// Add the foreign key if not already present
		
		if (!in_array($foreignKey, $fields)) {
			$fields[] = $foreignKey;
			
			// Special handling for polymorphic relations (morphTo)
			
			if ($foreignKey === 'model_id' && !in_array('model_type', $fields)) {
				$fields[] = 'model_type';
			}
		}
		
		$foreignKey = null;
	}
	
	/**
	 * Builds and applies a WHERE clause to the query for a given field, operator, and value.
	 *
	 * Supports logical AND/OR, LIKE and comparison operators, and negation.
	 * Enforces restrictions on which operators are allowed with LIKE filters.
	 *
	 * @param Builder $builder
	 * @param string $type
	 * @param string $field
	 * @param string $value
	 * @param bool $and
	 * @param int $i
	 * @return void
	 */
	private function addWhereClause(Builder $builder, string $type, string $field, string $value, bool $and = true, int $i = 0): void {
		// Prevent usage of comparison operators with LIKE filters
		
		if (
			$type === self::FILTER_TYPE_LIKE &&
			(
				str_starts_with($value, self::URI_OPERATOR_LESS_THAN) ||
				str_starts_with($value, self::URI_OPERATOR_LESS_THAN_OR_EQUAL) ||
				str_starts_with($value, self::URI_OPERATOR_GREATHER_THAN) ||
				str_starts_with($value, self::URI_OPERATOR_GREATHER_THAN_OR_EQUAL)
			)
		) {
			if (!$this->strictMode) {
				return;
			} else {
				throw new InvalidFilterException('Operators "'.self::URI_OPERATOR_LESS_THAN.'", "'.self::URI_OPERATOR_LESS_THAN_OR_EQUAL.'", "'.self::URI_OPERATOR_GREATHER_THAN.'" and "'.self::URI_OPERATOR_GREATHER_THAN_OR_EQUAL.'" are not allowed with "'.self::FILTER_TYPE_LIKE.'" filter, only with "'.self::FILTER_TYPE_WHERE.'".');
			}
		}
		
		// Determine the operator and strip it from the value
		
		if (str_starts_with($value, self::URI_OPERATOR_NOT)) {
			$not = true;
			$whereOperator = '!=';
			$value = substr($value, strlen(self::URI_OPERATOR_NOT));
		} else {
			$not = false;
			
			if (str_starts_with($value, self::URI_OPERATOR_LESS_THAN)) {
				$whereOperator = '<';
				$value = substr($value, strlen(self::URI_OPERATOR_LESS_THAN));
			} else if (str_starts_with($value, self::URI_OPERATOR_LESS_THAN_OR_EQUAL)) {
				$whereOperator = '<=';
				$value = substr($value, strlen(self::URI_OPERATOR_LESS_THAN_OR_EQUAL));
			} else if (str_starts_with($value, self::URI_OPERATOR_GREATHER_THAN)) {
				$whereOperator = '>';
				$value = substr($value, strlen(self::URI_OPERATOR_GREATHER_THAN));
			} else if (str_starts_with($value, self::URI_OPERATOR_GREATHER_THAN_OR_EQUAL)) {
				$whereOperator = '>=';
				$value = substr($value, strlen(self::URI_OPERATOR_GREATHER_THAN_OR_EQUAL));
			} else {
				$whereOperator = '=';
			}
		}
		
		// Apply the condition using the correct operator and logic
		
		if ($and || $i === 0) {
			// AND clause or first OR clause
			
			if ($type === self::FILTER_TYPE_LIKE) {
				if (!$not) {
					$builder->whereLike($field, '%'.$value.'%');
				} else {
					$builder->whereNotLike($field, '%'.$value.'%');
				}
			} else {
				$builder->where($field, $whereOperator, $value);
			}
		} else {
			// OR clause (subsequent in group)
			
			if ($type === self::FILTER_TYPE_LIKE) {
				if (!$not) {
					$builder->orWhereLike($field, '%'.$value.'%');
				} else {
					$builder->orWhereNotLike($field, '%'.$value.'%');
				}
			} else {
				$builder->orWhere($field, $whereOperator, $value);
			}
		}
	}
	
	/**
	 * Applies filtering logic on the given builder, resolving nested relation paths
	 * and handling logical OR / AND grouping.
	 *
	 * Supports recursive filtering of related models.
	 *
	 * @param Builder $builder
	 * @param string $type like | where
	 * @param array<string, string> $filters
	 * @param string|null $relationPath
	 * @return void
	 */
	private function filterFilterableColumns(Builder $builder, string $type, array $filters, ?string $relationPath = null): void {
		// Enforce allowed filter types
		
		if (!in_array($type, [self::FILTER_TYPE_LIKE, self::FILTER_TYPE_WHERE])) {
			if (!$this->strictMode) {
				return;
			} else {
				throw new InvalidFilterException('Filter type "'.$type.'" is not allowed.');
			}
		}
		
		foreach ($filters as $key => $value) {
			$hasOr = str_contains($value, self::URI_SEPARATOR_OR);
			$hasAnd = str_contains($value, self::URI_SEPARATOR_AND);
			
			// Prevent mixing AND/OR in a single filter value
			
			if ($hasOr && $hasAnd) {
				if (!$this->strictMode) {
					continue;
				} else {
					throw new InvalidFilterException('Filtering by combining "and" and "or" is not allowed.');
				}
			}
			
			$explodedKey = explode('.', $key, 2);
			$value = trim($value);
			
			// Skip if key is malformed or value is empty
			
			if (count($explodedKey) === 0 || $value === '') {
				continue;
			}
			
			$model = $builder->getModel();
			$modelTable = $model->getTable();
			
			if (count($explodedKey) === 1) {
				// Flat filter: e.g. ?where[name]=foo
				
				$field = $explodedKey[0];
				
				if (!$this->isAllowedFilter($relationPath === null ? $field : $relationPath.'.'.$field)) {
					continue;
				}
				
				// Ensure field exists in schema
				
				if (Schema::hasColumn($modelTable, $field)) {
					if ($hasOr) {
						// OR group of values
						
						$explodedValues = explode(self::URI_SEPARATOR_OR, $value);
						
						$builder->where(function (Builder $q) use ($type, $field, $explodedValues) {
							for ($i = 0; $i < count($explodedValues); $i++) {
								$this->addWhereClause($q, $type, $field, $explodedValues[$i], false, $i);
							}
						});
						
						$explodedValues = null;
					} else if ($hasAnd) {
						// AND group of values
						
						$explodedValues = explode(self::URI_SEPARATOR_AND, $value);
						
						$builder->where(function (Builder $q) use ($type, $field, $explodedValues) {
							foreach ($explodedValues as $explodedValue) {
								$this->addWhereClause($q, $type, $field, $explodedValue);
							}
						});
						
						$explodedValues = null;
					} else {
						// Single value
						
						$this->addWhereClause($builder, $type, $field, $value);
					}
				}
				
				$field = null;
			} else {
				// Nested filter on relation: e.g. ?where[author.name]=...
				
				$relation = $explodedKey[0];
				$fullRelationPath = $relationPath === null ? $relation : $relationPath.'.'.$relation;
				
				if ($this->isAllowedRelation($fullRelationPath)) {
					if (method_exists($model, $relation)) {
						$filter = $explodedKey[1];
						
						// Apply nested filtering recursively inside relation scope
						
						$builder->whereHas($relation, function (Builder $q) use ($type, $fullRelationPath, $filter, $value) {
							$this->filterFilterableColumns($q, $type, [$filter => $value], $fullRelationPath);
						});
						
						$filter = null;
					}
				}
				
				$relation = null;
				$fullRelationPath = null;
			}
			
			$model = null;
			$modelTable = null;
		}
	}
	
	/**
	 * Parses and applies all filters from the request to the query builder.
	 *
	 *  This method supports both 'like' and 'where' filter types, and delegates
	 *  the actual filtering logic to filterFilterableColumns().
	 *
	 *  Example URL parameters:
	 *  - ?like[name]=john
	 *  - ?where[created_at]=gte:2025-01-01%2000:00:00
	 *
	 * @return void
	 */
	private function filter(): void {
		// Loop through supported filter types and apply them if provided
		
		foreach ([self::FILTER_TYPE_LIKE, self::FILTER_TYPE_WHERE] as $type) {
			$filters = $this->request->input($type);
			
			if (!empty($filters)) {
				$this->filterFilterableColumns($this->query, $type, $filters);
			}
		}
	}
	
	/**
	 * Applies sorting to the query based on the `orderby` parameter from the request.
	 *
	 * Supports ascending and descending order by using a '-' prefix (e.g. `-created_at`).
	 * Falls back to defaultSorts() if no valid sort is specified.
	 *
	 * Example:
	 * - ?orderby=name,-created_at
	 *
	 * @return void
	 */
	private function sort(): void {
		// Extract and normalize the `orderby` parameter into an array
		
		$orderBy = array_filter(array_map('trim', explode(self::URI_SEPARATOR_AND, $this->request->input('orderby', ''))));
		$sorts = [];
		
		// Build Sort objects from the allowed keys
		
		if (!empty($orderBy)) {
			foreach ($orderBy as $ob) {
				$ob = strtolower($ob);
				
				// Only allow sorting on whitelisted columns
				
				if ($this->isAllowedSort($ob)) {
					// Leading dash (-) indicates descending order
					
					$sorts[] = str_starts_with($ob, '-') ? Sort::make(substr($ob, 1), 'desc') : Sort::make($ob);
				}
			}
		}
		
		// Use default sort configuration if none is provided
		
		if (count($sorts) === 0 && count($this->defaultSorts) > 0) {
			$sorts = $this->defaultSorts;
		}
		
		// Apply all valid sort conditions to the query
		
		if (count($sorts) > 0) {
			foreach ($sorts as $sort) {
				$this->query->orderBy($sort->column, $sort->direction);
			}
		}
		
		$orderBy = null;
		$sorts = null;
	}
	
	/**
	 * Ensures the query builder is set and fully prepared before execution.
	 *
	 * This method allows injecting a custom Builder instance and guarantees
	 * that the `prepare()` method has been called to apply filters, sorting,
	 * field selection, and relations.
	 *
	 * @param Builder|null $query
	 * @return void
	 */
	private function preFetch(?Builder $query = null): void {
		// Optionally override the default query builder
		
		if ($query !== null) {
			$this->query = $query;
		}
		
		// If not yet prepared, apply all processing logic
		
		if (!$this->hasBeenPrepared) {
			$this->prepare();
		}
	}
	
	/**
	 * @param int $perPage
	 * @return LengthAwarePaginator
	 */
	private function doPaginate(int $perPage): LengthAwarePaginator {
		return $this->query->paginate($perPage)->appends($this->request->query());
	}
}
