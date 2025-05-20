<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Class Address
 *
 * @package App\Models
 */
class Address extends Model {
	use HasUuids, HasFactory;

	/**
	 * @var list<string>
	 */
	protected $fillable = [
		'street',
		'zip',
		'locality'
	];

	/**
	 * @return MorphTo
	 */
	public function model(): MorphTo {
		return $this->morphTo();
	}

	/**
	 * @return string
	 */
	public function getFormattedAddressAttribute(): string {
		$formatted = [];

		if (!empty($this->street)) {
			$formatted[] = $this->street;
		}

		if (!empty($this->zip) || !empty($this->locality)) {
			$segments = [];

			if (!empty($this->zip)) $segments[] = $this->zip;
			if (!empty($this->locality)) $segments[] = $this->locality;

			$formatted[] = implode(' ', $segments);

			$segments = null;
		}

		return implode(', ', $formatted);
	}
}
