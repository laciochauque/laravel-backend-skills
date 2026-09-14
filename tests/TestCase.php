<?php

declare(strict_types=1);

namespace Laciochauque\BackendSkills\Tests;

use Laciochauque\BackendSkills\BackendSkillsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [BackendSkillsServiceProvider::class];
    }
}
