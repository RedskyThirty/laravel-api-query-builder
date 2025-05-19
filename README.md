# Laravel API Query Builder

A lightweight and composable query builder for Laravel APIs, inspired by GraphQL flexibility.  
Select only the fields and relations you want. Filter, sort, paginate — cleanly.

## Features

- ✅ Dynamic field selection (`fields[users]=name,email`)
- ✅ Relation loading with nested control (`relations=user.posts`)
- ✅ Filtering (`where[status]=active`)
- ✅ Logical AND / OR filtering (`where[name]=john|doe`)
- ✅ Sorting (`orderby=-created_at`)
- ✅ Strict mode for validation
- ✅ Easily extendable

## Installation

```bash
composer require redsky-envision/laravel-api-query-builder
```

Laravel will auto-discover the package.

## Usage

```php
use RedskyEnvision\ApiQueryBuilder\ApiQueryBuilder;
use App\Http\Resources\UserResource;

$results = ApiQueryBuilder::make(User::class, $request)
    ->allowedRelations(['posts', 'profile'])
    ->allowedFields([
        'users' => ['*'],
        'posts' => ['title', 'published_at'],
    ])
    ->defaultSorts([
        Sort::make('created_at', 'desc'),
    ])
    ->prepare()
    ->fetch();

return UserResource::collection($results);
```

## Requirements

- PHP 8.3+
- Laravel 12+

## License

MIT © [Redsky-Thirty](https://github.com/Redsky-Thirty)
