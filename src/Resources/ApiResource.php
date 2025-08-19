<?php

namespace RedskyEnvision\ApiQueryBuilder\Resources;

use RedskyEnvision\ApiQueryBuilder\Registries\FieldRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class ApiResource
 *
 * Base abstract resource class that enables dynamic field-level filtering
 * based on the fields requested via the API.
 *
 * Subclasses must define default fields and expose custom data using `data()`.
 * The actual response is trimmed to only include fields requested (via FieldRegistry).
 *
 * @package RedskyEnvision\ApiQueryBuilder\Resources
 */
abstract class ApiResource extends JsonResource {
	/**
	 * Defines the default list of fields to return if no specific
	 * field selection has been requested for this resource type.
	 *
	 * @return list<string> List of default field names
	 */
	abstract protected function defaultFields(): array;
	
	/**
	 * Defines the full set of available data exposed by the resource.
	 * Can include attributes, computed properties, or nested resources.
	 *
	 * @return array Full data payload keyed by field name
	 */
	abstract protected function data(): array;
	
	/**
	 * Builds the final API response array by including only the requested fields.
	 *
	 * If no fields have been registered in FieldRegistry for this model,
	 * it falls back to the defaultFields() method.
	 *
	 * Additionally, it checks for accessor-style properties as a fallback mechanism.
	 *
	 * @param Request $request
	 * @return array
	 */
	public function toArray(Request $request): array {
		// Get the list of fields requested for this resource's table
		
		$fieldRegistry = app(FieldRegistry::class);
		$requestedFields = $fieldRegistry->getFieldsFor($this->resource->getTable());
		
		// Fallback to default fields if none were explicitly requested
		
		if (FieldRegistry::isSelectingAll($requestedFields)) {
			$requestedFields = $this->defaultFields();
			$allowedFields = $fieldRegistry->getAllowedFieldsFor($this->resource->getTable());
			
			if (!empty($allowedFields) && !FieldRegistry::isSelectingAll($allowedFields)) {
				$requestedFields = array_values(array_intersect($requestedFields, $allowedFields));
			}
		}
		
		$resourceAttributes = $this->resource->getAttributes(); // Raw model attributes
		$data = $this->data(); // Extended resource data defined by subclass
		
		// Append computed fields (like accessors) not already in attributes or base data
		
		foreach ($requestedFields as $field) {
			if (
				!array_key_exists($field, $data) &&
				!array_key_exists($field, $resourceAttributes) &&
				$this->offsetExists($field)
			) {
				$data[$field] = $this->$field;
			}
		}
		
		// Return only the requested fields in the response
		
		return collect($data)
			->filter(fn ($_, $key) => in_array($key, $requestedFields))
			->all();
	}
}
