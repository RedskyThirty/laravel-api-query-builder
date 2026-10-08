<?php

namespace RedskyEnvision\ApiQueryBuilder\Support;

use InvalidArgumentException;

/**
 * Class FieldRequirement
 *
 * Immutable rule telling that some fields must be selected on a table,
 * either always or only when given fields are requested (possibly on other tables).
 *
 * @package RedskyEnvision\ApiQueryBuilder\Support
 */
final readonly class FieldRequirement {
	/**
	 * @param string[] $fields
	 * @param array<string, string[]> $whenRequested
	 */
	private function __construct(
		public array $fields,
		public array $whenRequested
	) {
		//
	}
	
	/**
	 * @param string[] $fields Fields to select
	 * @param array<string, string[]> $whenRequested Source table => fields. The requirement applies when at least
	 * one of them is explicitly requested. An empty array makes the requirement unconditional.
	 * @return self
	 */
	public static function make(array $fields, array $whenRequested = []): self {
		if ($fields === []) {
			throw new InvalidArgumentException('A field requirement needs at least one field.');
		}
		
		return new self($fields, $whenRequested);
	}
}
