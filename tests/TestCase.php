<?php

declare(strict_types=1);

namespace Tests;

use \Orchestra\Testbench\TestCase as OTestCase;
use \BobKosse\DataSecurity\DataSecurityServiceProvider;

abstract class TestCase extends OTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            DataSecurityServiceProvider::class,
        ];
    }
}
