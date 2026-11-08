<?php

class StatsnewsletterHookTest extends StatsnewsletterTestCase
{
    public function testHookExplainsMissingSubscriptionModule()
    {
        Db::resetInstance();
        $module = new statsnewsletter();
        $html = $module->hookDisplayAdminStatsModules(array());
        $this->assertContains('Email subscription', $html);
        $this->assertContains('must be installed', $html);
        foreach (Db::getStatementLog() as $sql) {
            $this->assertNotContains('`ps_customer`', $sql);
        }
    }

    public function testHookRendersDatabaseTotalsForFixedRange()
    {
        TestEnvironment::installCompanionModule();
        TestEnvironment::setEmployeeDates(1, '2020-01-01', '2020-12-31');
        TestEnvironment::insertCustomer(1, '2020-03-01 08:00:00');
        TestEnvironment::insertCustomer(1, '2020-11-02 09:00:00');
        TestEnvironment::insertVisitor(1, '2020-01-01 00:00:00');
        TestEnvironment::insertVisitor(1, '2020-06-15 12:00:00');
        TestEnvironment::insertVisitor(1, '2020-12-31 23:59:59');
        TestEnvironment::insertCustomer(1, '2019-12-31 23:59:59');
        TestEnvironment::insertCustomer(1, '2021-01-01 00:00:00');
        TestEnvironment::insertCustomer(2, '2020-06-15 12:00:00');
        TestEnvironment::insertVisitor(1, '2019-06-01 00:00:00');

        $module = new statsnewsletter();
        $html = $module->hookDisplayAdminStatsModules(array());

        $this->assertRegExp('/Customer registrations:\D*2/', $html);
        $this->assertRegExp('/Visitor registrations:\D*3/', $html);
        $this->assertRegExp('/Both:\D*5/', $html);
    }

    public function testHookEscapesExportLink()
    {
        TestEnvironment::installCompanionModule();
        $_SERVER['REQUEST_URI'] = '/admin/index.php?controller=AdminStats&module=statsnewsletter';
        $module = new statsnewsletter();
        $html = $module->hookDisplayAdminStatsModules(array());
        $this->assertContains('controller=AdminStats&amp;module=statsnewsletter&amp;export=1', $html);
        $this->assertContains('CSV Export', $html);
        $this->assertFalse(isset($_GET['export']));
    }

    public function testHookExportWritesCsvCountsAndStops()
    {
        $script = ChildProcess::writeTempScript(
            '<?php
require_once ' . var_export(STATSNEWSLETTER_TEST_ROOT . '/tests/support/bootstrap-core.php', true) . ';
TestEnvironment::installCompanionModule();
TestEnvironment::setEmployeeDates(1, "2020-06-15", "2020-06-15");
TestEnvironment::insertCustomer(1, "2020-06-15 14:10:00");
TestEnvironment::insertVisitor(1, "2020-06-15 14:10:00");
$_SERVER["REQUEST_URI"] = "/admin/index.php?controller=AdminStats&module=statsnewsletter";
$_GET["export"] = "1";
$module = new statsnewsletter();
$module->hookDisplayAdminStatsModules(array());
echo "SENTINEL";
'
        );
        $result = ChildProcess::runPhpScript($script);
        ChildProcess::assertCleanChildOutput($this, $result);
        $this->assertNotContains('SENTINEL', $result['stdout']);
        $this->assertNotContains('Customer registrations', $result['stdout']);

        $rows = ChildProcess::parseCsvCells($result['stdout']);
        $this->assertNotEmpty($rows);
        $header = $rows[0];
        $this->assertContains('Newsletter statistics', $header);
        $this->assertContains('customers', $header);
        $this->assertContains('Visitors', $header);
        $this->assertContains('Both', $header);

        $dataRow = null;
        foreach ($rows as $row) {
            if (isset($row[0]) && $row[0] === '14h') {
                $dataRow = $row;
                break;
            }
        }
        $this->assertNotNull($dataRow);
        $this->assertSame('1', $dataRow[1]);
        $this->assertSame('1', $dataRow[2]);
        $this->assertSame('2', $dataRow[3]);
    }
}
