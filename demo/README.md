# Laravel API Query Builder — Demo Application

This is a minimal embedded Laravel application used to test and explore the `laravel-api-query-builder` package locally.

---

## Getting started

```bash
cd demo
composer install
php artisan migrate:fresh --seed
php artisan serve
```

Then open your browser at [http://localhost:8000/api/users](http://localhost:8000/api/users).

---

## Available endpoints

### GET `/api/users` — Collection

Returns a list of users. Supports field selection, relation loading, filtering, sorting, and pagination.

**Allowed relations:** `profile`, `addresses`, `posts`, `posts.comments`  
**Allowed scopes:** `unverified`  
**Default sort:** `created_at` descending

```
# All users (default fields, default sort)
GET /api/users

# With specific fields and relations
GET /api/users?fields[users]=id,email,created_at&fields[profiles]=firstname,lastname&relations=profile

# With filtering
GET /api/users?where[email]=john@example.com
GET /api/users?like[email]=gmail
GET /api/users?where[profile.firstname]=john|jane&relations=profile

# With sorting
GET /api/users?orderby=-created_at,email

# With pagination
GET /api/users?per_page=10

# With scope
GET /api/users?scopes=unverified
```

**Custom filter (`search`):** matches a value against the user's email and profile name (`firstname`/`lastname`) in a single filter, demonstrating `customFilters()`.

```
# Exact match across email, firstname and lastname
GET /api/users?where[search]=john

# Partial match across the same fields
GET /api/users?like[search]=john
```

**Custom sort (`verification_priority`):** ranks verified users before unverified ones (or the reverse in descending order), tie-broken by `created_at`, demonstrating `customSorts()`.

```
# Verified users first, most recent first within each group
GET /api/users?orderby=verification_priority

# Unverified users first, most recent first within each group
GET /api/users?orderby=-verification_priority
```

---

### GET `/api/users/{id}` — Single Resource

Returns a single user by UUID. Returns a `404` JSON response if no user is found.

**Allowed relations:** `profile`, `addresses`, `posts`, `posts.comments`

```
GET /api/users/{uuid}
GET /api/users/{uuid}?fields[users]=id,email&fields[posts]=title,excerpt&relations=posts
```

---

### GET `/api/users/random` — Without Query

Returns a randomly selected user with all relations pre-loaded, using `prepareWithoutQuery()` to apply field selection without executing any query via `ApiQueryBuilder`.

```
GET /api/users/random
GET /api/users/random?fields[users]=id,email&fields[posts]=title
```

> Only field selection (`fields[...]`) is applied. Relations are always eager-loaded in full beforehand (`profile`, `addresses`, `posts`, `posts.comments`) — `prepareWithoutQuery()` does not apply filters, sorting, or relation loading, so a `relations=...` parameter has no effect on this endpoint.

---

### GET `/api/weather/current` — DTO Resource

Demonstrates `ApiFieldResolver` with a DTO-backed resource. No database table is involved: the data is built from a hardcoded `WeatherDto` instance, simulating an external source (API call, cache, computed result, etc.).

The `ApiFieldResolver` class handles field resolution and `FieldRegistry` registration without requiring an Eloquent model, enabling `WeatherDtoResource` to filter the response to only the requested fields.

**Available fields:** `location`, `condition`, `temperature_c`, `temperature_f`, `humidity`, `wind_kph`, `wind_direction`, `recorded_at`  
**Default fields:** `location`, `condition`, `temperature_c`, `humidity`, `recorded_at`

```
# Default fields
GET /api/weather/current

# Specific fields
GET /api/weather/current?fields[weather]=location,condition,temperature_c,temperature_f

# All fields
GET /api/weather/current?fields[weather]=location,condition,temperature_c,temperature_f,humidity,wind_kph,wind_direction,recorded_at
```

**Default response (no `fields` parameter):**
```json
{
    "data": {
        "location": "Brussels, Belgium",
        "condition": "Partly cloudy",
        "temperature_c": 14.2,
        "humidity": 72,
        "recorded_at": "2026-02-24T10:00:00.000000Z"
    }
}
```

**Response with `?fields[weather]=location,temperature_c,temperature_f,wind_kph,wind_direction`:**
```json
{
    "data": {
        "location": "Brussels, Belgium",
        "temperature_c": 14.2,
        "temperature_f": 57.6,
        "wind_kph": 18.5,
        "wind_direction": "SW"
    }
}
```

**Relevant files:**
- `app/DTOs/WeatherDto.php` — the DTO, implements `ApiResourceable`
- `app/Http/Resources/WeatherDtoResource.php` — extends `ApiDtoResource`
- `routes/api.php` — the `/weather/current` route

---

## Customizing the demo

The routes are defined in:

```
demo/routes/api.php
```

You can freely modify or extend this file to experiment with:

- Custom endpoints
- Different models and relationships
- Field selection, filtering, sorting, and nested relations
- DTO-backed resources with `ApiFieldResolver`

> The `demo/` folder is for local use only and should not be shipped to production.
