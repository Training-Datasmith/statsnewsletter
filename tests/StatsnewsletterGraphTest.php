<?php

class StatsnewsletterGraphTest extends StatsnewsletterTestCase
{
    private function probeWithDates($from, $to)
    {
        TestEnvironment::setEmployeeDates(1, $from, $to);
        $probe = new StatsnewsletterProbe();
        $probe->setEmployee(1);
        $probe->invokeGetData(3);

        return $probe;
    }

    public function testSameDayRangeCountsHours()
    {
        TestEnvironment::insertCustomer(1, '2020-06-15 00:05:00');
        TestEnvironment::insertCustomer(1, '2020-06-15 14:05:00');
        TestEnvironment::insertCustomer(1, '2020-06-15 14:59:00');
        TestEnvironment::insertVisitor(1, '2020-06-15 14:30:00');
        TestEnvironment::insertVisitor(1, '2020-06-15 23:00:00');
        $probe = $this->probeWithDates('2020-06-15', '2020-06-15');
        $values = $probe->getValuesSnapshot();
        $titles = $probe->getTitlesSnapshot();

        for ($h = 0; $h < 24; ++$h) {
            $this->assertArrayHasKey($h, $values[0]);
            $this->assertArrayHasKey($h, $values[1]);
            $this->assertArrayHasKey($h, $values[2]);
        }
        $this->assertSame(1, $values[0][0]);
        $this->assertSame(2, $values[0][14]);
        $this->assertSame(0, $values[0][23]);
        $this->assertSame(0, $values[1][0]);
        $this->assertSame(1, $values[1][14]);
        $this->assertSame(1, $values[1][23]);
        $this->assertSame(1, $values[2][0]);
        $this->assertSame(3, $values[2][14]);
        $this->assertSame(1, $values[2][23]);

        $this->assertSame('Newsletter statistics', $titles['main'][0]);
        $this->assertSame('customers', $titles['main'][1]);
        $this->assertSame('Visitors', $titles['main'][2]);
        $this->assertSame('Both', $titles['main'][3]);
    }

    public function testMonthRangeCountsDayOfMonth()
    {
        TestEnvironment::insertCustomer(1, '2020-03-01 08:00:00');
        TestEnvironment::insertCustomer(1, '2020-03-20 23:59:59');
        TestEnvironment::insertVisitor(1, '2020-03-01 00:00:00');
        TestEnvironment::insertCustomer(1, '2020-02-29 23:59:59');
        TestEnvironment::insertVisitor(1, '2020-03-21 00:00:00');
        $probe = $this->probeWithDates('2020-03-01', '2020-03-20');
        $values = $probe->getValuesSnapshot();

        $this->assertArrayHasKey(1, $values[0]);
        $this->assertArrayHasKey(20, $values[0]);
        $this->assertArrayNotHasKey(21, $values[0]);
        $this->assertSame(1, $values[0][1]);
        $this->assertSame(1, $values[0][20]);
        $this->assertSame(1, $values[1][1]);
        $this->assertSame(2, $values[2][1]);
        $this->assertSame(1, $values[2][20]);
    }

    public function testSubYearRangeCountsMonths()
    {
        TestEnvironment::insertCustomer(1, '2020-02-01 12:00:00');
        TestEnvironment::insertCustomer(1, '2020-06-15 00:00:00');
        TestEnvironment::insertVisitor(1, '2020-01-15 00:00:00');
        TestEnvironment::insertVisitor(1, '2020-06-15 23:59:59');
        TestEnvironment::insertCustomer(1, '2020-01-14 23:59:59');
        TestEnvironment::insertVisitor(1, '2020-07-01 00:00:00');
        $probe = $this->probeWithDates('2020-01-15', '2020-06-15');
        $values = $probe->getValuesSnapshot();

        for ($m = 1; $m <= 6; ++$m) {
            $this->assertArrayHasKey($m, $values[0]);
        }
        $this->assertSame(1, $values[0][2]);
        $this->assertSame(1, $values[0][6]);
        $this->assertSame(1, $values[1][1]);
        $this->assertSame(1, $values[1][6]);
        $this->assertSame(2, $values[2][6]);
        $this->assertSame(0, $values[0][1]);
    }

