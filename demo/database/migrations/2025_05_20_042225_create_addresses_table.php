<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		Schema::create('addresses', function (Blueprint $table) {
			$table->uuid('id')->primary();
			$table->uuidMorphs('model');
			$table->string('street');
			$table->string('zip');
			$table->string('locality');
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		Schema::dropIfExists('addresses');
	}
};
