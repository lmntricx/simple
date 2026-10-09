<?php

namespace Simple\Tests;

/**
 * Framework feature tests boot the small app in tests/Fixtures/app.
 */
abstract class TestCase extends \Simple\Testing\TestCase
{
    protected function basePath(): string
    {
        return __DIR__ . '/Fixtures/app';
    }
}
