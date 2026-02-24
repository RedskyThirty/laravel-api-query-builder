<?php

use App\DTOs\WeatherDto;
use App\Http\Resources\UserResource;
use App\Http\Resources\WeatherDtoResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use RedskyEnvision\ApiQueryBuilder\ApiFieldResolver;
use RedskyEnvision\ApiQueryBuilder\ApiQueryBuilder;
use RedskyEnvision\ApiQueryBuilder\Resources\NotFoundResource;
use RedskyEnvision\ApiQueryBuilder\Sorts\Sort;

// Collection

Route::get('/users', function (Request $request) {
	$results = ApiQueryBuilder::make(User::class, $request)
		->allowedRelations(['profile', 'addresses', 'posts', 'posts.comments'])
		->allowedScopes(['unverified'])
		->allowedFields([
			'users' => ['id', 'email', 'created_at', 'profile', 'addresses', 'posts'],
			'profiles' => ['*'],
			'addresses' => ['*'],
			'posts' => ['title', 'excerpt', 'created_at', 'comments'],
			'comments' => ['username', 'message', 'created_at']
		])
		->allowedFilters(['name', 'email', 'addresses.*', 'created_at', 'profile.firstname', 'profile.lastname', 'posts.comments.username'])
		->defaultSorts([Sort::make('created_at', 'desc')])
		->prepare()
		->fetch();

	// return $results; // Default JSON response
	return UserResource::collection($results);
});

// Single Resource

Route::get('/users/{id}', function (Request $request, string $id) {
	$user = ApiQueryBuilder::make(User::class, $request)
		->allowedRelations(['profile', 'addresses', 'posts', 'posts.comments'])
		->allowedFields([
			'users' => ['id', 'email', 'created_at', 'profile', 'addresses', 'posts'],
			'profiles' => ['*'],
			'addresses' => ['*'],
			'posts' => ['title', 'excerpt', 'created_at', 'comments'],
			'comments' => ['username', 'message', 'created_at']
		])
		->allowedFilters(['name', 'email', 'addresses.*', 'created_at', 'profile.firstname', 'profile.lastname', 'posts.comments.username'])
		->prepare()
		->query()
		->where('id', $id)
		->first();

	return $user !== null ? UserResource::make($user) : NotFoundResource::make();
})->whereUuid('id');

// Without Query

Route::get('/users/random', function (Request $request) {
	$user = User::with(['profile', 'addresses', 'posts', 'posts.comments'])->inRandomOrder()->first();

	ApiQueryBuilder::make(User::class, $request)
		->allowedRelations(['profile', 'addresses', 'posts', 'posts.comments'])
		->allowedFields([
			'users' => ['id', 'email', 'created_at', 'profile', 'addresses', 'posts'],
			'profiles' => ['*'],
			'addresses' => ['*'],
			'posts' => ['title', 'excerpt', 'created_at', 'comments'],
			'comments' => ['username', 'message', 'created_at']
		])
		->prepareWithoutQuery();

	return UserResource::make($user);
});

// DTO Resource (ApiFieldResolver)

Route::get('/weather/current', function (Request $request) {
	// Simulates a DTO built from an external source (API call, cache, computed data, etc.)
	// No database table involved — demonstrates ApiFieldResolver with a DTO-backed resource.

	$dto = new WeatherDto(
		location: 'Brussels, Belgium',
		condition: 'Partly cloudy',
		temperature_c: 14.2,
		temperature_f: 57.6,
		humidity: 72,
		wind_kph: 18.5,
		wind_direction: 'SW',
		recorded_at: Carbon::now()
	);

	ApiFieldResolver::make($request)
		->allowedFields([
			'weather' => ['location', 'condition', 'temperature_c', 'temperature_f', 'humidity', 'wind_kph', 'wind_direction', 'recorded_at']
		])
		->prepare('weather');

	return WeatherDtoResource::make($dto);
});
