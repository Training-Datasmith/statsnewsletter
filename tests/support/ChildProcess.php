<?php

class ChildProcess
{
    private static $diagnosticPattern = '/(Notice|Warning|Deprecated|Strict Standards|Parse error|Fatal error)/';

    public static function runPhpScript($scriptPath, array $argv = array(), array $env = array())
    {
        $php = PHP_BINARY;
        if ($php === '' || $php === false) {
            $php = 'php';
        }
        $cmd = $php
            . ' -d error_reporting=-1'
            . ' -d display_errors=1'
            . ' -d display_startup_errors=1'
            . ' -d log_errors=0'
            . ' -d date.timezone=UTC'
            . ' ' . escapeshellarg($scriptPath);
        foreach ($argv as $arg) {
            $cmd .= ' ' . escapeshellarg($arg);
        }

        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );

        $mergedEnv = array_merge($_ENV, $env);
        $proc = proc_open($cmd, $descriptors, $pipes, null, $mergedEnv);
        if (!is_resource($proc)) {
            throw new RuntimeException('proc_open failed');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $status = proc_close($proc);

        return array(
            'stdout' => $stdout,
            'stderr' => $stderr,
            'status' => (int) $status,
        );
    }

    public static function assertCleanChildOutput(PHPUnit_Framework_TestCase $test, array $result)
    {
        $test->assertSame(0, $result['status'], 'child exit status, stderr: ' . $result['stderr']);
        $test->assertNotRegExp(self::$diagnosticPattern, $result['stdout'], 'stdout must not contain PHP diagnostics');
        $test->assertNotRegExp(self::$diagnosticPattern, $result['stderr'], 'stderr must not contain PHP diagnostics');
    }

    public static function writeTempScript($contents)
    {
        $dir = sys_get_temp_dir() . '/statsnewsletter-child-' . getmypid();
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $path = tempnam($dir, 'child');
        if ($path === false) {
            throw new RuntimeException('tempnam failed');
        }
        file_put_contents($path, $contents);
        chmod($path, 0700);

        return $path;
    }

    public static function parseCsvCells($csv)
    {
        $lines = preg_split("/\r\n|\n|\r/", trim($csv));
        $rows = array();
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $rows[] = str_getcsv($line, ';');
        }

        return $rows;
    }
}
