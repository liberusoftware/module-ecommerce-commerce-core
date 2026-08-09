<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The schema commerce is built on: host → Channel → Store → Team.
 *
 * A `Team` may own several `Store`s, and a shopper on store A's domain must see
 * store A's catalogue rather than everything their merchant sells across every
 * store. That is why commerce scopes on `store_id` and not `team_id` — `team_id`
 * is the wrong grain and would under-scope.
 *
 * `channel_domains` is a table rather than a `domain` column on `channels`
 * because a storefront answers on several hostnames from day one: the apex,
 * `www`, a custom merchant domain, a platform subdomain. One column pushes
 * apex/`www` handling into web-server config the application cannot see, and
 * then the canonical the app generates and the host the request arrived on can
 * disagree. One row per hostname, one flagged primary for canonicals.
 *
 * `stores`, `channels` and `channel_domains` keep bare names — they existed in
 * the host before this package did, and `MODULE_DEVELOPMENT.md` §1.5 keeps an
 * extracted table's name. Tables this module invents carry the module prefix.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stores')) {
            Schema::create('stores', function (Blueprint $table) {
                $table->id();
                // Nullable for the same reason every other tenant column here is:
                // a store that predates ownership belongs to nobody rather than
                // to whoever happens to be first.
                $table->foreignId('team_id')->nullable()->index();
                $table->string('name');
                $table->string('slug')->unique();
                // Draft, not active: a store starts not serving, and somebody
                // decides it should. The opposite default publishes a half-built
                // storefront the moment a row is inserted.
                $table->string('status')->default('draft')->index();
                // The commercial context a channel inherits when it overrides
                // nothing. Held here rather than in settings because every read
                // of a store needs them and a settings lookup per price is a
                // query per price.
                $table->char('currency', 3)->default('USD');
                $table->string('locale')->default('en');
                $table->string('timezone')->default('UTC');
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('channels')) {
            Schema::create('channels', function (Blueprint $table) {
                $table->id();
                $table->foreignId('store_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                // One storefront, theme selected per resolved channel. Per-merchant
                // themes are later children with `parent: ecommerce`.
                $table->string('theme')->default('theme-ecommerce');
                $table->string('status')->default('draft')->index();
                // Null means "whatever the store says". A channel that overrides
                // nothing must not freeze a copy of the store's value, or changing
                // the store stops changing the storefront.
                $table->char('currency', 3)->nullable();
                $table->string('locale')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('channel_domains')) {
            Schema::create('channel_domains', function (Blueprint $table) {
                $table->id();
                $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
                // Hostname only — no scheme, no port. Unique across every channel:
                // a hostname resolving to two storefronts is the ambiguity this
                // whole table exists to prevent.
                $table->string('host')->unique();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ecommerce_commerce_core_settings')) {
            Schema::create('ecommerce_commerce_core_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('store_id')->constrained()->cascadeOnDelete();
                $table->string('key');
                // JSON rather than a string column: a setting that is a list or a
                // map otherwise arrives back as the word "Array", and callers grow
                // their own serialisation, each slightly different.
                $table->json('value')->nullable();
                $table->timestamps();

                $table->unique(['store_id', 'key']);
            });
        }

        if (! Schema::hasTable('ecommerce_commerce_core_capabilities')) {
            Schema::create('ecommerce_commerce_core_capabilities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('store_id')->constrained()->cascadeOnDelete();
                $table->string('capability');
                $table->boolean('enabled')->default(false);
                $table->timestamps();

                $table->unique(['store_id', 'capability']);
            });
        }

        if (! Schema::hasTable('ecommerce_commerce_core_order_sequences')) {
            Schema::create('ecommerce_commerce_core_order_sequences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('store_id')->constrained()->cascadeOnDelete();
                // One sequence per store per prefix, so a store can number its
                // web orders apart from its wholesale ones without a second table.
                $table->string('prefix')->default('');
                // The number the next allocation returns. Stored rather than
                // derived from `max(orders.number)`: orders belong to another
                // module, and a sequence that reads its consumer's table is a
                // dependency pointing the wrong way — as well as a race, since
                // two allocations reading the same max both return it.
                $table->unsignedBigInteger('next_number')->default(1);
                $table->unsignedInteger('pad_to')->default(6);
                $table->timestamps();

                $table->unique(['store_id', 'prefix']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_commerce_core_order_sequences');
        Schema::dropIfExists('ecommerce_commerce_core_capabilities');
        Schema::dropIfExists('ecommerce_commerce_core_settings');
        Schema::dropIfExists('channel_domains');
        Schema::dropIfExists('channels');
        Schema::dropIfExists('stores');
    }
};
