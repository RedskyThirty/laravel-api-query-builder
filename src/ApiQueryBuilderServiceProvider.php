<?php

namespace RedskyEnvision\ApiQueryBuilder;

use RedskyEnvision\ApiQueryBuilder\Registries\FieldRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Class ApiQueryBuilderServiceProvider
 *
 * @package RedskyEnvision\ApiQueryBuilder
 */
class ApiQueryBuilderServiceProvider extends ServiceProvider {
	/**
	 * @return void
	 */
	public function register(): void {
		$this->app->singleton(FieldRegistry::class, fn () => new FieldRegistry());
	}
}
