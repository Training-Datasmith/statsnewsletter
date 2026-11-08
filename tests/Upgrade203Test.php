<?php

class Upgrade203Test extends StatsnewsletterTestCase
{
    public function testUpgrade203MovesHookRegistration()
    {
        Db::getInstance()->insert('ps_module', array(
            'name' => 'statsnewsletter',
            'active' => 1,
        ));
        $hookAdmin = Db::getInstance()->insert('ps_hook', array('name' => 'AdminStatsModules'));
        $hookDisplay = Db::getInstance()->insert('ps_hook', array('name' => 'displayAdminStatsModules'));
        $module = Db::getInstance()->getRow(
            "SELECT `id_module` FROM `ps_module` WHERE `name` = 'statsnewsletter'"
        );
        Db::getInstance()->insert('ps_hook_module', array(
            'id_module' => (int) $module['id_module'],
            'id_hook' => (int) $hookAdmin,
            'id_shop' => 1,
        ));

        $stats = new statsnewsletter();
        $this->assertTrue(upgrade_module_2_0_3($stats));
        $this->assertFalse(TestEnvironment::hookIsRegistered('AdminStatsModules'));

        $row = Db::getInstance()->getRow(
            "SELECT hm.`id_hook` FROM `ps_hook_module` hm
             INNER JOIN `ps_hook` h ON h.`id_hook` = hm.`id_hook`
             INNER JOIN `ps_module` m ON m.`id_module` = hm.`id_module`
             WHERE h.`name` = 'displayAdminStatsModules' AND m.`name` = 'statsnewsletter'"
        );
        $this->assertNotFalse($row);
        $this->assertSame((int) $hookDisplay, (int) $row['id_hook']);
    }
}
