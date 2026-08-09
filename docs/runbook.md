# Runbook — Commerce Core

What breaks, what it looks like, and what to do. Every entry here is a failure
somebody will meet; the ones that are only theoretical are left out.

## Turning on telemetry

Everything below is easier with the module's own records. They are **off by
default** — a merchant estate writes one per checkout, and the deployment owns
that retention bill.

```dotenv
COMMERCE_CORE_TELEMETRY=true
COMMERCE_CORE_TELEMETRY_CHANNEL=stack   # optional; omit for the default channel
```

Records key on `context.event`, e.g. `commerce-core.store.status_changed`. The
levels are chosen so an alert can be written without parsing a message: anything
that **stops a storefront serving** or **moves traffic** is a `warning`, the rest
`info`.

| Event | Level | Why you would search for it |
| --- | --- | --- |
| `store.status_changed` / `channel.status_changed` | `warning` when it stops serving | A merchant reports their shop is down |
| `channel_domain.primary_changed` | `warning` | Canonical URLs changed; search traffic moved |
| `channel_domain.removed` | `warning` | A hostname stopped answering |
| `order_number.allocated` | `info` | Reconciling a gap in a numbering series |
| `store_setting.changed` | `info` | "What was this before somebody changed it" |

Setting **values are never logged** — only the key, whether there was a previous
value, and whether it was cleared. A setting may hold a credential, and a log
has different retention and different access than the table it came from.

## The module does nothing after installing

**Expected.** The package ships no `extra.laravel.providers`, so Composer boots
nothing. `ModuleManagerServiceProvider` is the only registrar and it acts only on
what the deployment names:

```dotenv
MODULES_ENABLED=ecommerce-commerce-core
```

Confirm with `php artisan module:list`. If the module is absent from that list
entirely, `config('modules.paths')` does not include the directory it installed
into.

## A merchant says their storefront is down

Work down the chain; it is nearly always one of the first three.

1. **Is the store serving?** A store in `suspended`, `draft` or `archived` does
   not trade. `store.status_changed` says when and from what.
2. **Is the channel serving?** `Channel::isServing()` is deliberately *both*
   questions — a channel is only serving when its store is too. A disabled
   channel on a healthy store looks identical to the shopper.
3. **Does the hostname resolve to a channel?** `channel_domains.host` holds
   hostnames only — no scheme, no port, lowercased. A row entered with
   `https://` or a trailing slash will never match a request.
4. **Is the host trusted?** The trusted-host list is a *host application*
   concern, and on this deployment it is `channel_domains` cached. If a merchant
   added a domain and it answers 400, that cache is stale — see the README's
   "what the host owns".

## `DomainAlreadyClaimed` on adding a hostname

A hostname is unique across **every** channel, not per channel. This is the
control that stops one hostname resolving to two storefronts, so the answer is
never to relax it. Find the holder:

```sql
select c.id as channel_id, c.store_id, d.host
from channel_domains d join channels c on c.id = d.channel_id
where d.host = 'shop.example.com';
```

Release it from the channel that holds it — `RemoveChannelDomain` — then add it.
Releasing the primary automatically promotes the oldest survivor, so the losing
channel does not end up primary-less.

## `InvalidStatusTransition`

The transition table refused the move. It is a `DomainException`, not a
validation failure: the surface should never have offered the transition, so
this arriving means a caller asked for something its own state machine said was
impossible.

- `archived` is **terminal**. An archived store is never reopened; a replacement
  store is created. Orders and invoices point at the old one and rewriting its
  history is how those stop reconciling.
- `draft` cannot be suspended — there is nothing to suspend yet.
- A surface offering options should build them from `StoreStatus::allowedTransitions()`
  rather than keeping a second copy of the table. If you are seeing this from a
  UI, that UI has a second copy and it has drifted.

## Two orders with the same number

Should be impossible: allocation takes a row lock inside a transaction. If it
happens, the number was **not** allocated through `AllocateOrderNumber` — check
for a consumer writing `orders.number` itself, or reading
`max(orders.number)`, which races with itself.

**Gaps are normal and are not a fault.** A number is spent on allocation, not on
the order committing, so an abandoned checkout burns one. That is deliberate:
holding the lock until the order commits would put the payment gateway's latency
inside a lock every other checkout waits on.

## A merchant sees no stores in the panel

The policies read `current_team_id` off the actor. An actor with no current team
is denied everything, and a store with `team_id = null` belongs to nobody and is
denied to everybody — deliberately stricter than the read scope, because seeing
an orphan is how it gets fixed and editing one is how it gets stolen.

```sql
select id, name, team_id, status from stores where team_id is null;
```

Assign the store to a team rather than loosening the policy.

## A queued job prices in the wrong currency

`ResolvesCommercialContext::current()` answers from the resolved channel first,
then the store `StoreContext` picks for writes, then **unresolved** — which
reports the deployment defaults rather than guessing. On a multi-store
deployment a job with no context genuinely cannot know the store, and the fix is
to pass the `CommercialContext` into the job rather than to resolve it inside.
It serialises to a flat array for exactly that.

## Upgrading

Pre-1.0.0 the schema may change within a minor version; the changelog says when.
`SchemaTest` in this package is the contract for what a consumer may rely on —
if you depend on a column not asserted there, say so in an issue rather than
assuming it is stable.
