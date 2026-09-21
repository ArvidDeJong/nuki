<?php

declare(strict_types=1);

use Darvis\Nuki\Tests\AuthTestCase;
use Darvis\Nuki\Tests\TestCase;
use Darvis\Nuki\Tests\UiOffTestCase;

uses(TestCase::class)->in(__DIR__.'/Feature');
uses(AuthTestCase::class)->in(__DIR__.'/Auth');
uses(UiOffTestCase::class)->in(__DIR__.'/UiOff');
