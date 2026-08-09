<?php

return [

    /*
    |--------------------------------------------------------------------------
    | The host's team model
    |--------------------------------------------------------------------------
    |
    | A `Store` belongs to a team, and the team belongs to the application, not
    | to this package. So the class is resolved here at call time rather than
    | imported — see ADR 0006 in the Ecommerce repository. A module that names
    | `App\Models\Team` in a `use` statement installs into one application.
    |
    | The default is Jetstream's, which is what every Liberu application uses.
    | An application whose team model lives elsewhere publishes this file and
    | says so.
    |
    */

    'team_model' => env('COMMERCE_CORE_TEAM_MODEL', 'App\\Models\\Team'),

    /*
    |--------------------------------------------------------------------------
    | Default currency
    |--------------------------------------------------------------------------
    |
    | What a store is created with, and what an unresolved commercial context
    | reports — a queued job on a multi-store deployment has no storefront to
    | read a currency from, and reporting one anyway would be a guess wearing a
    | fact's clothes. ISO 4217, three letters.
    |
    */

    'default_currency' => env('COMMERCE_CORE_DEFAULT_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Telemetry
    |--------------------------------------------------------------------------
    |
    | Structured records of this module's own domain events. Off by default:
    | a merchant estate produces one of these per checkout, and a package that
    | starts writing to a deployment's log the moment it installs has decided
    | somebody else's retention bill.
    |
    | `channel` is a Laravel log channel name, or null for the default one.
    |
    | Nothing here is exclusive. Everything the logger writes is a domain event
    | any listener can subscribe to, so a deployment wanting these in a metrics
    | backend subscribes to the events and leaves this off.
    |
    */

    'telemetry' => [
        'enabled' => (bool) env('COMMERCE_CORE_TELEMETRY', false),
        'channel' => env('COMMERCE_CORE_TELEMETRY_CHANNEL'),
    ],

];
