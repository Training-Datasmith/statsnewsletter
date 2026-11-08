<?php

if (!defined('STATSNEWSLETTER_TEST_ROOT')) {
    define('STATSNEWSLETTER_TEST_ROOT', dirname(dirname(__DIR__)));
}

require_once STATSNEWSLETTER_TEST_ROOT . '/tests/support/Harness.php';
require_once STATSNEWSLETTER_TEST_ROOT . '/tests/support/ChildProcess.php';
require_once STATSNEWSLETTER_TEST_ROOT . '/statsnewsletter.php';
require_once STATSNEWSLETTER_TEST_ROOT . '/tests/support/StatsnewsletterProbe.php';
require_once STATSNEWSLETTER_TEST_ROOT . '/upgrade/upgrade-2.0.3.php';

set_error_handler(function ($severity, $message, $file, $line) {
    if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED
        || (defined('E_STRICT') && $severity === E_STRICT)) {
        if (class_exists('PHPUnit_Framework_Error')) {
            throw new PHPUnit_Framework_Error($message, $severity, $file, $line);
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    return false;
});
