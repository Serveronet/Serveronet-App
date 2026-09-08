<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        if (env('TESTS_DISABLED')) {
            $this->fail('Tests are disabled on this machine.');
        }
    }
}
