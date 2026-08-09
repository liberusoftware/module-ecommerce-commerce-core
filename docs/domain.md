# Commerce Core — the domain

What the module owns, and the shape of its public surface. Anything not listed
here is internal and may change without a major version.

## Aggregates

**Store** — a storefront's worth of commerce data, owned by a team. Carries the
commercial defaults (`currency`, `locale`, `timezone`) that a channel inherits.
Lifecycle: `draft → active ⇄ suspended → archived`; **archived is terminal**.

**Channel** — a way into a store: a set of hostnames and the theme they render
with. May override `currency` and `locale`; may not override `timezone`, which
is the merchant's operating day rather than a per-storefront presentation
choice. Lifecycle: `draft → active ⇄ disabled`.

**ChannelDomain** — one hostname, normalised on write (lowercased, port
stripped) and **unique across every channel**. Exactly one per channel is
primary; canonical URLs are built from it.

## The write surface — actions

Every mutation goes through one of these. They dispatch the domain events and
enforce the invariants; a caller that writes a model directly bypasses both.

| Action | Notes |
| --- | --- |
| `CreateStore` | Derives a unique slug by suffix, not by rejection. Starts in `draft` |
| `ChangeStoreStatus` | Idempotent. Throws `InvalidStatusTransition` |
| `CreateChannel` | Starts in `draft`, overrides nothing |
| `ChangeChannelStatus` | Idempotent. Throws `InvalidStatusTransition` |
| `AddChannelDomain` | First hostname becomes primary automatically. Throws `DomainAlreadyClaimed` |
| `PromoteDomainToPrimary` | Silent when already primary |
| `RemoveChannelDomain` | Promotes the oldest survivor if the primary goes |
| `SetStoreSetting` / `->forget()` | Silent when the value has not changed |
| `SetStoreCapability` | Records a decision even when it matches the default |
| `AllocateOrderNumber` | Row-locked. One sequence per store per prefix |

## The read surface — queries and data

`StoreQuery` and `ChannelQuery` return `StoreData`, `ChannelData` and
`ChannelDomainData`: immutable, `JsonSerializable`, and free of Eloquent. A
consumer that reads through these is unaffected by a column rename here.

`CommerceAccess` answers the policy questions **by id**, for a consumer that
holds no model. It resolves the subject and asks the gate — it makes no
authorization decision of its own.

## Commercial context

`ResolvesCommercialContext::current()` never returns null. It answers from the
resolved channel, then the store `StoreContext` picks for writes, then
`CommercialContext::unresolved()` — the deployment defaults. `CommercialContext`
carries ids rather than models because it crosses job boundaries.

## Events

Ten, past tense, each carrying enough to act on without a query:

`StoreCreated` · `StoreStatusChanged` · `ChannelCreated` · `ChannelStatusChanged` ·
`ChannelDomainAdded` · `ChannelDomainRemoved` · `PrimaryDomainChanged` ·
`StoreSettingChanged` · `StoreCapabilityChanged` · `OrderNumberAllocated`

`StoreStatusChanged` and `ChannelStatusChanged` carry **both** ends of the move:
"started serving" and "stopped serving" are different subscriptions, and a
listener given only the new status has to guess which happened.
`StoreSettingChanged` carries the previous value, because that is the question an
audit actually asks. `ChannelDomainRemoved` carries the hostname rather than the
model, because the row is gone by the time a listener runs.

## Authorization

`StorePolicy` — `viewAny`, `view`, `create`, `update`, `delete`, `changeStatus`,
`manageSettings`. `ChannelPolicy` — `viewAny`, `view`, `update`, `delete`,
`manageDomains`. There is deliberately **no** channel `create`: Laravel hands a
create check the class name rather than a model, so there would be no store to
ask. Creating a channel is an edit to its store.

Both read the team from the actor's `current_team_id`, not from a Filament
panel, so they answer identically in a console command, a job and an API
request. An archived store is a record rather than a resource: it cannot be
updated or moved. Only a `draft` store may be deleted — deletion cascades to
channels, domains, settings and sequences, and a store that ever traded is
archived instead.

## Tables

`stores`, `channels` and `channel_domains` keep bare names — they existed in the
host before this package did, and `MODULE_DEVELOPMENT.md` §1.5 keeps an
extracted table's name. Tables this module invented carry the prefix:
`ecommerce_commerce_core_settings`, `_capabilities`, `_order_sequences`.

`stores.team_id` carries **no foreign key**. The team belongs to the host
application and this package must not constrain the host's table.
