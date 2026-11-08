<?php

class DirectoryGuardTest extends StatsnewsletterTestCase
{
  /** @var string */
    private $childScript;

    protected function setUp()
    {
        parent::setUp();
        $root = STATSNEWSLETTER_TEST_ROOT;
        $this->childScript = ChildProcess::writeTempScript(
            '<?php
$target = $argv[1];
include $target;
echo "SENTINEL";
'
        );
    }

    private function assertGuardExitsQuietly($relativePath)
    {
        $result = ChildProcess::runPhpScript($this->childScript, array(STATSNEWSLETTER_TEST_ROOT . '/' . $relativePath));
        ChildProcess::assertCleanChildOutput($this, $result);
        $this->assertSame('', $result['stdout']);
        $this->assertNotContains('SENTINEL', $result['stdout']);
    }

    public function testRootIndexExitsWithoutOutput()
    {
        $this->assertGuardExitsQuietly('index.php');
    }

    public function testTestsIndexExitsWithoutOutput()
    {
        $this->assertGuardExitsQuietly('tests/index.php');
    }

    public function testTranslationsIndexExitsWithoutOutput()
    {
        $this->assertGuardExitsQuietly('translations/index.php');
    }

    public function testUpgradeIndexExitsWithoutOutput()
    {
        $this->assertGuardExitsQuietly('upgrade/index.php');
    }

    public function testModuleFileAbortsWhenPsVersionMissing()
    {
        $script = ChildProcess::writeTempScript(
            '<?php
include ' . var_export(STATSNEWSLETTER_TEST_ROOT . '/statsnewsletter.php', true) . ';
echo "SENTINEL";
'
        );
        $result = ChildProcess::runPhpScript($script);
        ChildProcess::assertCleanChildOutput($this, $result);
        $this->assertSame('', $result['stdout']);
        $this->assertNotContains('SENTINEL', $result['stdout']);
    }
}
