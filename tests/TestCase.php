<?php

declare(strict_types=1);

namespace Tests;

use BobKosse\DataSecurity\DataSecurityServiceProvider;
use Orchestra\Testbench\TestCase as OTestCase;

abstract class TestCase extends OTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            DataSecurityServiceProvider::class,
        ];
    }
}
