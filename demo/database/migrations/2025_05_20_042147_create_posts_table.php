<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		Schema::create('posts', function (Blueprint $table) {
			$table->uuid('id')->primary();
			$table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
			$table->string('title');
			$table->longText('description');
			$table->timestamp('published_at')->nullable();
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		Schema::dropIfExists('posts');
	}
};
