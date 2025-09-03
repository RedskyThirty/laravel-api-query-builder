# Laravel API Query Builder

A lightweight and composable query builder for Laravel APIs, inspired by GraphQL flexibility.  
Select only the fields and relations you want. Filter, sort, paginate — cleanly.

Current version: 1.0.10

---

## Table of contents

- [Features](#features)
- [Installation](#installation)
- [Usage](#usage)
    - [Collection Mode](#collection-mode)
    - [Single Resource Mode](#single-resource-mode)
    - [Usage Without Executing a Query](#usage-without-executing-a-query)
- [Always Fields](#always-fields)
    - [Priority Rules](#-priority-rules)
- [Sorting](#sorting)
    - [Basic Usage](#basic-usage)
    - [Defining Allowed Sorts](#defining-allowed-sorts)
    - [Default Sorts](#default-sorts)
- [Resource example](#resource-example)
- [Nested Relation Helpers](#nested-relation-helpers)
    - [Usage](#usage)
    - [Behavior](#behavior)
    - [Signature](#signature)
- [Demo](#demo)
    - [Getting started](#-getting-started)
    - [Customizing the Demo](#-customizing-the-demo)
- [Example URLs](#example-urls)
- [Requirements](#requirements)
- [License](#license)

---

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

### Collection Mode

This mode is used to retrieve multiple results from the database. It can automatically decide between returning a full collection or a paginated response based on the presence of the `per_page` parameter.

```php
use App\Http\Resources\UserResource;
use RedskyEnvision\ApiQueryBuilder\ApiQueryBuilder;
use RedskyEnvision\ApiQueryBuilder\Sorts\Sort;

/*
 * Use auto-mode based on URI parameters
 */

$results = ApiQueryBuilder::make(User::class, $request)
    ->allowedRelations(['profile', 'addresses', 'posts', 'posts.comments'])
    ->allowedFields([
        'users' => ['id', 'email', 'created_at', 'profile', 'addresses', 'posts'],
        'profiles' => ['*'],
        'addresses' => ['*'],
        'posts' => ['title', 'excerpt', 'created_at', 'comments'],
        'comments' => ['username', 'message', 'created_at']
    ])
    ->alwaysFields([
        'posts' => ['author_id']
    ])
    ->allowedFilters(['name', 'email', 'created_at', 'addresses.*', 'profile.firstname', 'profile.lastname', 'posts.comments.username'])
    ->defaultSorts([Sort::make('created_at', 'desc')])
    
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

### Single Resource Mode

This mode allows you to build the query manually and return a single model instance (e.g., `User::find(...)`). Useful for retrieving one resource with relation and field selection logic applied.

```php
use App\Http\Resources\UserResource;
use App\Models\User;
use RedskyEnvision\ApiQueryBuilder\ApiQueryBuilder;
use RedskyEnvision\ApiQueryBuilder\Resources\NotFoundResource;

$user = ApiQueryBuilder::make(User::class, $request)
    ->allowedRelations(['profile', 'addresses', 'posts', 'posts.comments'])
    ->allowedFields([
        'users' => ['id', 'email', 'created_at', 'profile', 'addresses', 'posts'],
        'profiles' => ['*'],
        'addresses' => ['*'],
        'posts' => ['title', 'excerpt', 'created_at', 'comments'],
        'comments' => ['username', 'message', 'created_at']
    ])
    ->alwaysFields([
        'posts' => ['author_id']
    ])
    ->allowedFilters(['name', 'email', 'created_at', 'addresses.*', 'profile.firstname', 'profile.lastname', 'posts.comments.username'])
    ->prepare()
    ->query()
    ->where('id', $id)
    ->first();

return $user !== null ? new UserResource($user) : NotFoundResource::make();
```

### Usage Without Executing a Query

You can initialize field and relation selection logic without executing any database queries using the `prepareWithoutQuery()` method. This is particularly useful when preparing resource responses or resolving metadata without needing to fetch actual records.

This method parses the requested fields from the URL and stores them in the internal FieldRegistry, allowing your resources to behave consistently with the API expectations — all without triggering any Eloquent or SQL operations.

```php
use App\Http\Resources\UserResource;
use App\Models\User;
use RedskyEnvision\ApiQueryBuilder\ApiQueryBuilder;

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

return new UserResource($user);
```

## Always Fields

Sometimes, certain fields are **required internally** even if the client hasn't explicitly requested them. For example, foreign keys used to link relationships.

To support this, the `alwaysFields()` method allows you to define fields that should **always be included in the response**, even if they are not present in the `fields[...]` parameters or in the `defaultFields()` fallback.

```php
->alwaysFields([
    'posts' => ['author_id']
])
```

These fields will be automatically merged into the requested or default field set before the resource is rendered.

### ⚠️ Priority Rules
- `alwaysFields` are **not filtered** by `allowedFields`
- They are **injected unconditionally**
- Useful for internal fields like foreign keys or polymorphic links

## Sorting

The `orderby` parameter allows you to dynamically control the sort order of your API results.

### Basic Usage

You can specify one or multiple fields to sort by.  
By default, the sort order is **ascending** unless you prefix the field with a minus (`-`) for **descending** order.

#### Examples

```
# Sort by email ascending
GET /api/users?orderby=email

# Sort by created date descending
GET /api/users?orderby=-created_at

# Sort by multiple fields (first by created_at descending, then by email ascending)
GET /api/users?orderby=-created_at,email
```

### Defining Allowed Sorts

To restrict which fields can be used for sorting, you can use the `allowedSorts()` method:

```php
$results = ApiQueryBuilder::make(User::class, $request)
    ->allowedSorts(['id', 'email', 'created_at'])
    ->prepare()
    ->fetch();
```

If a user tries to sort by a field not in the allowed list, the query builder will ignore it (or throw an exception if **strict mode** is enabled).

### Default Sorts

You can define default sorts using the `defaultSorts()` method.  
This will be applied automatically if no `orderby` parameter is provided.

```php
use RedskyEnvision\ApiQueryBuilder\Sorts\Sort;

$results = ApiQueryBuilder::make(User::class, $request)
    ->defaultSorts([ Sort::make('created_at', 'desc') ])
    ->prepare()
    ->fetch();
```

This ensures that your API always returns predictable results even when no explicit sorting is requested.

## Resource example

```php
class UserResource extends ApiResource {
    protected function defaultFields(): array {
        return ['id', 'email', 'profile', 'created_at', 'updated_at'];
    }

    protected function data(): array {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'profile' => $this->whenLoaded('profile', fn () => new ProfileResource($this->profile)),
            'posts' => $this->whenLoaded('posts', fn () => PostResource::collection($this->posts)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
```

## Nested Relation Helpers

Sometimes you may want to include data in a **Resource** only if a **nested relation** has been loaded.  
Laravel provides the `whenLoaded()` method but it only works with **direct relations**.

To solve this, the package includes the `HasNestedWhenLoaded` trait.

### Usage

```php
use RedskyEnvision\ApiQueryBuilder\Resources\Concerns\HasNestedWhenLoaded;

class ContactResource extends ApiResource {
    use HasNestedWhenLoaded;

    protected function data(): array {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            
            // Only included if both "user" and "profile" are eager-loaded
            'profile' => $this->whenNestedLoaded(
                'user.profile',
                fn () => new ProfileResource($this->user->profile)
            ),
        ];
    }
}
```

### Behavior

- If the full relation chain (`user.profile`) is loaded → the callback is executed.
- If any intermediate relation is missing → the default value (`null`) is returned.
- Supports unlimited depth (e.g. `user.profile.address.country`).

### Signature

```php
whenNestedLoaded(string $relation, callable $callback, mixed $default = null): mixed
```

- `$relation`: Nested relation using dot notation (`parent.child.grandchild`).
- `$callback`: Function executed if all relations in the chain are loaded.
- `$default`: Value returned if the relation is not loaded (defaults to `null`).

## Demo

This package includes a demo Laravel application for local testing and exploration.

### 🚀 Getting started

To launch the demo project:

```bash
cd demo
composer install
php artisan migrate:fresh --seed
php artisan serve
```

Then open your browser at [http://localhost:8000/api/users](http://localhost:8000/api/users).

### 🔧 Customizing the Demo

The logic used to test the API query builder is defined inside:

```
/demo/routes/api.php
```

You can modify or extend this file freely to experiment with:

- Custom endpoints
- Different models and relationships
- Field selection, filtering, sorting, and nested relations

This allows you to test the package without needing to copy files into a separate Laravel project.

> Note: The `demo/` folder is for local use only and should not be required in production.

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

### 9. "Single Resource Mode" and "Without Executing a Query"

All URL parameters related to **field selection** demonstrated above can also be used with single-resource endpoints like `/api/users/{id}` or `/api/users/random`.

This works especially well when using the `prepareWithoutQuery()` method, which allows parsing and validation of requested fields and relations **without performing any database query**. This ensures consistent response shaping even when loading a single resource outside of the query builder's automatic mode.


All URL examples provided above are fully replicable with the **Single Resource** usage (e.g. via `/api/users/{id}`).
This ensures a consistent API experience whether you're fetching a list of resources or a single one.

In contrast, when using the `prepareWithoutQuery()` method (query-less mode), **only field selection logic** (i.e. the `fields[...]` parameters) is parsed and validated. This is useful for shaping responses or metadata without performing any database access, but it does not apply filters, sorting, or relation loading.

In contrast, when using the `prepareWithoutQuery()` method (query-less mode), **only field selection logic** (i.e. the `fields[...]` parameters) is parsed and validated. This is useful for shaping responses or metadata without performing any database access — such as in the demo endpoints `/api/users/random` — but it does not apply filters, sorting, or relation loading.


## Requirements

- PHP 8.3+
- Laravel 12+

## License

MIT © [Redsky-Thirty](https://github.com/Redsky-Thirty)
