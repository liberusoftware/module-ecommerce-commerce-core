# Changelog

All notable changes to `liberusoftware/ecommerce-commerce-core` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
versions are bare `MAJOR.MINOR.PATCH` tags — no `v` prefix — per ADR 0005 of the
Ecommerce repository.

## 0.3.0 — 2026-08-09

### Added

- **Read models and queries** — `StoreData`, `ChannelData`, `ChannelDomainData`,
  `StoreQuery`, `ChannelQuery`. A presentation package can now render a store
  without importing one.
- **`CommerceAccess`** — the policy questions asked by id, for an adapter that
  holds no model.

These exist because of a boundary rule with teeth: an `-api` package may not
import a `Models\` class at all. Without a read side the adapter has nothing to
serialise and no way to authorize, and the rule gets waived rather than met —
which is precisely the *business authorization solely in the presentation layer*
that the epic excludes. The stable contract is a side benefit worth having: a
column rename in this package is not a breaking change to a consumer that never
saw the column.

## 0.2.0 — 2026-08-09

The remaining six of the eight capabilities the specification names. 0.1.x had
Stores and Channels; this has the rest.

### Added

- **Shared states** — `StoreStatus` (draft → active ⇄ suspended → archived) and
  `ChannelStatus` (draft → active ⇄ disabled), each owning its own transition
  table so no surface keeps a second copy of the rules. Archived is terminal.
- **Commercial context** — `CommercialContext`, an immutable value carrying
  store, channel, team, currency, locale and timezone, and
  `CommercialContextResolver` behind the `ResolvesCommercialContext` contract.
  Channel overrides store for currency and locale; the timezone is the
  merchant's operating day and a channel has no say in it. Resolution never
  returns null — an unresolved context reports the deployment defaults, so no
  caller writes that fallback itself.
- **Order numbering** — `AllocateOrderNumber` behind `AllocatesOrderNumbers`,
  one sequence per store per prefix, allocated under a row lock inside a
  transaction. Numbers are spent on allocation, not on the order committing: a
  gap is free and a duplicate is not.
- **Settings** — per-store JSON key/value with one row per key, and
  `StoreSettingChanged` carrying the previous value, because "what was it before
  somebody broke it" is the question an audit actually asks.
- **Capabilities** — `Capability`, a closed set of switches other modules branch
  on, defaulting off. A stored row means somebody decided; no row means nobody
  has, and the two are kept distinct.
- **Domain events** — ten past-tense events across the lifecycle, settings,
  capabilities, domains and numbering.
- **Policies** — `StorePolicy` and `ChannelPolicy`, on team ownership read from
  the actor rather than from a Filament panel, so they answer the same way in a
  console command, a job and an API request. A store belonging to nobody is
  nobody's to edit; an archived store is a record, not a resource.
- **Actions** — `CreateStore`, `ChangeStoreStatus`, `CreateChannel`,
  `ChangeChannelStatus`, `AddChannelDomain`, `PromoteDomainToPrimary`,
  `RemoveChannelDomain`, `SetStoreSetting`, `SetStoreCapability`,
  `AllocateOrderNumber`. Status changes are idempotent — a retried job and a
  double-clicked button are not faults.
- `commerce-core.default_currency`.

### Changed

- **`stores` and `channels` gain `status`, and the commercial columns.** The
  migration is edited in place rather than added to: nothing consumes 0.1.x, and
  a second migration to patch a table this package invented one release ago is
  archaeology nobody needs.
- **A channel's first hostname becomes its primary automatically**, and
  releasing the primary promotes the oldest survivor. A channel with domains and
  no primary canonicalises on whatever sorts first, which is a silent SEO fault.

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
