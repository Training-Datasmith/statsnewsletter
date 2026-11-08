<?php

require_once __DIR__ . '/support/bootstrap-core.php';

abstract class StatsnewsletterTestCase extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        TestEnvironment::resetAll();
    }

    protected function tearDown()
    {
        TestEnvironment::resetAll();
    }
}
