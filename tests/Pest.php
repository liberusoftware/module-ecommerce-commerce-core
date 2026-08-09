<?php

declare(strict_types=1);

use Liberu\PackageTestbench\PackageTestCase;
use Liberu\PackageTestbench\UsesTestUser;

/*
 * `UsesTestUser` brings `RefreshDatabase` with it, and it is wanted for both
 * halves: the `users` table is what `StoreContext`'s panel branch needs an
 * actor in, and the migrations this package's provider loads need a database
 * to run against.
 */
uses(PackageTestCase::class, UsesTestUser::class)->in(__DIR__);
