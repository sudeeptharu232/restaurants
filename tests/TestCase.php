<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        if (app()->resolved('db')) {
            foreach (app('db')->getConnections() as $name => $connection) {
                try {
                    $connection->disconnect();
                    app('db')->purge($name);
                } catch (\Exception $e) {
                    // Ignore disconnect errors
                }
            }
        }

        parent::tearDown();
    }
}
