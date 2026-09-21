<?php

declare(strict_types=1);

namespace Darvis\Nuki\Tests;

/**
 * Package users on, bundled UI off: the auth pages have to work without a single UI route.
 */
abstract class UiOffTestCase extends AuthTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('nuki.ui.enabled', false);
    }
}
