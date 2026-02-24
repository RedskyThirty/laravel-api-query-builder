<?php

namespace RedskyEnvision\ApiQueryBuilder\Contracts;

/**
 * Interface ApiResourceable
 *
 * Must be implemented by DTOs intended to be used with ApiResource.
 * Provides the minimal surface required by ApiResource to resolve
 * the resource table name and its raw attributes.
 *
 * @package RedskyEnvision\ApiQueryBuilder\Contracts
 */
interface ApiResourceable {
	/**
	 * Returns the table or resource name used for FieldRegistry lookups.
	 *
	 * @return string
	 */
	public function getTable(): string;
	
	/**
	 * Returns the raw attributes keyed by field name.
	 *
	 * @return array<string, mixed>
	 */
	public function getAttributes(): array;
}
