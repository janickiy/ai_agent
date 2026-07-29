<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $app->detectEnvironment(static fn (): string => 'testing');
        $app['config']->set([
            'app.env' => 'testing',
            'database.default' => 'sqlite',
            'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'session.driver' => 'array',
            'mail.default' => 'array',
        ]);

        return $app;
    }
}
