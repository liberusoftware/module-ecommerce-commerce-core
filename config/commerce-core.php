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

];
