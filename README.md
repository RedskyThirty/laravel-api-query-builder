# Laravel API Query Builder

A lightweight and composable query builder for Laravel APIs, inspired by GraphQL flexibility.  
Select only the fields and relations you want. Filter, sort, paginate — cleanly.

**Current version:** 1.4.0<br>
**Last update:** March 13, 2026

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
- [Local Scopes](#local-scopes)
    - [Basic Usage](#basic-usage)
    - [Whitelisting Allowed Scopes](#whitelisting-allowed-scopes)
    - [Syntax Variants](#syntax-variants)
    - [Wildcard Mode](#wildcard-mode)
- [Custom Filters](#custom-filters)
- [Resource example](#resource-example)
- [DTO-backed Resources](#dto-backed-resources)
    - [When you own the DTO](#when-you-own-the-dto)
    - [When you don't own the DTO](#when-you-dont-own-the-dto)
    - [Accessing the DTO in data()](#accessing-the-dto-in-data)
- [Field Resolution Without a Query (ApiFieldResolver)](#field-resolution-without-a-query-apifieldresolver)
    - [Basic Usage](#basic-usage-1)
    - [alwaysFields Support](#alwaysfields-support)
    - [Strict Mode](#strict-mode)
    - [Inspecting Resolved Fields](#inspecting-resolved-fields)
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
- ✅ Custom filters for virtual or computed attributes (`customFilters()`)
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
    ->allowedScopes(['unverified'])
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

return $user !== null ? UserResource::make($user) : NotFoundResource::make();
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

return UserResource::make($user);
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

## Local Scopes

The query builder supports applying **Eloquent local scopes** directly from the URL via the `scopes` parameter.

Local scopes let you encapsulate commonly used query constraints in your models (e.g. `scopeUnverified()` → `unverified()`).

### Basic Usage

```sh
GET /api/users?scopes=unverified
```

This will automatically call the method `scopeUnverified()` defined on the `User` model — equivalent to:

```php
User::unverified()->get();
```

You can also pass multiple scopes separated by commas:

```sh
GET /api/users?scopes=unverified,visibleOnly
```

> **Note:** Scopes are applied **only to the root model**, not to nested relations.

### Whitelisting Allowed Scopes

To control which scopes can be applied from the URL, use the `allowedScopes()` method:

```php
$results = ApiQueryBuilder::make(User::class, $request)
->allowedScopes(['unverified', 'visibleOnly'])
->prepare()
->fetch();
```

If a request includes a scope that is not allowed, it will either:
- be ignored (in **non-strict mode**), or
- throw an `InvalidArgumentException` (in **strict mode**, enabled by default).

### Syntax Variants

The following formats are all accepted and automatically normalized:

| Input               | Invoked Scope |
|---------------------|----------------|
| `unverified`        | `scopeUnverified()` |
| `unverified()`      | `scopeUnverified()` |
| `scopeUnverified`   | `scopeUnverified()` |
| `scopeUnverified()` | `scopeUnverified()` |

### Wildcard Mode

To allow **all** local scopes to be accessible (not recommended in public APIs):

```php
->allowedScopes(['*'])
```

## Custom Filters

Sometimes a filterable attribute does not map to a real database column — for example, a computed field, a cross-table search, or any condition that cannot be expressed as a simple `where[column]=value`.

The `customFilters()` method lets you register a closure for any such field. The closure receives the query builder, the raw value from the request, and the filter type (`'where'` or `'like'`), giving you full control over how the condition is applied.

A common use case is a **unified search parameter** that matches across multiple columns or related tables in a single filter:

```php
use Illuminate\Database\Eloquent\Builder;

$results = ApiQueryBuilder::make(User::class, $request)
    ->allowedRelations(['profile'])
    ->allowedFilters(['search'])
    ->customFilters([
        'search' => function (Builder $builder, string $value, string $type): void {
            $operator  = $type === 'like' ? 'like' : '=';
            $formatted = $type === 'like' ? '%'.$value.'%' : $value;

            $builder->where(function (Builder $q) use ($operator, $formatted): void {
                $q->where('users.email', $operator, $formatted)
                  ->orWhereHas('profile', function (Builder $q) use ($operator, $formatted): void {
                      $q->whereRaw("CONCAT(firstname, ' ', lastname) $operator ?", [$formatted]);
                  });
            });
        },
    ])
    ->prepare()
    ->fetch();
```

```
# Exact match across email and full name
GET /api/users?where[search]=john

# Partial match across email and full name
GET /api/users?like[search]=john
```

#### Notes

- The filter name **must also appear in `allowedFilters()`** to be reachable.
- Custom filters only apply to the **root model**. They cannot be used inside nested relation paths (e.g. `where[relation.virtual_field]`).
- Operator parsing (`gt:`, `lte:`, `!`, etc.) is **not applied automatically** — the closure receives the raw value as typed in the request. Handle any parsing you need inside the closure itself.
- The `$type` parameter reflects which URL key was used: `'where'` for `where[field]=...` and `'like'` for `like[field]=...`.

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
            'profile' => $this->whenLoaded('profile', fn () => ProfileResource::make($this->profile)),
            'posts' => $this->whenLoaded('posts', fn () => PostResource::collection($this->posts)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
```

## DTO-backed Resources

By default, `ApiResource` expects an Eloquent model as its underlying resource. However, it is possible to back a resource with a DTO instead, while still benefiting from the full field-filtering logic provided by `ApiResource`.

To support this, the package provides the `ApiDtoResource` abstract class and the `ApiResourceable` contract.

### When you own the DTO

If you have full control over the DTO class, implement the `ApiResourceable` interface on it. This requires two methods: `getTable()`, which returns the table or registry key used for field lookups, and `getAttributes()`, which returns the raw attributes as a key/value array.

```php
use RedskyEnvision\ApiQueryBuilder\Contracts\ApiResourceable;

class UserData implements ApiResourceable
{
    public function __construct(
        public readonly int    $id,
        public readonly string $name,
        public readonly string $email,
    ) {}

    public function getTable(): string
    {
        return 'users';
    }

    public function getAttributes(): array
    {
        return [
            'id'    => $this->id,
            'name'  => $this->name,
            'email' => $this->email
        ];
    }
}
```

If your DTO already exposes a `toArray()` method that matches the structure you want to expose, you can use it directly in `getAttributes()` instead of listing every property manually:

```php
public function getAttributes(): array
{
    return $this->toArray();
}
```

Then extend `ApiDtoResource` instead of `ApiResource` in your resource class:

```php
use RedskyEnvision\ApiQueryBuilder\Resources\ApiDtoResource;

class UserResource extends ApiDtoResource
{
    protected function defaultFields(): array
    {
        return ['id', 'name', 'email'];
    }

    protected function data(): array
    {
        return [
            'id'    => $this->dto()->id,
            'name'  => $this->dto()->name,
            'email' => $this->dto()->email
        ];
    }
}
```

Usage remains identical to a model-backed resource:

```php
UserResource::make($dto);
UserResource::collection($dtos);
```

### When you don't own the DTO

If the DTO comes from a third-party library and you cannot make it implement `ApiResourceable`, override `resolveTable()` and `resolveAttributes()` directly in the resource class. No adapter or intermediate class is needed.

If the DTO exposes a `toArray()` method that fits your needs, you can use it directly in `resolveAttributes()`:

```php
use RedskyEnvision\ApiQueryBuilder\Resources\ApiDtoResource;

class UserResource extends ApiDtoResource
{
    protected function resolveTable(): string
    {
        return 'users';
    }

    protected function resolveAttributes(): array
    {
        /** @var ThirdPartyUserData $dto */
        $dto = $this->resource;

        return $dto->toArray();
    }

    protected function defaultFields(): array
    {
        return ['id', 'name', 'email'];
    }

    protected function data(): array
    {
        /** @var ThirdPartyUserData $dto */
        $dto = $this->resource;

        return [
            'id'    => $dto->id,
            'name'  => $dto->name,
            'email' => $dto->email,
        ];
    }
}
```

Otherwise, list the properties manually:

```php
protected function resolveAttributes(): array
{
    /** @var ThirdPartyUserData $dto */
    $dto = $this->resource;

    return [
        'id'    => $dto->id,
        'name'  => $dto->name,
        'email' => $dto->email,
    ];
}
```

Usage is unchanged:

```php
UserResource::make($thirdPartyDto);
```

### Accessing the DTO in data()

`ApiDtoResource` provides a `dto()` helper that returns `$this->resource` typed as `ApiResourceable`. This gives static analysis tools (PHPStan, Psalm) accurate type information, which `$this->resource` alone — declared as `mixed` in `JsonResource` — cannot provide.

```php
protected function data(): array
{
    return [
        'id'   => $this->dto()->id,
        'name' => $this->dto()->name,
    ];
}
```

If you prefer accessing DTO properties directly via `$this->property` without calling `dto()`, you can add a `@mixin` annotation on the resource class. This is purely an IDE hint and has no effect on static analysis tools:

```php
/** @mixin UserData */
class UserResource extends ApiDtoResource
{
    protected function data(): array
    {
        return [
            'id'   => $this->id,    // IDE-friendly, no dto() call needed
            'name' => $this->name,
        ];
    }
}
```

> **Note:** `@mixin` is recognized by PhpStorm and similar IDEs but is ignored by PHPStan and Psalm. If you rely on static analysis, prefer `dto()` for accurate type inference.

## Field Resolution Without a Query (ApiFieldResolver)

When working with DTO-backed resources, there is no Eloquent model and no database query to execute. `ApiQueryBuilder` cannot be used in this context because it requires a model class. `ApiFieldResolver` fills this gap: it provides the same field resolution and `FieldRegistry` registration logic, without any query building or Eloquent dependency.

### Basic Usage

Instantiate `ApiFieldResolver` in your controller before returning the resource. The `prepare()` call parses the `fields[...]` parameters from the request, filters them against `allowedFields`, and registers the result in `FieldRegistry` so that the resource can apply field filtering automatically.

```php
use App\DTOs\UserDto;
use App\Http\Resources\UserDtoResource;
use RedskyEnvision\ApiQueryBuilder\ApiFieldResolver;

class UserController extends Controller
{
    public function show(Request $request, int $id): UserDtoResource
    {
        $dto = new UserDto(
            id: $id,
            email: 'john@example.com',
            name: 'John',
            createdAt: now()
        );

        ApiFieldResolver::make($request)
            ->allowedFields([
                'users' => ['id', 'email', 'name', 'created_at']
            ])
            ->prepare('users');

        return UserDtoResource::make($dto);
    }
}
```

With `?fields[users]=id,email`, the response will only contain `id` and `email`.  
Without any `fields` parameter, the resource falls back to its `defaultFields()`.

### alwaysFields Support

`ApiFieldResolver` supports `alwaysFields()` with the same semantics as `ApiQueryBuilder`: the specified fields are injected unconditionally into any non-wildcard selection.

```php
ApiFieldResolver::make($request)
    ->allowedFields([
        'users' => ['id', 'email', 'name', 'created_at']
    ])
    ->alwaysFields([
        'users' => ['id']
    ])
    ->prepare('users');
```

### Strict Mode

By default, strict mode is enabled: requesting a field not listed in `allowedFields` throws an `InvalidFieldException`. Pass `false` as the second argument to `make()` to silently drop disallowed fields instead.

```php
ApiFieldResolver::make($request, strict: false)
    ->allowedFields([
        'users' => ['id', 'email', 'name']
    ])
    ->prepare('users');
```

### Inspecting Resolved Fields

After calling `prepare()`, you can query the resolved field list directly on the resolver instance if needed.

```php
$resolver = ApiFieldResolver::make($request)
    ->allowedFields(['users' => ['id', 'email', 'name', 'created_at']])
    ->prepare('users');

// Returns the filtered field list, e.g. ['id', 'email']
$fields = $resolver->getRequestedFieldsFor('users');

// Returns true if 'email' is in the resolved list (or if wildcard is active)
$hasEmail = $resolver->hasRequestedField('users', 'email');
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
                fn () => ProfileResource::make($this->user->profile)
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

### 4. Apply local scope

```
GET /api/users?scopes=unverified
```

### 5. Filter with equals, OR and AND

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

### 6. Filter with operators

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

### 7. Search (LIKE)

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

### 8. Sort results

```
GET /api/users?orderby=email
GET /api/users?orderby=-created_at
```

### 9. Pagination

```
GET /api/users?
    fields[users]=id,email,profile&
    fields[profiles]=firstname,lastname&
    relations=profile&
    per_page=10
```

### 10. "Single Resource Mode" and "Without Executing a Query"

All URL parameters related to **field selection** demonstrated above can also be used with single-resource endpoints like `/api/users/{id}` or `/api/users/random`.

This works especially well when using the `prepareWithoutQuery()` method, which allows parsing and validation of requested fields and relations **without performing any database query**. This ensures consistent response shaping even when loading a single resource outside of the query builder's automatic mode.

All URL examples provided above are fully replicable with the **Single Resource** usage (e.g. via `/api/users/{id}`).  
This ensures a consistent API experience whether you're fetching a list of resources or a single one.

When using the `prepareWithoutQuery()` method (query-less mode), **only field selection logic** (i.e. the `fields[...]` parameters) is parsed and validated. This is useful for shaping responses or metadata without performing any database access — such as in the demo endpoints `/api/users/random` — but it does not apply filters, sorting, or relation loading.


## Requirements

- PHP 8.3+
- Laravel 12+

## License

MIT © [Redsky-Thirty](https://github.com/Redsky-Thirty)
