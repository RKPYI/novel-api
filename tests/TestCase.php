<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        if ((getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? null)) === 'testing') {
            $database = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? null);

            if ($database === null || $database === '' || $database === 'rantale-api') {
                throw new RuntimeException('Tests must use a dedicated database, not the application database.');
            }

            $app['config']->set('database.connections.mysql.database', $database);
            $app['config']->set('database.connections.mariadb.database', $database);
        }

        return $app;
    }
}
