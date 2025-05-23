<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class Profile
 *
 * @package App\Models
 */
class Profile extends Model {
	use HasUuids, HasFactory;

	/**
	 * @var list<string>
	 */
	protected $fillable = [
		'firstname',
		'lastname',
		'summary'
	];

	/**
	 * @return BelongsTo
	 */
	public function user(): BelongsTo {
		return $this->belongsTo(User::class);
	}
}
