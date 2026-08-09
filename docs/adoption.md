# Adopting Commerce Core

## Install

The package is **not yet on Packagist**. Until it is, a consumer needs a VCS
repository entry:

```json
"repositories": [
    {"type": "vcs", "url": "https://github.com/liberusoftware/module-ecommerce-commerce-core"}
]
```

```bash
composer require liberusoftware/ecommerce-commerce-core
```

**Remove the `repositories` entry once the package is published** — `MODULE_DEVELOPMENT.md`
§6.3 says the entry's presence is information ("promoted but not yet
published"), and leaving ~100 stale entries behind is what the current fleet did
and regrets.

## Enable

Installing boots nothing. The module ships no `extra.laravel.providers`; the
deployment decides:

```dotenv
MODULES_ENABLED=ecommerce-commerce-core
```

Then `php artisan migrate`.

## What the host must supply

**A team model.** `Store::team()` resolves `config('commerce-core.team_model')`
at call time and never imports it — ADR 0006. The default is `App\Models\Team`,
which is Jetstream's and what every Liberu application uses. If yours lives
elsewhere:

```bash
php artisan vendor:publish --tag=commerce-core-config
```

**A trusted-host listener, if the host caches one.** `channel_domains` is the
natural source for a trusted-host list. The cache and the middleware reading it
belong to the application, so the application registers the invalidation — once,
on the model, not at the call sites that add domains:

```php
ChannelDomain::saved(fn () => Cache::forget(TrustHosts::CACHE_KEY));
ChannelDomain::deleted(fn () => Cache::forget(TrustHosts::CACHE_KEY));
```

**Channel-resolution middleware.** This package answers *which channel is
`shop.example.com`* (`ChannelResolver::resolve()`). *How a request carries the
answer* is an HTTP question and stays in the host: middleware that resolves the
host and puts the channel on the request under `ChannelResolver::ATTRIBUTE`.
`ChannelResolver::current()` and everything built on it read from there.

## Optional surfaces

Each is independent, and each pulls this package in itself — you never install
four things to get one.

| Want | Install |
| --- | --- |
| Domain logic only | `liberusoftware/ecommerce-commerce-core` |
| HTTP API | `liberusoftware/ecommerce-commerce-core-api` |
| Admin screens | `liberusoftware/ecommerce-commerce-core-filament` |
| Storefront components | `liberusoftware/ecommerce-commerce-core-livewire` |

Domain packages never depend on a presentation package, so the domain module
stays usable headlessly — through your own controllers, console commands, jobs
or Livewire components — with no Filament installed.

## Upgrading

Pre-1.0.0, a minor version may change the schema; the changelog says when and
what. `tests/SchemaTest.php` states the shape a consumer may rely on.

Nothing consumed this package before `0.3.0`, so `0.1.x` and `0.2.x` need no
upgrade path.
