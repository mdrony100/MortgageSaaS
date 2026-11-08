<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        if (! $app->environment('testing') || $app['config']->get('database.default') !== 'pgsql') {
            throw new \RuntimeException('Integration tests require APP_ENV=testing and isolated PostgreSQL; no migrations executed');
        }
        $connection = $app['db']->connection();
        $database = $connection->selectOne('select current_database() as name')->name;
        if (! str_ends_with($database, '_test')) {
            throw new \RuntimeException('Refusing integration tests outside an explicitly named _test PostgreSQL database');
        }

        return $app;
    }
}
