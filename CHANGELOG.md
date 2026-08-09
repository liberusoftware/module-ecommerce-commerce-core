# Changelog

All notable changes to `liberusoftware/ecommerce-commerce-core` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
versions are bare `MAJOR.MINOR.PATCH` tags — no `v` prefix — per ADR 0005 of the
Ecommerce repository.

## 0.1.1 — 2026-08-09

### Fixed

- **`package-testbench` is required at `^1.6`, not `^1.5`.** The suite uses
  `UsesTestUser`, which 1.5.0 does not ship, so the declared constraint claimed
  a bottom end that cannot install. `--prefer-lowest` is the only thing that
  looks at the bottom of a range, and it is why the compatibility workflow runs
  on a tag rather than never.

## 0.1.0 — 2026-08-09

First release. The module is extracted from `liberusoftware/ecommerce-laravel`,
where these classes shipped as `App\Models\Store`, `App\Models\Channel`,
`App\Models\ChannelDomain`, `App\Services\ChannelResolver` and
`App\Services\StoreContext`. Behaviour is unchanged; the namespace is not.

### Added

- `Store`, `Channel` and `ChannelDomain` models, and the migration that creates
  `stores`, `channels` and `channel_domains`.
- `ChannelResolver` — which channel answers on a given hostname. The HTTP half
  of the question, how a request carries the answer, stays in the host as
  middleware.
- `StoreContext` — the store a read is scoped to and the store a write belongs
  to, kept as separate questions because conflating them is how tenant scopes
  go wrong.
- `config/commerce-core.php`, carrying `team_model`. The host's team model is
  resolved from configuration at call time and never imported.

### Changed from the host implementation

- **`ChannelDomain` no longer clears the trusted-host cache itself.** That cache
  and the middleware reading it belong to the host application, so the host
  registers the model listener. It is still registered once, on the model,
  rather than at the call sites that add domains.
- **`Store::team()` resolves `config('commerce-core.team_model')`** instead of
  importing `App\Models\Team`.
