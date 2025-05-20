<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class Comment
 *
 * @package App\Models
 */
class Comment extends Model {
	use HasUuids, HasFactory;

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var list<string>
	 */
	protected $fillable = [
		'username',
		'message'
	];

	/**
	 * @return BelongsTo
	 */
	public function post(): BelongsTo {
		return $this->belongsTo(Post::class);
	}
}
