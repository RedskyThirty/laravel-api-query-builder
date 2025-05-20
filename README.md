# Laravel API Query Builder

A lightweight and composable query builder for Laravel APIs, inspired by GraphQL flexibility.  
Select only the fields and relations you want. Filter, sort, paginate — cleanly.

## Features

- ✅ Dynamic field selection (`fields[users]=name,email`)
- ✅ Relation loading with nested control (`relations=posts.comments`)
- ✅ Filtering (`where[status]=active`)
- ✅ Logical AND / OR filtering (`where[name]=john|doe`)
- ✅ Sorting (`orderby=-created_at`)
- ✅ Strict mode for validation

## Installation

```bash
composer require redsky-thirty/laravel-api-query-builder
```

## Usage

```php
use RedskyEnvision\ApiQueryBuilder\ApiQueryBuilder;
use App\Http\Resources\UserResource;

/*
 * Use auto-mode based on uri params
 */

$results = ApiQueryBuilder::make(User::class, $request)
    ->allowedRelations(['posts', 'posts.comments', 'profile'])
    ->allowedFields([
        'users' => ['id', 'email', 'created_at', 'posts', 'profile'],
        'posts' => ['title', 'published_at'],
        'comments' => ['*'],
        'profile' => ['firstname', 'lastname']
    ])
    ->allowedFilters([
        'email', 'created_at',
        'posts.comments.username',
        'profile.*'
    ])
    ->defaultSorts([
        Sort::make('created_at', 'desc'),
    ])
    ->prepare()
    ->fetch();

/*
 * Force "Collection"
 * 
 * $results = ApiQueryBuilder::make(User::class, $request)
 *      ...
 *      ->get();
 * 
 * Force "LengthAwarePaginator"
 * 
 * $results = ApiQueryBuilder::make(User::class, $request)
 *      ...
 *      ->paginate();
 */

return UserResource::collection($results);
```

## Example URLs

### 1. Select default fields with relations

```
GET /api/users?relations=posts,posts.comments,profile
```

### 2. Select specific fields

```
GET /api/users?fields[users]=id,name,created_at
```

### 3. Load relations and limit fields

```
GET /api/users?
    fields[users]=id,email,profile&
    fields[profiles]=firstname,lastname&
    relations=profile

GET /api/users?
    fields[users]=id,email,profile,addresses,posts&
    fields[profiles]=firstname,lastname&
    fields[addresses]=street,zip,locality,formatted_address&
    fields[posts]=title,description,excerpt,comments&
    fields[comments]=message,username,created_at&
    relations=posts,posts.comments,profile,addresses
```

### 4. Filter with equals, OR and AND

```
GET /api/users?
    fields[users]=profile&
    fields[profiles]=firstname,lastname&
    relations=profile&
    where[profile.firstname]=john
    
GET /api/users?
    fields[users]=profile&
    fields[profiles]=firstname,lastname&
    relations=profile&
    where[profile.firstname]=!john

GET /api/users?
    fields[users]=profile&
    fields[profiles]=firstname,lastname&
    relations=profile&
    where[profile.firstname]=john|jane

GET /api/users?
    fields[users]=profile&
    fields[profiles]=firstname,lastname&
    relations=profile&
    where[profile.firstname]=!john,!jane
```

### 5. Filter with operators

```
GET /api/users?
    fields[users]=id,email,created_at&
    where[created_at]=gt:2025-05-01%2023:59:59

GET /api/users?
    fields[users]=id,email,created_at&
    where[created_at]=gte:2025-05-01%2000:00:00,lte:2025-05-31%2023:59:59

GET /api/users?
    fields[users]=id,email,created_at&
    where[created_at]=lte:2023-12-31%2023:59:59|gte:2025-01-01%2000:00:00
```

### 6. Search (LIKE)

```
GET /api/users?
    fields[users]=id,email&
    like[email]=gmail

GET /api/users?
    fields[users]=id,email&
    like[email]=!gmail

GET /api/users?
    fields[users]=id,email&
    like[email]=gmail|yahoo

GET /api/users?
    fields[users]=id,email&
    like[email]=!gmail,!yahoo
```

### 7. Sort results

```
GET /api/users?orderby=email
GET /api/users?orderby=-created_at
```

### 8. Pagination

```
GET /api/users?
    fields[users]=id,email,profile&
    fields[profiles]=firstname,lastname&
    relations=profile&
    per_page=10
```

## Requirements

- PHP 8.3+
- Laravel 12+

## License

MIT © [Redsky-Thirty](https://github.com/Redsky-Thirty)