    public function testMultiYearRangeCountsYears()
    {
        TestEnvironment::insertCustomer(1, '2018-05-01 00:00:00');
        TestEnvironment::insertCustomer(1, '2020-01-01 00:00:00');
        TestEnvironment::insertVisitor(1, '2019-12-31 23:00:00');
        TestEnvironment::insertVisitor(1, '2020-12-31 23:59:59');
        TestEnvironment::insertCustomer(1, '2017-12-31 23:59:59');
        TestEnvironment::insertVisitor(1, '2021-01-01 00:00:00');
        $probe = $this->probeWithDates('2018-01-01', '2020-12-31');
        $values = $probe->getValuesSnapshot();

        $this->assertArrayHasKey(2018, $values[0]);
        $this->assertArrayHasKey(2019, $values[0]);
        $this->assertArrayHasKey(2020, $values[0]);
        $this->assertSame(1, $values[0][2018]);
        $this->assertSame(0, $values[0][2019]);
        $this->assertSame(1, $values[0][2020]);
        $this->assertSame(1, $values[1][2019]);
        $this->assertSame(1, $values[1][2020]);
        $this->assertSame(1, $values[2][2018]);
        $this->assertSame(1, $values[2][2019]);
        $this->assertSame(2, $values[2][2020]);
    }

    public function testBothSeriesSumsCustomerAndVisitorBuckets()
    {
        TestEnvironment::insertCustomer(1, '2020-04-02 14:00:00');
        TestEnvironment::insertCustomer(1, '2020-04-02 14:00:00');
        TestEnvironment::insertVisitor(1, '2020-04-02 14:00:00');
        $probe = $this->probeWithDates('2020-04-02', '2020-04-02');
        $values = $probe->getValuesSnapshot();

        $this->assertSame(2, $values[0][14]);
        $this->assertSame(1, $values[1][14]);
        $this->assertSame(3, $values[2][14]);
        $this->assertSame(0, $values[0][0]);
        $this->assertSame(0, $values[1][0]);
        $this->assertSame(0, $values[2][0]);

        for ($h = 0; $h < 24; ++$h) {
            $this->assertSame($values[0][$h] + $values[1][$h], $values[2][$h]);
        }
    }

    public function testOtherShopAndOutOfRangeRowsAreExcluded()
    {
        TestEnvironment::insertCustomer(1, '2020-08-01 10:00:00');
        TestEnvironment::insertVisitor(1, '2020-08-01 10:00:00');
        TestEnvironment::insertCustomer(2, '2020-08-01 10:00:00');
        TestEnvironment::insertVisitor(2, '2020-08-01 10:00:00');
        TestEnvironment::insertCustomer(1, '2020-07-31 10:00:00');
        TestEnvironment::insertVisitor(1, '2020-08-02 10:00:00');
        $probe = $this->probeWithDates('2020-08-01', '2020-08-01');
        $values = $probe->getValuesSnapshot();

        $this->assertSame(1, $values[0][10]);
        $this->assertSame(1, $values[1][10]);
        $this->assertSame(2, $values[2][10]);
        $this->assertSame(0, $values[0][11]);
        $this->assertSame(0, $values[1][11]);
        $this->assertSame(0, $values[2][11]);
    }

    public function testNullSubscriptionDatesAreExcluded()
    {
        TestEnvironment::insertCustomerNull(1);
        TestEnvironment::insertVisitorNull(1);
        TestEnvironment::insertCustomer(1, '2020-05-05 12:00:00');
        $probe = $this->probeWithDates('2020-01-01', '2020-12-31');
        $values = $probe->getValuesSnapshot();

        $this->assertSame(1, $values[0][5]);
        $this->assertSame(0, $values[1][5]);
        $this->assertSame(1, $values[2][5]);
    }

    public function testEmptyRangeStaysZeroWithoutDiagnostics()
    {
        $probe = $this->probeWithDates('2020-09-09', '2020-09-09');
        $values = $probe->getValuesSnapshot();

        for ($h = 0; $h < 24; ++$h) {
            $this->assertArrayHasKey($h, $values[0]);
            $this->assertArrayHasKey($h, $values[1]);
            $this->assertArrayHasKey($h, $values[2]);
        }
        $this->assertSame(0, $values[0][0]);
        $this->assertSame(0, $values[1][0]);
        $this->assertSame(0, $values[2][0]);
        $this->assertSame(0, $values[0][23]);
        $this->assertSame(0, $values[1][23]);
        $this->assertSame(0, $values[2][23]);
    }
}
