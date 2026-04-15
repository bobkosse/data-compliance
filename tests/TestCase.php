<?php

namespace Tests;

use \Orchestra\Testbench\TestCase as OTestCase;
use \BobKosse\DataCompliance\DataComplianceServiceProvider;

abstract class TestCase extends OTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            DataComplianceServiceProvider::class,
        ];
    }
}
