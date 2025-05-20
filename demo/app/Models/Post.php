<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Post
 *
 * @package App\Models
 */
class Post extends Model {
	use HasUuids, HasFactory;

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var list<string>
	 */
	protected $fillable = [
		'title',
		'description',
		'published_at'
	];

	/**
	 * @var array<string, string|class-string>
	 */
	protected $casts = [
		'published_at' => 'datetime'
	];

	/**
	 * @return BelongsTo
	 */
	public function user(): BelongsTo {
		return $this->belongsTo(User::class);
	}

	public function comments(): HasMany {
		return $this->hasMany(Comment::class);
	}
}
