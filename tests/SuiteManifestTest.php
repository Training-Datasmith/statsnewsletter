<?php

class SuiteManifestTest extends StatsnewsletterTestCase
{
    public function testCommittedFileListMatchesSortedTestFiles()
    {
        $manifest = require STATSNEWSLETTER_TEST_ROOT . '/tests/support/suite-manifest.php';
        $files = array();
        foreach (scandir(STATSNEWSLETTER_TEST_ROOT . '/tests') as $entry) {
            if (substr($entry, -8) === 'Test.php') {
                $files[] = 'tests/' . $entry;
            }
        }
        sort($files, SORT_STRING);
        $this->assertSame($manifest['test_files'], $files);
    }

    public function testLiteralTestCountMatchesPhpunit()
    {
        $manifest = require STATSNEWSLETTER_TEST_ROOT . '/tests/support/suite-manifest.php';
        $count = 0;
        foreach ($manifest['test_files'] as $relative) {
            $path = STATSNEWSLETTER_TEST_ROOT . '/' . $relative;
            $source = file_get_contents($path);
            if ($source === false) {
                $this->fail('missing ' . $relative);
            }
            $count += preg_match_all('/function\s+test\w*\s*\(/', $source);
        }
        $this->assertSame($manifest['expected_test_count'], $count);
        $this->assertSame(24, $count);
    }
}
