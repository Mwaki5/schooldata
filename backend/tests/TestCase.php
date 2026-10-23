<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    /**
     * Spatie caches the permission catalog in memory; the role and
     * permission tables are rebuilt by the migrations on every test, so
     * the cache must not outlive them.
     */
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Run a simulated request the way a fresh PHP worker would.
     *
     * The JWT guard resolves its token through the shared `tymon.jwt`
     * instance, which caches the first token it ever parses for the life
     * of the application — so within one test every request carrying a
     * bearer token would run as the first authenticated user, whatever
     * token it held. Production never sees this (each request is a fresh
     * process), so for bearer-token requests drop the cached token and
     * the resolved guards first: each request then authenticates from
     * its own token. Requests without one are left alone so session
     * state placed by `actingAs()` survives to them.
     *
     * @param  array<mixed>  $parameters
     * @param  array<mixed>  $cookies
     * @param  array<mixed>  $files
     * @param  array<string, mixed>  $server
     */
    public function call(
        $method,
        $uri,
        $parameters = [],
        $cookies = [],
        $files = [],
        $server = [],
        $content = null,
    ): TestResponse {
        if (isset($this->defaultHeaders['Authorization'])) {
            app('tymon.jwt')->unsetToken();
            app('auth')->forgetGuards();
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
