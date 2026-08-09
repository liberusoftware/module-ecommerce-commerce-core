<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Liberu\Ecommerce\CommerceCore\Models\Channel;
use Liberu\Ecommerce\CommerceCore\Models\Store;

/**
 * The migration is the module's public surface as much as its classes are — a
 * consumer's data lives in these tables, and a column quietly renamed or
 * dropped between releases is an outage on deploy rather than a failing build.
 *
 * These assert the shape a consumer may rely on. Changing one of them on
 * purpose means an entry in the changelog and, past 1.0.0, a major version.
 */
it('creates every table the module owns', function (string $table) {
    expect(Schema::hasTable($table))->toBeTrue();
})->with([
    'stores',
    'channels',
    'channel_domains',
    'ecommerce_commerce_core_settings',
    'ecommerce_commerce_core_capabilities',
    'ecommerce_commerce_core_order_sequences',
]);

it('keeps the extracted tables on their bare names and prefixes the invented ones', function () {
    // MODULE_DEVELOPMENT.md §1.5: a table that existed in the host before this
    // package did keeps its name when it moves; a table this package invents
    // carries the module prefix. Both halves are asserted because getting
    // either one wrong is silent until a second module claims the name.
    expect(Schema::hasTable('stores'))->toBeTrue()
        ->and(Schema::hasTable('ecommerce_commerce_core_stores'))->toBeFalse()
        ->and(Schema::hasTable('settings'))->toBeFalse()
        ->and(Schema::hasTable('ecommerce_commerce_core_settings'))->toBeTrue();
});

it('gives stores the columns a consumer reads', function (string $column) {
    expect(Schema::hasColumn('stores', $column))->toBeTrue();
})->with(['id', 'team_id', 'name', 'slug', 'status', 'currency', 'locale', 'timezone', 'archived_at', 'created_at', 'updated_at']);

it('gives channels the columns a consumer reads', function (string $column) {
    expect(Schema::hasColumn('channels', $column))->toBeTrue();
})->with(['id', 'store_id', 'name', 'theme', 'status', 'currency', 'locale']);

it('starts a store in draft and a channel in draft, whatever the caller forgot to say', function () {
    $storeId = DB::table('stores')->insertGetId(['name' => 'Bare', 'slug' => 'bare', 'created_at' => now(), 'updated_at' => now()]);
    $channelId = DB::table('channels')->insertGetId(['store_id' => $storeId, 'name' => 'Web', 'created_at' => now(), 'updated_at' => now()]);

    // Inserted through the query builder rather than the model on purpose:
    // this is asserting the database's own defaults, which is what protects a
    // row written by a seeder, a fixture or another module's migration.
    expect(Store::query()->find($storeId)->status->value)->toBe('draft')
        ->and(Channel::query()->find($channelId)->status->value)->toBe('draft')
        ->and(Store::query()->find($storeId)->currency)->toBe('USD');
});

it('refuses two channels claiming one hostname, at the database and not only in the action', function () {
    $channel = Channel::factory()->create();
    DB::table('channel_domains')->insert(['channel_id' => $channel->id, 'host' => 'shop.example.com', 'created_at' => now(), 'updated_at' => now()]);

    DB::table('channel_domains')->insert(['channel_id' => Channel::factory()->create()->id, 'host' => 'shop.example.com', 'created_at' => now(), 'updated_at' => now()]);
})->throws(QueryException::class);

it('refuses a store holding one settings key twice', function () {
    $store = Store::factory()->create();
    $row = ['store_id' => $store->id, 'key' => 'k', 'value' => '"a"', 'created_at' => now(), 'updated_at' => now()];

    DB::table('ecommerce_commerce_core_settings')->insert($row);
    DB::table('ecommerce_commerce_core_settings')->insert($row);
})->throws(QueryException::class);

it('refuses a store deciding one capability twice', function () {
    $store = Store::factory()->create();
    $row = ['store_id' => $store->id, 'capability' => 'guest_checkout', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()];

    DB::table('ecommerce_commerce_core_capabilities')->insert($row);
    DB::table('ecommerce_commerce_core_capabilities')->insert($row);
})->throws(QueryException::class);

it('refuses a store holding two sequences for one prefix', function () {
    $store = Store::factory()->create();
    $row = ['store_id' => $store->id, 'prefix' => 'WEB-', 'next_number' => 1, 'pad_to' => 6, 'created_at' => now(), 'updated_at' => now()];

    DB::table('ecommerce_commerce_core_order_sequences')->insert($row);
    DB::table('ecommerce_commerce_core_order_sequences')->insert($row);
})->throws(QueryException::class);

it('declares a cascade, so deleting a store cannot leave an orphan behind', function (string $table, string $column, string $parent) {
    // The declaration is asserted rather than the deletion. Whether the engine
    // acts on it is a connection setting — SQLite enforces foreign keys only
    // with the pragma on, and a pragma inside RefreshDatabase's transaction is
    // a no-op — so a behavioural test here would pass or fail on how the suite
    // is wired rather than on what this migration says.
    $foreignKey = collect(Schema::getForeignKeys($table))
        ->first(fn (array $key): bool => in_array($column, $key['columns'], true));

    expect($foreignKey)->not->toBeNull()
        ->and($foreignKey['foreign_table'])->toBe($parent)
        ->and(strtolower((string) $foreignKey['on_delete']))->toBe('cascade');
})->with([
    'channels' => ['channels', 'store_id', 'stores'],
    'domains' => ['channel_domains', 'channel_id', 'channels'],
    'settings' => ['ecommerce_commerce_core_settings', 'store_id', 'stores'],
    'capabilities' => ['ecommerce_commerce_core_capabilities', 'store_id', 'stores'],
    'sequences' => ['ecommerce_commerce_core_order_sequences', 'store_id', 'stores'],
]);

it('leaves a store unowned rather than pointing at a team that is not there', function () {
    // `team_id` carries no foreign key on purpose: the team belongs to the host
    // application, whose table this package must not constrain. Null means
    // nobody, which is what the policies deny everything on.
    $store = Store::factory()->create(['team_id' => null]);

    expect($store->team_id)->toBeNull()
        ->and(Schema::hasColumn('stores', 'team_id'))->toBeTrue();
});
