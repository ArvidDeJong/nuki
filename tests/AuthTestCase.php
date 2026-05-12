<?php

declare(strict_types=1);

namespace Darvis\Nuki\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class AuthTestCase extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $this->enableAuthUsers($app);
    }
}
