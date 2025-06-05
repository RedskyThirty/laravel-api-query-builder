<?php

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use RedskyEnvision\ApiQueryBuilder\ApiQueryBuilder;
use RedskyEnvision\ApiQueryBuilder\Resources\NotFoundResource;
use RedskyEnvision\ApiQueryBuilder\Sorts\Sort;

Route::get('/users', function (Request $request) {
	$results = ApiQueryBuilder::make(User::class, $request)
		->allowedRelations(['profile', 'addresses', 'posts', 'posts.comments'])
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
		->defaultSorts([Sort::make('created_at', 'desc')])
		->prepare()
		->query()
		->where('id', $id)
		->first();

	return $user !== null ? new UserResource($user) : NotFoundResource::make();
})->whereUuid('id');
