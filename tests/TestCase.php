<?php

namespace Tests;

/**
 * Base class for this app's tests: boots the app with a fresh database per test.
 */
abstract class TestCase extends \Simple\Testing\TestCase
{
    protected function basePath(): string
    {
        return dirname(__DIR__);
    }
}
